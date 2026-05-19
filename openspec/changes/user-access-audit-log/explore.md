## Exploration: user-access-audit-log

### Current State

El proyecto tiene un sistema de autenticación y autorización funcional pero básico:

**Auth (Fortify):**
- Login, registro, reset de password, verificación de email y 2FA configurados
- `CreateNewUser` y `ResetUserPassword` como acciones personalizables
- No hay logging de eventos de autenticación (logins, logouts, intentos fallidos)

**Permisos (Spatie Permission v7.4):**
- User model con trait `HasRoles` correctamente configurado
- 1 rol "Super Administrador" con 12 permisos CRUD para usuarios/roles/permisos
- Super Admin configurado vía `Gate::before()` en `AppServiceProvider`
- Eventos de Spatie **desactivados** (`permission.events_enabled = false`)
- No hay observers ni listeners para cambios en roles/permisos

**Gestión de Usuarios:**
- 3 páginas admin (usuarios, roles, permisos) implementadas como Livewire SFC
- Tests de acceso y gestión administrativa existentes
- No hay registro de quién crea/edita/elimina usuarios, roles o permisos

**Audit Log:**
- **NO EXISTE** ningún mecanismo de auditoría
- No hay tabla `activity_log` ni modelo `Activity`
- No hay observers, events o listeners para tracking de acciones
- No hay UI para visualizar historial de actividades

### Affected Areas

- `app/Models/User.php` — Agregar trait para logging de actividad
- `app/Actions/Fortify/*.php` — Loguear eventos de auth
- `resources/views/pages/admin/*.blade.php` — Loguear acciones CRUD
- `config/permission.php` — Habilitar eventos de Spatie (`events_enabled => true`)
- `app/Observers/` — Nueva carpeta para observers de modelos
- `app/Listeners/` — Nueva carpeta para listeners de eventos
- `app/Providers/EventServiceProvider.php` — Registrar listeners (si no existe, crear en `AppServiceProvider`)
- `database/migrations/` — Migración para tabla `activity_log`
- `resources/views/pages/` — Nueva vista para visualizar audit log
- `routes/admin.php` — Ruta para página de audit log
- `tests/Feature/Admin/` — Tests para auditoría

### Approaches

#### 1. **Spatie Activitylog Package** (Recomendado)

Package maduro de Spatie específicamente para audit logging.

**Pros:**
- Ampliamente adoptado (14k+ estrellas, mantenido activamente)
- Automático con trait `LogsActivity` en modelos
- Logs cambios de atributos (old/new values)
- Soporta causer, subject, properties custom
- API simple: `activity()->log('descripción')`
- Comandos de limpieza automática
- Filtrado por log name, date range, subject, causer
- Compatible con Spatie Permission (eventos integrables)

**Contras:**
- Dependencia externa adicional
- Requiere migración y configuración inicial
- Curva de aprendizaje mínima para configuraciones avanzadas

**Esfuerzo:** **Medio** (2-3 sesiones)
- Instalación y configuración: 1 sesión
- Implementación en modelos y acciones: 1-2 sesiones
- UI de visualización: 1 sesión

#### 2. **Eloquent Observers + Logging Nativo**

Usar observers de Eloquent con la facada `Log` de Laravel.

**Pros:**
- Sin dependencias externas
- Control total sobre qué y cómo se loguea
- Integración nativa con sistema de logging de Laravel

**Contras:**
- Más código boilerplate (observers para cada modelo)
- No tiene estructura de queries incorporada
- Hay que diseñar propio esquema de almacenamiento
- Menos features out-of-the-box (limpieza, filtrado, etc.)

**Esfuerzo:** **Alto** (4-5 sesiones)
- Diseñar esquema de BD: 1 sesión
- Implementar observers: 2 sesiones
- Crear queries/filtros custom: 1 sesión
- UI de visualización: 1 sesión

#### 3. **Event System + Listeners**

Aprovechar eventos de Spatie Permission y Fortify con listeners.

**Pros:**
- Desacoplado (events/listeners)
- Spatie Permission ya tiene eventos (RoleAttached, PermissionDetached, etc.)
- Escalable para futuras integraciones

**Contras:**
- Requiere habilitar eventos de Spatie (`events_enabled`)
- No cubre acciones manuales en Livewire (CRUD de usuarios)
- Necesita combinación con observers para modelos sin eventos

**Esfuerzo:** **Medio-Alto** (3-4 sesiones)
- Habilitar y configurar eventos: 0.5 sesiones
- Crear listeners: 1-2 sesiones
- Observers complementarios: 1 sesión
- UI de visualización: 1 sesión

#### 4. **Híbrido: Spatie Activitylog + Events**

Combinar lo mejor de ambos: Activitylog para modelos, events para permisos.

