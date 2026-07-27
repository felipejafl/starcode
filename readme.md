# Startcode

Aplicación web basada en el starter kit oficial de Laravel para Livewire. Incluye autenticación, gestión administrativa de acceso y trazabilidad de acciones relevantes.

## Inicio rápido

```bash
composer run setup
composer run dev
```

`composer run setup` instala dependencias, crea `.env` desde el ejemplo si falta, genera la clave de la aplicación, ejecuta migraciones, instala dependencias de Node y compila los assets. `composer run dev` inicia el servidor de Laravel, el listener de colas y Vite de forma concurrente.

Comandos disponibles:

```bash
npm run dev
npm run build
composer test
composer run lint
composer run lint:check
```

## Stack

| Área | Tecnología |
| --- | --- |
| Backend | PHP 8.4 (entorno declarado); `composer.json` admite PHP `^8.3`; Laravel 13 |
| Autenticación | Laravel Fortify |
| Interfaz | Livewire 4, Flux UI 2, Blade |
| Estilos y assets | Tailwind CSS 4, Vite 8 |
| Autorización | Spatie Laravel Permission |
| Auditoría | Spatie Laravel Activitylog |
| Pruebas | Pest 4 con SQLite en memoria |

## Módulos principales

- Autenticación mediante email: inicio de sesión, restablecimiento de contraseña, verificación de email y autenticación de dos factores.
- Ajustes de perfil, apariencia y seguridad para usuarios autenticados.
- Administración de usuarios: búsqueda, paginación, alta, edición, eliminación y asignación de roles.
- Administración de roles y permisos, con asignación de permisos a roles.
- Consulta de auditoría con búsqueda, filtros por fecha y tipo, detalles y paginación.

## Estructura

| Directorio o archivo | Responsabilidad |
| --- | --- |
| `app/Models` | Modelos `User`, `Role` y `Permission`; los tres registran actividad. |
| `app/Listeners` | Listeners de eventos de autenticación, 2FA y asignación o retiro de roles y permisos. |
| `app/Providers` | Configuración de Fortify, limitadores y listeners de auditoría. |
| `routes/web.php`, `routes/settings.php`, `routes/admin.php` | Rutas públicas, de configuración y administrativas. |
| `resources/views/pages` | Vistas Blade y componentes Livewire de autenticación, ajustes y administración. |
| `database/migrations` | Esquema de usuarios, permisos, colas, caché y `activity_log`. |
| `database/seeders` | Roles, permisos y un administrador inicial para desarrollo local. |
| `tests/Feature`, `tests/Unit` | Cobertura de autenticación, ajustes, administración, auditoría e infraestructura. |

## Autenticación y autorización

- Fortify usa el guard `web` y el email como identificador.
- El inicio de sesión y el desafío 2FA están limitados a cinco intentos por minuto.
- El registro público está deshabilitado: los usuarios se crean desde el módulo administrativo por quien tenga `usuarios.crear`.
- Las rutas administrativas requieren usuario autenticado, email verificado y `admin.acceder`; cada sección exige además su permiso de lectura específico.
- Las acciones de crear, actualizar y eliminar verifican permisos propios por módulo.
- Los usuarios reciben acceso a través de roles; el seeder crea el rol `Super Administrador`, al que el `Gate::before` concede autorización global.
- La funcionalidad de equipos de Spatie Permission está desactivada.

## Auditoría

- Se registran cambios de los modelos de usuarios, roles y permisos, sin incluir contraseñas, secretos 2FA ni tokens de recuerdo.
- Se escuchan eventos de login, login fallido, logout, bloqueo, verificación de email, activación o desactivación de 2FA, y cambios de roles o permisos.
- Los registros se conservan durante 90 días. El comando `activitylog:clean` está programado semanalmente.

## Pruebas

`composer test` limpia la configuración, verifica formato con Pint y ejecuta la suite de Laravel. La configuración de pruebas usa SQLite en memoria y aplica `LazilyRefreshDatabase` a las pruebas Feature.

## Notas actuales

- El seeder crea `admin@test.local` con la contraseña `password` y le asigna el rol `Super Administrador`; esta cuenta es solo un punto de partida local.
- La ejecución de la limpieza semanal de auditoría requiere que el scheduler de Laravel esté activo en el entorno donde se despliegue la aplicación.
