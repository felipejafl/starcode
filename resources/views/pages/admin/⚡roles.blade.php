<?php

use App\Models\Permission;
use App\Models\Role;
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

new #[Title('Roles')] class extends Component {
    use WithPagination;

    public string $search = '';

    #[Url]
    public int $perPage = 10;

    public ?int $editingRoleId = null;
    public string $name = '';

    /**
     * @var array<int, string>
     */
    public array $selectedPermissions = [];

    /**
     * @var array<int, int>
     */
    public array $perPageOptions = [10, 20, 50, 100];

    /**
     * Autoriza el acceso inicial al listado de roles.
     *
     * Livewire lo ejecuta al montar la ruta admin.roles definida en routes/admin.php; aborta con
     * 403 si el usuario autenticado no conserva el permiso de consulta.
     */
    public function mount(): void
    {
        abort_unless(Auth::user()->can('roles.ver'), 403);
    }

    /**
     * Define las reglas del formulario de alta y edición de roles.
     *
     * Livewire las consume durante save(); excluye el rol editado de la unicidad y valida los
     * permisos antes de sincronizarlos.
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
                Rule::unique('roles', 'name')->ignore($this->editingRoleId),
            ],
            'selectedPermissions' => ['array'],
            'selectedPermissions.*' => ['string', 'exists:permissions,name'],
        ];
    }

    #[Computed]
    /**
     * Obtiene la página de roles con sus permisos para la tabla administrativa.
     *
     * La vista consume $this->roles; precarga permisos para evitar consultas por fila.
     */
    public function roles(): LengthAwarePaginator
    {
        return Role::query()
            ->with('permissions')
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->orderBy('name')
            ->paginate($this->perPage)
            ->withQueryString();
    }

    #[Computed]
    /**
     * Obtiene los permisos disponibles para el formulario de roles.
     *
     * La vista consume $this->permissions al renderizar las casillas de selección.
     */
    public function permissions(): \Illuminate\Database\Eloquent\Collection
    {
        return Permission::query()
            ->orderBy('name')
            ->get();
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
     * Prepara un formulario vacío para crear un rol.
     *
     * Lo invoca wire:click desde el botón de creación y verifica el permiso correspondiente.
     */
    public function create(): void
    {
        $this->authorizeAbility('roles.crear');
        $this->resetForm();
    }

    /**
     * Carga un rol y sus permisos en el formulario de edición.
     *
     * Lo invoca wire:click desde cada fila; elimina errores previos después de cargar el estado.
     */
    public function edit(int $roleId): void
    {
        $this->authorizeAbility('roles.actualizar');

        $role = Role::query()->with('permissions')->findOrFail($roleId);

        $this->editingRoleId = $role->id;
        $this->name = $role->name;
        $this->selectedPermissions = $role->permissions->pluck('name')->all();

        $this->resetErrorBag();
    }

    /**
     * Crea o actualiza un rol y sincroniza sus permisos.
     *
     * Lo invoca wire:submit; invalida la caché de Spatie Permission para que las autorizaciones
     * posteriores observen inmediatamente la nueva asignación.
     */
    public function save(): void
    {
        $isCreating = $this->editingRoleId === null;

        $this->authorizeAbility($isCreating ? 'roles.crear' : 'roles.actualizar');

        $validated = $this->validate();

        $role = $isCreating
            ? Role::create(['name' => $validated['name'], 'guard_name' => 'web'])
            : tap(Role::query()->findOrFail($this->editingRoleId), function (Role $role) use ($validated): void {
                $role->update([
                    'name' => $validated['name'],
                ]);
            });

        $role->syncPermissions($validated['selectedPermissions'] ?? []);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->resetForm();

        Flux::modal('role-form')->close();

        Flux::toast(variant: 'success', text: $isCreating
            ? __('Rol creado.')
            : __('Rol actualizado.'));
    }

    /**
     * Elimina un rol que no sea el rol administrativo principal.
     *
     * Lo invoca wire:click desde la tabla; bloquea la eliminación de Super Administrador e invalida
     * la caché de permisos tras una baja válida.
     */
    public function delete(int $roleId): void
    {
        $this->authorizeAbility('roles.eliminar');

        $role = Role::query()->findOrFail($roleId);

        if ($role->name === 'Super Administrador') {
            throw ValidationException::withMessages([
                'general' => __('El rol Super Administrador no se puede eliminar.'),
            ]);
        }

        $role->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        if ($this->editingRoleId === $roleId) {
            $this->resetForm();
        }

        Flux::toast(variant: 'success', text: __('Rol eliminado.'));
    }

    /**
     * Descarta el estado del formulario y cierra el modal de roles.
     *
     * Lo invoca la interacción de cancelación de la vista y no realiza cambios persistentes.
     */
    public function cancel(): void
    {
        $this->resetForm();
        Flux::modal('role-form')->close();
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
     * Restablece el estado transitorio y los errores del formulario de roles.
     *
     * Lo invocan las acciones de creación, guardado, eliminación y cancelación.
     */
    private function resetForm(): void
    {
        $this->reset([
            'editingRoleId',
            'name',
            'selectedPermissions',
        ]);

        $this->resetErrorBag();
    }
}; ?>

<section class="space-y-6">
    <x-page-heading :heading="__('Roles')" :subheading="__('Agrupá permisos en roles reutilizables para que los usuarios hereden acceso de forma consistente.')" />

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

            @can('roles.crear')
                <flux:modal.trigger name="role-form">
                    <flux:button variant="primary" wire:click="create" icon="plus">{{ __('Crear rol') }}</flux:button>
                </flux:modal.trigger>
            @endcan
        </div>
    </div>

    {{-- Tabla de roles --}}
    <x-table>
        <thead class="bg-neutral-50 dark:bg-neutral-800">
            <tr>
                <x-table.heading>{{ __('ID') }}</x-table.heading>
                <x-table.heading>{{ __('Nombre') }}</x-table.heading>
                <x-table.heading>{{ __('Permisos') }}</x-table.heading>
                <x-table.heading class="text-end">{{ __('Acciones') }}</x-table.heading>
            </tr>
        </thead>
        <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
            @forelse ($this->roles as $role)
                <x-table.row wire:key="role-row-{{ $role->id }}">
                    <x-table.cell>
                        <flux:text class="text-neutral-500">{{ $role->id }}</flux:text>
                    </x-table.cell>
                    <x-table.cell>
                        <flux:heading size="sm">{{ $role->name }}</flux:heading>
                    </x-table.cell>
                    <x-table.cell>
                        <div class="flex flex-wrap gap-1">
                            @forelse ($role->permissions as $permission)
                                <flux:badge color="zinc">{{ $permission->name }}</flux:badge>
                            @empty
                                <flux:badge color="red">{{ __('Sin permisos') }}</flux:badge>
                            @endforelse
                        </div>
                    </x-table.cell>
                    <x-table.cell class="text-end">
                        <div class="flex justify-end gap-2">
                            @can('roles.actualizar')
                                <flux:modal.trigger name="role-form">
                                    <flux:button variant="ghost" size="sm" wire:click="edit({{ $role->id }})" icon="pencil">{{ __('Editar') }}</flux:button>
                                </flux:modal.trigger>
                            @endcan

                            @can('roles.eliminar')
                                <flux:button variant="danger" size="sm" wire:click="delete({{ $role->id }})" :disabled="$role->name === 'Super Administrador'" icon="trash">
                                    {{ __('Eliminar') }}
                                </flux:button>
                            @endcan
                        </div>
                    </x-table.cell>
                </x-table.row>
            @empty
                <tr>
                    <x-table.cell colspan="4" class="py-6 text-center text-neutral-500">
                        {{ $search ? __('No se encontraron resultados.') : __('No hay roles disponibles.') }}
                    </x-table.cell>
                </tr>
            @endforelse
        </tbody>
    </x-table>

    {{-- Pagination footer --}}
    <div class="flex items-center justify-between">
        <flux:text class="text-neutral-500">
            {{ __('Mostrando :first - :last de :total', [
                'first' => $this->roles->firstItem() ?? 0,
                'last' => $this->roles->lastItem() ?? 0,
                'total' => $this->roles->total(),
            ]) }}
        </flux:text>

        @if ($this->roles->hasPages())
            {{ $this->roles->links() }}
        @endif
    </div>

    {{-- Modal crear / editar --}}
    <flux:modal name="role-form" class="w-full md:w-[36rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingRoleId ? __('Editar rol') : __('Crear rol') }}</flux:heading>
                <flux:text class="text-neutral-600 dark:text-neutral-300">
                    {{ __('Asigná permisos a cada rol y reutilizalos entre usuarios.') }}
                </flux:text>
            </div>

            <form wire:submit="save" class="space-y-5">
                <flux:input wire:model="name" :label="__('Nombre del rol')" type="text" required />

                <div class="space-y-3">
                    <flux:heading size="sm">{{ __('Permisos') }}</flux:heading>

                    <div class="grid gap-3">
                        @foreach ($this->permissions as $permission)
                            <label wire:key="modal-role-permission-{{ $permission->id }}" class="flex items-center gap-3 rounded-lg border border-neutral-200 px-3 py-2 dark:border-neutral-700">
                                <flux:checkbox wire:model="selectedPermissions" value="{{ $permission->name }}" />
                                <div class="flex flex-col">
                                    <span class="text-sm text-neutral-700 dark:text-neutral-200">{{ $permission->name }}</span>
                                    <span class="text-xs text-neutral-500 dark:text-neutral-400">{{ __('Guard') }}: {{ $permission->guard_name }}</span>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cancelar') }}</flux:button>
                    </flux:modal.close>
                    <flux:button variant="primary" type="submit">
                        {{ $editingRoleId ? __('Actualizar rol') : __('Crear rol') }}
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</section>