**Pros:**
- Máxima cobertura (modelos + eventos de Spatie)
- Actividad automática en modelos con `LogsActivity`
- Events de Spatie para cambios de roles/permisos
- Flexible y escalable

**Contras:**
- Más complejo de configurar inicialmente
- Dos mecanismos que mantener

**Esfuerzo:** **Medio** (3 sesiones)
- Activitylog base: 1 sesión
- Listeners para eventos Spatie: 1 sesión
- UI y tests: 1 sesión

### Recommendation

**Opción 4: Híbrido (Spatie Activitylog + Events de Spatie)**

**Por qué:**
1. **Cobertura completa**: Activitylog cubre modelos (User, Role, Permission) y events cubren cambios de relaciones (assignRole, syncPermissions, etc.)
2. **Menos código custom**: El trait `LogsActivity` hace el 80% del trabajo automáticamente
3. **Futuro-proof**: Events permiten extender a otras partes del sistema fácilmente
4. **Consistente con el stack**: Ya usás Spatie Permission, tiene sentido usar Activitylog del mismo ecosystem
5. **Query-ready**: La tabla `activity_log` viene con índices y estructura para filtrar por causer, subject, date range

**Alcance del Audit Log:**
- **Auth**: logins, logouts, intentos fallidos, registro de usuarios, password resets
- **Usuarios**: creación, edición, eliminación, asignación/remoción de roles
- **Roles**: creación, edición, eliminación, sync de permisos
- **Permisos**: creación, edición, eliminación
- **Propiedades a loguear**: causer (quién hizo), subject (sobre qué), properties (IP, user agent, cambios de atributos)

**Estructura sugerida:**
```
app/
├── Observers/
│   ├── UserObserver.php
│   ├── RoleObserver.php
│   └── PermissionObserver.php
├── Listeners/
│   ├── LogRoleAttached.php
│   ├── LogRoleDetached.php
│   ├── LogPermissionAttached.php
│   └── LogPermissionDetached.php
└── Models/
    └── User.php (agregar LogsActivity trait)
```

### Risks

1. **Performance en alta concurrencia**: Logging en cada acción puede impactar performance si no se queuea. **Mitigación**: Usar `ShouldQueue` en listeners y configurar cola asíncrona.

2. **Datos sensibles en logs**: Passwords o tokens podrían loguearse accidentalmente. **Mitigación**: Configurar `default_except_attributes` con `['password', 'two_factor_secret', 'remember_token']`.

3. **Crecimiento ilimitado de la tabla**: La tabla `activity_log` puede crecer indefinidamente. **Mitigación**: Configurar `clean_after_days` y programar comando de limpieza semanal.

4. **Breaking changes en tests**: Tests existentes podrían fallar si esperan cierto estado sin actividades. **Mitigación**: Actualizar tests para ignorar actividad o usar `RefreshDatabase`.

5. **Eventos de Spatie no disparados**: Si hay código que hace `DB::table('model_has_roles')->insert()` directo, no se disparan eventos. **Mitigación**: Auditar código existente y asegurar que siempre use métodos del package (`assignRole`, `syncRoles`, etc.).

### Ready for Proposal

**Sí, listo para proposal.**

**Lo que el orchestrator debe comunicarle al usuario:**

1. **Problemas detectados en gestión actual:**
   - No hay audit trail de ninguna acción (quién hizo qué y cuándo)
   - Eventos de Spatie Permission desactivados (no se puede trackear cambios en roles/permisos)
   - No hay logging de eventos de autenticación (logins, logouts, intentos fallidos)

2. **Funcionalidad propuesta:**
   - Audit log completo que registre TODAS las acciones de usuarios
   - Implementación híbrida: Spatie Activitylog + Events de Spatie Permission
   - UI de visualización con filtros por usuario, fecha, tipo de acción
   - Limpieza automática de logs antiguos configurable

3. **Alcance del change:**
   - Instalar `spatie/laravel-activitylog`
   - Crear observers para User, Role, Permission
   - Crear listeners para eventos de Spatie (RoleAttached, PermissionDetached, etc.)
   - Habilitar eventos en `config/permission.php`
   - Agregar logging en acciones de Fortify (login, logout, registro)
   - Crear página admin para visualizar audit log con filtros
   - Agregar tests de cobertura

4. **Riesgos principales:**
   - Performance (mitigar con colas)
   - Datos sensibles en logs (mitigar con exclusión de atributos)
   - Crecimiento de BD (mitigar con limpieza programada)

5. **Próximos pasos si el usuario aprueba:**
   - Crear proposal formal con specs detallados
   - Diseñar arquitectura con diagrama de secuencia
   - Breakdown en tasks implementables por sesión
