<?php

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Spatie\Activitylog\Models\Activity;

new #[Title('Auditoría')] class extends Component {
    #[Url]
    public string $search = '';

    #[Url]
    public ?string $date_start = null;

    #[Url]
    public ?string $date_end = null;

    #[Url]
    public ?string $action_type = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->can('auditoria.ver'), 403);
    }

    #[Computed]
    public function entries(): LengthAwarePaginator
    {
        return Activity::query()
            ->with('causer')
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('description', 'like', "%{$this->search}%")
                        ->orWhere('subject_type', 'like', "%{$this->search}%")
                        ->orWhereHas('causer', function ($q) {
                            $q->where('name', 'like', "%{$this->search}%")
                                ->orWhere('email', 'like', "%{$this->search}%");
                        });

                    if (is_numeric($this->search)) {
                        $q->orWhere('causer_id', (int) $this->search);
                    }
                });
            })
            ->when($this->date_start, fn ($q) => $q->whereDate('created_at', '>=', $this->date_start))
            ->when($this->date_end, fn ($q) => $q->whereDate('created_at', '<=', $this->date_end))
            ->when($this->action_type, fn ($q) => $q->where('log_name', $this->action_type))
            ->orderBy('created_at', 'desc')
            ->paginate(25);
    }

    #[Computed]
    public function actionTypes(): array
    {
        return [
            '' => __('Todos'),
            'auth' => __('Autenticación'),
            'default' => __('Modelos'),
        ];
    }

    public function causerName(?Activity $activity): string
    {
        if ($activity === null || $activity->causer === null) {
            return __('Sistema');
        }

        return $activity->causer->name ?? __('Desconocido');
    }

    public function subjectLabel(?Activity $activity): string
    {
        if ($activity === null || $activity->subject === null) {
            return $activity?->description ?? '—';
        }

        $model = class_basename($activity->subject_type);

        if (method_exists($activity->subject, 'name')) {
            return "{$model} #{$activity->subject->id}";
        }

        return "{$model} #{$activity->subject->id}";
    }

    public function actionLabel(string $description): string
    {
        return match ($description) {
            'login' => __('Inicio de sesión'),
            'logout' => __('Cierre de sesión'),
            'failed_login' => __('Falló inicio de sesión'),
            'lockout' => __('Bloqueo por intentos'),
            'registered' => __('Registro'),
            'password_reset' => __('Restableció contraseña'),
            'two_factor_enabled' => __('2FA activado'),
            'two_factor_disabled' => __('2FA desactivado'),
            'email_verified' => __('Email verificado'),
            'created' => __('Creado'),
            'updated' => __('Actualizado'),
            'deleted' => __('Eliminado'),
            default => ucfirst(str_replace('_', ' ', $description)),
        };
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->date_start = null;
        $this->date_end = null;
        $this->action_type = null;
    }
}; ?>

<section class="space-y-6">
    <x-page-heading
        :heading="__('Auditoría')"
        :subheading="__('Revisá quién hizo qué, cuándo y sobre qué recurso en el sistema.')"
    />

    {{-- Filters --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-end">
            <flux:input
                wire:model.live="search"
                icon="magnifying-glass"
                :placeholder="__('Buscar por acción o tipo...')"
                class="max-w-xs"
            />

            <flux:field>
                <flux:label>{{ __('Desde') }}</flux:label>
                <flux:input wire:model="date_start" type="date" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Hasta') }}</flux:label>
                <flux:input wire:model="date_end" type="date" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Tipo') }}</flux:label>
                <flux:select wire:model.live="action_type">
                    @foreach ($this->actionTypes as $value => $label)
                        <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>
        </div>

        <flux:button variant="ghost" wire:click="resetFilters" icon="x-mark">
            {{ __('Limpiar filtros') }}
        </flux:button>
    </div>

    {{-- Table --}}
    <x-table>
        <thead class="bg-neutral-50 dark:bg-neutral-800">
            <tr>
                <x-table.heading>{{ __('Fecha') }}</x-table.heading>
                <x-table.heading>{{ __('Usuario') }}</x-table.heading>
                <x-table.heading>{{ __('Acción') }}</x-table.heading>
                <x-table.heading>{{ __('Sujeto') }}</x-table.heading>
                <x-table.heading>{{ __('Detalles') }}</x-table.heading>
            </tr>
        </thead>
        <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
            @forelse ($this->entries as $entry)
                <x-table.row wire:key="audit-row-{{ $entry->id }}">
                    <x-table.cell>
                        <flux:text class="text-neutral-500">
                            {{ $entry->created_at->format('d/m/Y H:i') }}
                        </flux:text>
                    </x-table.cell>
                    <x-table.cell>
                        <flux:heading size="sm">
                            {{ $this->causerName($entry) }}
                        </flux:heading>
                    </x-table.cell>
                    <x-table.cell>
                        <flux:badge
                            :color="$entry->log_name === 'auth' ? 'blue' : 'zinc'"
                        >
                            {{ $this->actionLabel($entry->description) }}
                        </flux:badge>
                    </x-table.cell>
                    <x-table.cell>
                        <flux:text>
                            {{ $this->subjectLabel($entry) }}
                        </flux:text>
                    </x-table.cell>
                    <x-table.cell>
                        @if ($entry->properties && collect($entry->properties)->isNotEmpty())
                            <flux:modal.trigger name="properties-{{ $entry->id }}">
                                <flux:button variant="ghost" size="sm" icon="eye">
                                    {{ __('Ver detalles') }}
                                </flux:button>
                            </flux:modal.trigger>

                            <flux:modal name="properties-{{ $entry->id }}" class="w-full md:w-[36rem]">
                                <div class="space-y-4">
                                    <flux:heading size="lg">{{ __('Detalles de la actividad') }}</flux:heading>

                                    <div class="rounded-lg bg-neutral-50 p-4 font-mono text-xs dark:bg-neutral-800">
                                        <pre class="whitespace-pre-wrap break-words">{{ json_encode($entry->properties ? collect($entry->properties)->toArray() : [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                    </div>

                                    <div class="flex justify-end">
                                        <flux:modal.close>
                                            <flux:button variant="ghost">{{ __('Cerrar') }}</flux:button>
                                        </flux:modal.close>
                                    </div>
                                </div>
                            </flux:modal>
                        @else
                            <flux:text class="text-neutral-400">—</flux:text>
                        @endif
                    </x-table.cell>
                </x-table.row>
            @empty
                <tr>
                    <x-table.cell colspan="5" class="py-6 text-center text-neutral-500">
                        {{ __('No hay registros de auditoría') }}
                    </x-table.cell>
                </tr>
            @endforelse
        </tbody>
    </x-table>

    {{-- Pagination --}}
    @if ($this->entries->hasPages())
        <div class="flex items-center justify-between">
            <flux:text class="text-neutral-500">
                {{ __('Mostrando :first - :last de :total', [
                    'first' => $this->entries->firstItem(),
                    'last' => $this->entries->lastItem(),
                    'total' => $this->entries->total(),
                ]) }}
            </flux:text>

            {{ $this->entries->links() }}
        </div>
    @endif
</section>
