# CRUD standard for this project

This file defines the STANDARD for creating and reviewing CRUDs in this repository.

It is based on the REAL patterns already used in the project:
- Laravel 13
- Livewire 4
- Flux UI 2
- Tailwind CSS 4
- Pest 4
- Spatie Permission

Use this as the default guide before creating a new CRUD for {ModuloName}.

## Quick path

1. Create the model, migration, factory, seeder, and tests.
2. Create the Livewire full-page {ModuloName} component.
3. Register permissions in the seeder.
4. Register the route in `routes/moduloname.php` dependiendo del modulo que sea.
5. Add the sidebar item.
6. Build the page with the standard toolbar, table, modal, and toasts.
7. Add authorization, validation, and business protections.
8. Write Pest tests for access, create, update, and delete.
9. Run targeted tests.
10. Run Pint.

## First rule

This project DOES NOT use classic controller-based CRUD as the main {ModuloName} pattern.

The standard here is:
- **{ModuloName} CRUDs = Livewire full-page SFCs** in `resources/views/pages/{ModuloName}/`
- **routes = `Route::livewire()`** in `routes/{ModuloName}.php`
- **UI = Flux + Blade components**
- **permissions = Spatie Permission**

Controllers are only needed when the feature truly requires them, for example:
- API endpoints
- non-Livewire backend flows
- integrations/webhooks
- actions that should not live in the page component

## Standard artisan commands

## 1) Base model layer

Create the model with migration, factory, and seeder support:

```bash
php artisan make:model Product -mf --no-interaction
php artisan make:seeder ProductSeeder --no-interaction
```

If the CRUD needs a policy:

```bash
php artisan make:policy ProductPolicy --model=Product --no-interaction
```

If the CRUD really needs a controller too:

```bash
php artisan make:controller {ModuloName}/ProductController --resource --model=Product --no-interaction
```

If the CRUD needs a Form Request:

```bash
php artisan make:request StoreProductRequest --no-interaction
php artisan make:request UpdateProductRequest --no-interaction
```

## 2) Livewire page

For this project, create the  CRUD page as a full-page Livewire SFC:

```bash
php artisan make:livewire pages::madulname.products --no-interaction
```

Expected file:

```text
resources/views/pages/{ModuloName}/⚡products.blade.php
```

## 3) Tests

Create a Pest feature test:

```bash
php artisan make:test --pest ProductCrudTest --no-interaction
```

Typical {ModuloName} CRUD tests usually belong in:

```text
tests/Feature/{ModuloName}/
```

## Standard file structure

| Purpose | Standard path |
|---|---|
| Model | `app/Models/Product.php` |
| Migration | `database/migrations/*_create_products_table.php` |
| Factory | `database/factories/ProductFactory.php` |
| Seeder | `database/seeders/ProductSeeder.php` |
| Modul Name page | `resources/views/pages/{ModuloName}/⚡products.blade.php` |
| Modul Name route | `routes/{ModuloName}.php` |
| Sidebar link | `resources/views/layouts/app/sidebar.blade.php` |
| Feature tests | `tests/Feature/{ModuloName}/ProductCrudTest.php` |

## Model standard

Every CRUD model should follow Laravel conventions and be explicit about mass assignment.

Minimum example:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'price',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
```

## Migration standard

- Use `foreignId()->constrained()` when relationships exist.
- Add indexes when the CRUD filters or searches by that column.
- Do not mix schema changes with data migration.

Example:

```php
Schema::create('products', function (Blueprint $table): void {
    $table->id();
    $table->string('name')->index();
    $table->text('description')->nullable();
    $table->decimal('price', 10, 2);
    $table->boolean('is_active')->default(true)->index();
    $table->timestamps();
});
```

## Permission standard

This project uses **Spatie Permission** with permission names in this format:

```text
{resource}.ver
{resource}.crear
{resource}.actualizar
{resource}.eliminar
```

Examples already used:
- `usuarios.ver`
- `roles.crear`
- `permisos.actualizar`
- `auditoria.ver`

When a CRUD is added, update `database/seeders/RolesAndPermissionsSeeder.php`.

Example:

```php
Permission::findOrCreate('products.ver', 'web');
Permission::findOrCreate('products.crear', 'web');
Permission::findOrCreate('products.actualizar', 'web');
Permission::findOrCreate('products.eliminar', 'web');
```

If the CRUD changes roles or permissions directly, ALWAYS clear cache:

```php
app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
```

## Route standard

Modul name CRUD routes go in `routes/{ModuloName}.php`.

Pattern:

```php
Route::livewire('products', 'pages::{ModuloName}.products')
    ->middleware('can:products.ver')
    ->name('products.index');
