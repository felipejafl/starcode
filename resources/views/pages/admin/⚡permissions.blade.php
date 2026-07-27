<?php

use App\Models\Permission;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\PermissionRegistrar;

new #[Title('Permisos')] class extends Component {
    use WithPagination;

    public string $search = '';

    #[Url]
    public int $perPage = 10;

    public ?int $editingPermissionId = null;
    public string $name = '';

    /**
     * @var array<int, int>
     */
    public array $perPageOptions = [10, 20, 50, 100];

    /**
     * Autoriza el acceso inicial al listado de permisos.
     *
     * Livewire lo ejecuta al montar la ruta admin.permissions definida en routes/admin.php;
     * aborta con 403 si el usuario autenticado no conserva el permiso de consulta.
     */
    public function mount(): void
    {
        abort_unless(Auth::user()->can('permisos.ver'), 403);
    }

    /**
     * Define las reglas del formulario de alta y edición de permisos.
     *
     * Livewire las consume durante save(); excluye el permiso editado de la unicidad.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('permissions', 'name')->ignore($this->editingPermissionId),
            ],
        ];
    }

    #[Computed]
    /**
     * Obtiene la página de permisos con sus roles para la tabla administrativa.
     *
     * La vista consume $this->permissions; precarga roles para evitar consultas por fila.
     */
    public function permissions(): LengthAwarePaginator
    {
        return Permission::query()
            ->with('roles')
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->orderBy('name')
            ->paginate($this->perPage)
            ->withQueryString();
    }

    /**
     * Restablece la paginación cuando Livewire actualiza filtros de la tabla.
     *
     * Livewire lo invoca por convención al modificar search o perPage mediante wire:model.live.
     */
    public function updating(string $name): void
    {
        if ($name === 'perPage' || $name === 'search') {
            $this->resetPage();
        }
    }

    /**
     * Prepara un formulario vacío para crear un permiso.
     *
     * Lo invoca wire:click desde el botón de creación y verifica el permiso correspondiente.
     */
    public function create(): void
    {
        $this->authorizeAbility('permisos.crear');
        $this->resetForm();
    }

    /**
     * Carga un permiso en el formulario de edición.
     *
     * Lo invoca wire:click desde cada fila y elimina errores de una interacción anterior.
     */
    public function edit(int $permissionId): void
    {
        $this->authorizeAbility('permisos.actualizar');

        $permission = Permission::query()->findOrFail($permissionId);

        $this->editingPermissionId = $permission->id;
        $this->name = $permission->name;

        $this->resetErrorBag();
    }

    /**
     * Crea o actualiza un permiso y actualiza la caché de autorizaciones.
     *
     * Lo invoca wire:submit; invalida la caché de Spatie Permission antes de cerrar el modal.
     */
    public function save(): void
    {
        $isCreating = $this->editingPermissionId === null;

        $this->authorizeAbility($isCreating ? 'permisos.crear' : 'permisos.actualizar');

        $validated = $this->validate();

        if ($isCreating) {
            Permission::create([
                'name' => $validated['name'],
                'guard_name' => 'web',
            ]);
        } else {
            Permission::query()->findOrFail($this->editingPermissionId)->update([
                'name' => $validated['name'],
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->resetForm();

        Flux::modal('permission-form')->close();

        Flux::toast(variant: 'success', text: $isCreating
            ? __('Permiso creado.')
            : __('Permiso actualizado.'));
    }

    /**
     * Elimina un permiso que no sea el requisito de acceso administrativo.
     *
     * Lo invoca wire:click desde la tabla; protege admin.acceder e invalida la caché de Spatie
     * Permission tras una baja válida.
     */
    public function delete(int $permissionId): void
    {
        $this->authorizeAbility('permisos.eliminar');

        $permission = Permission::query()->findOrFail($permissionId);

        if ($permission->name === 'admin.acceder') {
            throw ValidationException::withMessages([
                'general' => __('El permiso admin.acceder no se puede eliminar.'),
            ]);
        }

        $permission->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        if ($this->editingPermissionId === $permissionId) {
            $this->resetForm();
        }

        Flux::toast(variant: 'success', text: __('Permiso eliminado.'));
    }

    /**
     * Descarta el estado del formulario y cierra el modal de permisos.
     *
     * Lo invoca la interacción de cancelación de la vista y no realiza cambios persistentes.
     */
    public function cancel(): void
    {
        $this->resetForm();
        Flux::modal('permission-form')->close();
    }

    /**
     * Exige una capacidad administrativa para una acción del componente.
     *
     * Lo invocan las acciones públicas del CRUD y aborta la solicitud Livewire con 403 si falta.
     */
    private function authorizeAbility(string $ability): void
    {
        abort_unless(Auth::user()->can($ability), 403);
    }

    /**
     * Restablece el estado transitorio y los errores del formulario de permisos.
     *
     * Lo invocan las acciones de creación, guardado, eliminación y cancelación.
     */
    private function resetForm(): void
    {
        $this->reset([
            'editingPermissionId',
            'name',
        ]);

        $this->resetErrorBag();
    }
}; ?>

<section class="space-y-6">
    <x-page-heading :heading="__('Permisos')" :subheading="__('Definí las capacidades de bajo nivel que después se agrupan y distribuyen en roles.')" />

    @if ($errors->has('general'))
        <flux:callout variant="danger" icon="x-circle" :heading="$errors->first('general')" />
    @endif

    {{-- Toolbar --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <flux:input wire:model.live="search" icon="magnifying-glass" :placeholder="__('Buscar por nombre...')" class="max-w-sm" />

        <div class="flex items-center gap-3">
            <flux:field>
                <flux:select wire:model.live="perPage">
                    @foreach ($perPageOptions as $option)
                        <flux:select.option value="{{ $option }}">{{ $option }} {{ __('por página') }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>

            @can('permisos.crear')
                <flux:modal.trigger name="permission-form">
                    <flux:button variant="primary" wire:click="create" icon="plus">{{ __('Crear permiso') }}</flux:button>
                </flux:modal.trigger>
            @endcan
        </div>
    </div>

    {{-- Tabla de permisos --}}
    <x-table>
        <thead class="bg-neutral-50 dark:bg-neutral-800">
            <tr>
                <x-table.heading>{{ __('ID') }}</x-table.heading>
                <x-table.heading>{{ __('Nombre') }}</x-table.heading>
                <x-table.heading>{{ __('Guard') }}</x-table.heading>
                <x-table.heading>{{ __('Asignado a') }}</x-table.heading>
                <x-table.heading class="text-end">{{ __('Acciones') }}</x-table.heading>
            </tr>
        </thead>
        <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
            @forelse ($this->permissions as $permission)
                <x-table.row wire:key="permission-row-{{ $permission->id }}">
                    <x-table.cell>
                        <flux:text class="text-neutral-500">{{ $permission->id }}</flux:text>
                    </x-table.cell>
                    <x-table.cell>
                        <flux:heading size="sm">{{ $permission->name }}</flux:heading>
                    </x-table.cell>
                    <x-table.cell>
                        <flux:text>{{ $permission->guard_name }}</flux:text>
                    </x-table.cell>
                    <x-table.cell>
                        <div class="flex flex-wrap gap-1">
                            @forelse ($permission->roles as $role)
                                <flux:badge color="zinc">{{ $role->name }}</flux:badge>
                            @empty
                                <flux:badge color="red">{{ __('Sin asignar') }}</flux:badge>
                            @endforelse
                        </div>
                    </x-table.cell>
                    <x-table.cell class="text-end">
                        <div class="flex justify-end gap-2">
                            @can('permisos.actualizar')
                                <flux:modal.trigger name="permission-form">
                                    <flux:button variant="ghost" size="sm" wire:click="edit({{ $permission->id }})" icon="pencil">{{ __('Editar') }}</flux:button>
                                </flux:modal.trigger>
                            @endcan

                            @can('permisos.eliminar')
                                <flux:button variant="danger" size="sm" wire:click="delete({{ $permission->id }})" :disabled="$permission->name === 'admin.acceder'" icon="trash">
                                    {{ __('Eliminar') }}
                                </flux:button>
                            @endcan
                        </div>
                    </x-table.cell>
                </x-table.row>
            @empty
                <tr>
                    <x-table.cell colspan="5" class="py-6 text-center text-neutral-500">
                        {{ $search ? __('No se encontraron resultados.') : __('No hay permisos disponibles.') }}
                    </x-table.cell>
                </tr>
            @endforelse
        </tbody>
    </x-table>

    {{-- Pagination footer --}}
    <div class="flex items-center justify-between">
        <flux:text class="text-neutral-500">
            {{ __('Mostrando :first - :last de :total', [
                'first' => $this->permissions->firstItem() ?? 0,
                'last' => $this->permissions->lastItem() ?? 0,
                'total' => $this->permissions->total(),
            ]) }}
        </flux:text>

        @if ($this->permissions->hasPages())
            {{ $this->permissions->links() }}
        @endif
    </div>

    {{-- Modal crear / editar --}}
    <flux:modal name="permission-form" class="w-full md:w-[36rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingPermissionId ? __('Editar permiso') : __('Crear permiso') }}</flux:heading>
                <flux:text class="text-neutral-600 dark:text-neutral-300">
                    {{ __('Usá nombres claros para que las reglas de acceso sigan siendo legibles.') }}
                </flux:text>
            </div>

            <form wire:submit="save" class="space-y-5">
                <flux:input wire:model="name" :label="__('Nombre del permiso')" type="text" required />

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cancelar') }}</flux:button>
                    </flux:modal.close>
                    <flux:button variant="primary" type="submit">
                        {{ $editingPermissionId ? __('Actualizar permiso') : __('Crear permiso') }}
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</section>