```

Expected surrounding group:

```php
Route::middleware(['auth', 'verified', 'can:{ModuloName}.acceder'])
    ->prefix('{ModuloName}')
    ->name('{ModuloName}.')
    ->group(function (): void {
        // CRUD routes here
    });
```

## Sidebar standard

Every {ModuloName} CRUD should appear in the {ModuloName} sidebar when appropriate.

Pattern:

```blade
@can('products.ver')
    <flux:sidebar.item
        icon="cube"
        :href="route('{ModuloName}.products.index')"
        :current="request()->routeIs('{ModuloName}.products.*')"
        wire:navigate
    >
        {{ __('Productos') }}
    </flux:sidebar.item>
@endcan
```

Rules:
- Use a real Heroicon name.
- Keep naming aligned with existing {ModuloName} items.
- Gate the item with `@can()`.

## Livewire page standard

This is the main project pattern.

## Component skeleton

```php
<?php

use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use App\Models\Product;

new #[Title('Productos')] class extends Component {
    public string $search = '';
    public ?int $editingProductId = null;
    public string $name = '';
    public ?string $description = null;
    public string $price = '';
    public bool $is_active = true;

    public function mount(): void
    {
        abort_unless(Auth::user()->can('products.ver'), 403);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }

    #[Computed]
    public function products(): Collection
    {
        return Product::query()
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->orderBy('name')
            ->get();
    }

    public function create(): void
    {
        $this->authorizeAbility('products.crear');
        $this->resetForm();
    }

    public function edit(int $productId): void
    {
        $this->authorizeAbility('products.actualizar');

        $product = Product::query()->findOrFail($productId);

        $this->editingProductId = $product->id;
        $this->name = $product->name;
        $this->description = $product->description;
        $this->price = (string) $product->price;
        $this->is_active = $product->is_active;

        $this->resetErrorBag();
    }

    public function save(): void
    {
        $isCreating = $this->editingProductId === null;

        $this->authorizeAbility($isCreating ? 'products.crear' : 'products.actualizar');

        $validated = $this->validate();

        if ($isCreating) {
            Product::create($validated);
        } else {
            Product::query()->findOrFail($this->editingProductId)?->update($validated);
        }

        $this->resetForm();
        Flux::modal('product-form')->close();
        Flux::toast(variant: 'success', text: $isCreating ? __('Producto creado.') : __('Producto actualizado.'));
    }

    public function delete(int $productId): void
    {
        $this->authorizeAbility('products.eliminar');

        Product::query()->findOrFail($productId)->delete();

        if ($this->editingProductId === $productId) {
            $this->resetForm();
        }

        Flux::toast(variant: 'success', text: __('Producto eliminado.'));
    }

    public function cancel(): void
    {
        $this->resetForm();
        Flux::modal('product-form')->close();
    }

    private function authorizeAbility(string $ability): void
    {
        abort_unless(Auth::user()->can($ability), 403);
    }

    private function resetForm(): void
    {
        $this->reset([
            'editingProductId',
            'name',
            'description',
            'price',
            'is_active',
        ]);

        $this->is_active = true;
        $this->resetErrorBag();
    }
};
?>
```

## View structure standard

Every {ModuloName} CRUD page should follow this order:

1. `x-page-heading`
2. optional global `flux:callout` for business errors
3. `{{-- Toolbar --}}`
4. table with `x-table`
5. modal for create/edit

## Standard Blade layout

```blade
<section class="space-y-6">
    <x-page-heading
        :heading="__('Productos')"
        :subheading="__('{ModuloName}istrá productos con una interfaz consistente del panel.')"
    />

    @if ($errors->has('general'))
        <flux:callout variant="danger" icon="x-circle" :heading="$errors->first('general')" />
    @endif

    {{-- Toolbar --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <flux:input
            wire:model.live="search"
            icon="magnifying-glass"
            :placeholder="__('Buscar por nombre...')"
            class="max-w-sm"
        />

        @can('products.crear')
            <flux:modal.trigger name="product-form">
                <flux:button variant="primary" wire:click="create" icon="plus">
                    {{ __('Crear producto') }}
                </flux:button>
            </flux:modal.trigger>
        @endcan
    </div>

    {{-- Tabla de productos --}}
    <x-table>
        <thead class="bg-neutral-50 dark:bg-neutral-800">
            <tr>
                <x-table.heading>{{ __('ID') }}</x-table.heading>
                <x-table.heading>{{ __('Nombre') }}</x-table.heading>
                <x-table.heading>{{ __('Precio') }}</x-table.heading>
                <x-table.heading>{{ __('Estado') }}</x-table.heading>
                <x-table.heading class="text-end">{{ __('Acciones') }}</x-table.heading>
            </tr>
        </thead>
        <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
            @forelse ($this->products as $product)
                <x-table.row wire:key="product-row-{{ $product->id }}">
                    <x-table.cell>
                        <flux:text class="text-neutral-500">{{ $product->id }}</flux:text>
                    </x-table.cell>
                    <x-table.cell>
                        <flux:heading size="sm">{{ $product->name }}</flux:heading>
                    </x-table.cell>
                    <x-table.cell>
                        <flux:text>{{ $product->price }}</flux:text>
                    </x-table.cell>
                    <x-table.cell>
                        <flux:badge :color="$product->is_active ? 'green' : 'zinc'">
                            {{ $product->is_active ? __('Activo') : __('Inactivo') }}
                        </flux:badge>
                    </x-table.cell>
                    <x-table.cell class="text-end">
                        <div class="flex justify-end gap-2">
                            @can('products.actualizar')
                                <flux:modal.trigger name="product-form">
                                    <flux:button variant="ghost" size="sm" wire:click="edit({{ $product->id }})" icon="pencil">
                                        {{ __('Editar') }}
                                    </flux:button>
                                </flux:modal.trigger>
                            @endcan

                            @can('products.eliminar')
                                <flux:button variant="danger" size="sm" wire:click="delete({{ $product->id }})" icon="trash">
                                    {{ __('Eliminar') }}
                                </flux:button>
                            @endcan
                        </div>
                    </x-table.cell>
                </x-table.row>
            @empty
                <tr>
                    <x-table.cell colspan="5" class="py-6 text-center text-neutral-500">
                        {{ $search ? __('No se encontraron resultados.') : __('No hay registros disponibles.') }}
                    </x-table.cell>
                </tr>
            @endforelse
        </tbody>
    </x-table>

    {{-- Modal crear / editar --}}
    <flux:modal name="product-form" class="w-full md:w-[36rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingProductId ? __('Editar producto') : __('Crear producto') }}</flux:heading>
                <flux:text class="text-neutral-600 dark:text-neutral-300">
                    {{ __('Completá la información principal del producto.') }}
                </flux:text>
            </div>

            <form wire:submit="save" class="space-y-5">
                <flux:input wire:model="name" :label="__('Nombre')" type="text" required />
                <flux:textarea wire:model="description" :label="__('Descripción')" />
                <flux:input wire:model="price" :label="__('Precio')" type="number" step="0.01" required />
                <flux:switch wire:model="is_active" :label="__('Activo')" />

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cancelar') }}</flux:button>
                    </flux:modal.close>
                    <flux:button variant="primary" type="submit">
                        {{ $editingProductId ? __('Actualizar producto') : __('Crear producto') }}
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</section>
```

## Visual component standards

Use these components by default.

| Need | Standard |
|---|---|
| Page heading | `<x-page-heading />` |
| Search | `<flux:input icon="magnifying-glass" ... />` |
| Primary action | `<flux:button variant="primary" icon="plus">` |
| Edit action | `<flux:button variant="ghost" size="sm" icon="pencil">` |
| Delete action | `<flux:button variant="danger" size="sm" icon="trash">` |
| Error state | `<flux:callout variant="danger" icon="x-circle" ... />` |
| Table | `<x-table>` + `<x-table.heading>` + `<x-table.cell>` |
| Modal | `<flux:modal>` |
| Badges | `<flux:badge>` |
| Form labels | `:label="__('...')"` or `<flux:field><flux:label>` |
| Toast feedback | `Flux::toast()` |

## UI rules

- Use `space-y-6` as the page vertical rhythm.
- Use `max-w-sm` for standard search inputs in CRUD toolbars.
- Use `flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between` for standard toolbars.
- Use `justify-end gap-2` for row actions.
- Use `w-full md:w-[36rem]` for create/edit modals unless the form truly needs more space.
- Use `wire:key` in every table row loop.
- Wrap all visible text in `__()`.

## Validation standard

- Keep rules inside the Livewire page unless the complexity justifies extraction.
- Use array syntax for rules.
- Prefer dynamic rules when create/edit differs.
- Call `$this->validate()` inside `save()`.
- After loading edit state, call `$this->resetErrorBag()`.

Example:

```php
protected function rules(): array
{
    return [
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'email'],
    ];
}
```

## Authorization standard

There are two levels:

### Page access

```php
public function mount(): void
{
    abort_unless(Auth::user()->can('products.ver'), 403);
}
```

### Action access

```php
private function authorizeAbility(string $ability): void
{
    abort_unless(Auth::user()->can($ability), 403);
}
```

Then call it in `create`, `edit`, `save`, and `delete`.

## Search, filtering and pagination standard

Base search pattern:

```php
->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
```

Rules:
- Use `wire:model.live` for search.
- Keep filtering inside the computed query.
- Use `orderBy()` explicitly.
- **All CRUDs MUST use pagination** with a per-page selector.

### Pagination pattern

Every CRUD page should paginate results with a configurable per-page selector.

**Component properties:**

```php
use Livewire\WithPagination;
use Livewire\Attributes\Url;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

// Inside the component class:
use WithPagination;

#[Url]
public int $perPage = 10;

/**
 * @var array<int, int>
 */
public array $perPageOptions = [10, 20, 50, 100];
```

**Computed method returning paginator:**

```php
#[Computed]
public function items(): LengthAwarePaginator
{
    return Model::query()
        ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
        ->orderBy('name')
        ->paginate($this->perPage);
}
```

**Reset page on filter change:**

```php
public function updating(string $name): void
{
    if ($name === 'perPage' || $name === 'search') {
        $this->resetPage();
    }
}
```

**Toolbar with per-page selector:**

```blade
<div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <flux:input wire:model.live="search" icon="magnifying-glass" :placeholder="__('Buscar...')" class="max-w-sm" />

    <div class="flex items-center gap-3">
        <flux:field>
            <flux:select wire:model.live="perPage">
                @foreach ($perPageOptions as $option)
                    <flux:select.option value="{{ $option }}">{{ $option }} {{ __('por página') }}</flux:select.option>
                @endforeach
            </flux:select>
        </flux:field>

        @can('resource.crear')
            <flux:modal.trigger name="resource-form">
                <flux:button variant="primary" wire:click="create" icon="plus">{{ __('Crear recurso') }}</flux:button>
            </flux:modal.trigger>
        @endcan
    </div>
</div>
```

**Pagination footer:**

Keep the summary visible even when the current dataset fits on one page. Hide only the navigation links when there are no extra pages.

```blade
<div class="flex items-center justify-between">
    <flux:text class="text-neutral-500">
        {{ __('Mostrando :first - :last de :total', [
            'first' => $this->items->firstItem() ?? 0,
            'last' => $this->items->lastItem() ?? 0,
            'total' => $this->items->total(),
        ]) }}
    </flux:text>

    @if ($this->items->hasPages())
        {{ $this->items->links() }}
    @endif
</div>
```

## Business protection standard

Every CRUD must define domain protections explicitly.

Existing project examples:
- users: do not allow deleting your own {ModuloName} account
- roles: do not allow deleting `Super {ModuloName}istrador`
- permissions: protect critical permissions when needed

Pattern:

```php
throw ValidationException::withMessages([
    'general' => __('Mensaje de negocio.'),
]);
```

And show it with:

```blade
@if ($errors->has('general'))
    <flux:callout variant="danger" icon="x-circle" :heading="$errors->first('general')" />
@endif
```

## Seeder standard

If the CRUD introduces data required by the {ModuloName}, seed it.

Rules:
- use `findOrCreate()` for roles and permissions
- keep `guard_name` as `web`
- if touching roles/permissions, clear cache afterward

## Testing standard

Every CRUD change must be tested.

Minimum CRUD test coverage:

- access allowed with proper permission
- access denied without permission
- create works
- update works
- delete works
- business protections work
- search/filter works if present

## Pest example

```php
<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
});

test('super {ModuloName} can create a product', function (): void {
    $user = User::factory()->create();
    $user->assignRole('Super {ModuloName}istrador');

    $this->actingAs($user);

    Livewire::test('pages::{ModuloName}.products')
        ->call('create')
        ->set('name', 'Demo product')
        ->set('price', '10.50')
        ->call('save')
        ->assertHasNoErrors();

    expect(\App\Models\Product::query()->where('name', 'Demo product')->exists())->toBeTrue();
});
```

## Verification commands

Run the minimum relevant checks first:

```bash
php artisan test --compact tests/Feature/{ModuloName}/ProductCrudTest.php
vendor/bin/pint --dirty --format agent
```

If the change touches broader {ModuloName} behavior, run the focused {ModuloName} suite too.

## CRUD checklist

Use this before considering the work done.

- [ ] Model created with fillable/casts
- [ ] Migration created with proper indexes and constraints
- [ ] Factory available
- [ ] Seeder updated if needed
- [ ] Permissions created in `RolesAndPermissionsSeeder`
- [ ] {ModuloName} route registered in `routes/{ModuloName}.php`
- [ ] Sidebar item added
- [ ] Livewire full-page SFC created in `resources/views/pages/{ModuloName}/`
- [ ] `mount()` authorization added
- [ ] `create`, `edit`, `save`, `delete`, `cancel`, `resetForm` implemented
- [ ] Search implemented if needed
- [ ] Table uses `x-table`
- [ ] Modal uses Flux standard
- [ ] Success toasts added
- [ ] Business protections added
- [ ] Tests written with Pest
- [ ] Targeted tests passed
- [ ] Pint passed

## Common gotchas in this project

| Gotcha | Why it matters |
|---|---|
| Forgetting `@can()` in sidebar | Exposes links users should not see |
| Forgetting page `mount()` authorization | Users may load the page directly |
| Forgetting `authorizeAbility()` in actions | UI may render but action stays unprotected |
| Forgetting `resetErrorBag()` | Modal can reopen with stale validation errors |
| Forgetting `PermissionRegistrar::forgetCachedPermissions()` | Roles/permissions UI behaves inconsistently |
| Forgetting `wire:key` in rows | Livewire updates can become unstable |
| Using a different toolbar layout | Breaks visual consistency across {ModuloName} CRUDs |
| Mixing raw HTML buttons where Flux already exists | Creates inconsistent interaction patterns |
| Adding controllers for {ModuloName} CRUD without need | Fights the current project architecture |

## When to use a controller anyway

Use a controller only if the CRUD needs one of these:
- API resource endpoints
- file downloads/uploads with dedicated HTTP flow
- webhook or callback endpoints
- logic that should be shared outside the Livewire page

If that happens, keep the {ModuloName} UI in Livewire and let the controller handle the external HTTP concern.

## Final decision

For THIS project, the standard {ModuloName} CRUD is:

**Model + Migration + Factory + Seeder + Permissions + Livewire Full-Page SFC + {ModuloName} Route + Sidebar Item + Pest tests + Flux UI**

That is the path to follow unless there is a strong architectural reason to do otherwise.
