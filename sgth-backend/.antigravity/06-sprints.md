# SGTH — Sistema de Gestión de Talento Humano

# Contexto del Agente — Archivo 06: Sprints de Desarrollo

---

## INSTRUCCIONES PARA EL AGENTE

Cuando el usuario diga "ejecuta el Sprint X" o "trabaja en el Sprint X",
el agente debe:

1. Leer la lista de tareas del sprint
2. Ejecutarlas en el orden indicado
3. Confirmar cada tarea completada antes de pasar a la siguiente
4. Si una tarea falla, detener y reportar el error con detalle
5. Al terminar el sprint, mostrar un resumen de lo completado

---

## SPRINT 0 — Configuración Base del Proyecto

**Duración estimada:** 3 días
**Prerequisito:** Proyecto Laravel 13 creado, PostgreSQL y Redis corriendo en Docker

### Tareas en orden:

1. Verificar versión PHP 8.3 y Laravel 13 con `php artisan --version`
2. Crear carpeta `.antigravity/` en la raíz del proyecto
3. Configurar `bootstrap/app.php` con el handler global de excepciones (ver Archivo 04)
4. Crear `app/Http/Responses/ApiResponse.php` con todos los métodos (ver Archivo 04)
5. Crear `app/Exceptions/ReglaNegocioException.php`
6. Crear todos los Enums en `app/Enums/`:
    - `Rol.php`
    - `Permiso.php`
    - `EstadoPermiso.php`
    - `TipoPermiso.php`
    - `RegimenLaboral.php`
    - `EstadoNomina.php`
    - `EstadoTicket.php`
    - `PrioridadTicket.php`
    - `TipoConcepto.php`
    - `EstadoCita.php`
    - `TipoDiscapacidad.php`
    - `TipoNombramiento.php`
    - `TipoSubrogacion.php`
    - `MotivoSubrogacion.php`
    - `EstadoSubrogacion.php`
7. Crear middleware `app/Http/Middleware/PrimerLoginMiddleware.php`
8. Crear middleware `app/Http/Middleware/VerificarRol.php`
9. Registrar middlewares en `bootstrap/app.php`
10. Publicar configuraciones:
    - `php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"`
    - `php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"`
    - `php artisan vendor:publish --provider="Laravel\Telescope\TelescopeServiceProvider"`
    - `php artisan vendor:publish --tag="activitylog-migrations"`
11. Ejecutar migraciones base: `php artisan migrate`
12. Crear `database/seeders/RolPermisoSeeder.php` (ver Archivo 05)
13. Crear `database/seeders/AdminTiSeeder.php` con usuario inicial
14. Ejecutar seeders: `php artisan db:seed`
15. Verificar que Telescope funciona en `http://sgth.test/telescope`

**Entregable:** `php artisan migrate --seed` ejecuta sin errores. Todos los Enums creados. ApiResponse disponible.

---

## SPRINT 1 — Autenticación y Gestión de Usuarios

**Duración estimada:** 4 días
**Prerequisito:** Sprint 0 completado

### Tareas en orden:

1. Migración `crear_tabla_servidores` con campos base de identidad y autenticación:
    - `id`
    - `user_id` (FK users — cuenta de acceso al sistema)
    - `cedula` (string 10, unique — número de cédula ecuatoriana o pasaporte)
    - `nombre` (string)
    - `segundo_nombre` (string nullable)
    - `apellido` (string)
    - `segundo_apellido` (string nullable)
    - `regimen_laboral` (enum: losep/codigo_trabajo)
    - `unidad_administrativa_id` (FK unidades_administrativas nullable)
    - `puesto_id` (FK puestos nullable)
    - `estado` (boolean default true)
    - `timestamps`, `softDeletes`
    - NOTA: Los campos del expediente completo se agregan en Sprint 3 para mantener el Sprint 1 enfocado en autenticación
2. Modificar tabla `users` nativa: agregar columna `usuario_ti` (string unique)
3. Modelo `User` — agregar relaciones y el método `esJefeDeServidor(int $servidorId): bool`
   con la siguiente lógica:

    ```php
    public function esJefeDeServidor(int $servidorId): bool
    {
        $servidor = Servidor::find($servidorId);
        if (!$servidor) return false;

        $mismaUnidad = $servidor->unidad_administrativa_id;
        $miServidor  = Servidor::where('user_id', $this->id)->first();
        if (!$miServidor) return false;

        // Caso 1: es jefe titular por puesto (es_jefe = true en la misma unidad)
        $esJefeTitular = Servidor::where('user_id', $this->id)
            ->whereHas('puesto', fn($q) => $q->where('es_jefe', true))
            ->where('unidad_administrativa_id', $mismaUnidad)
            ->exists();

        if ($esJefeTitular) return true;

        // Caso 2: está en subrogación o encargo activo en esa unidad
        return Subrogacion::where('servidor_subrogante_id', $miServidor->id)
            ->where('unidad_administrativa_id', $mismaUnidad)
            ->whereIn('estado', ['activa'])
            ->where('fecha_inicio', '<=', now())
            ->where('fecha_fin', '>=', now())
            ->exists();
    }
    ```

    NOTA: El modelo `Subrogacion` se crea en el Sprint 3. En el Sprint 1
    este método puede definirse con solo el Caso 1 y agregarse el Caso 2
    una vez que el Sprint 3 esté completado.

4. Modelo `App\Models\Expediente\Servidor` con SoftDeletes y cast de `regimen_laboral` a `RegimenLaboral` enum
5. `app/Contracts/Auth/AuthServiceInterface.php`
6. `app/Services/Auth/AuthService.php`:
    - `login(string $usuario, string $contrasena): array`
    - `cambiarContrasenaInicial(User $user, string $nuevaContrasena): void`
    - Al login: verificar `primer_login`, incluir flag en respuesta
7. `app/Http/Requests/Auth/LoginRequest.php` (campos: `usuario`, `contrasena`, mensajes en español)
8. `app/Http/Requests/Auth/CambiarContrasenaRequest.php`
9. `app/Http\Controllers\Auth\AuthController.php` (ver Archivo 04)
10. `app/Http\Controllers\Auth\PasswordController.php`
11. `app/Policies/Admin/UsuarioPolicy.php`
12. `app/Http/Requests/Admin/StoreUsuarioRequest.php`
13. `app/Http/Requests/Admin/UpdateUsuarioRequest.php`
14. `app/Contracts/Admin/UsuarioServiceInterface.php`
15. `app/Services/Admin/UsuarioService.php`:
    - `crear(array $datos): User` — genera `usuario_ti` (primera letra nombre + primer apellido), contraseña inicial = cédula hasheada, `primer_login = true`
    - `restablecerContrasena(int $userId): void` — resetea a cédula, `primer_login = true`
16. `app/Http/Controllers/Admin/UsuarioController.php`
17. Rutas en `routes/api.php` (ver Archivo 04)
18. Test: login exitoso devuelve token + flag `primer_login`
19. Test: intentar acceder a endpoint protegido sin cambiar contraseña devuelve 403

**Entregable:** POST `/api/v1/auth/login` con `{"usuario":"jperez","contrasena":"1234567890"}` devuelve token. Sistema bloquea acceso si `primer_login = true`.

---

## SPRINT 2 — Módulo 01: Estructura Organizacional y Puestos

**Duración estimada:** 5 días
**Prerequisito:** Sprint 1 completado

### Tareas en orden:

1. Migración `crear_tabla_unidades_administrativas`:
    - `id`, `codigo`, `nombre`, `descripcion` (nullable),
    - `unidad_padre_id` (FK self nullable — para jerarquía),
    - `nivel` (tinyInteger), `estado` (boolean default true),
    - `timestamps`, `softDeletes`
2. Migración `crear_tabla_puestos`:
    - `id`, `codigo` (unique), `denominacion`, `unidad_administrativa_id` (FK),
    - `grupo_ocupacional`, `grado_rmu` (tinyInteger), `rmu` (decimal 10,2),
    - `nivel`, `estado` (boolean default true),
    - `timestamps`, `softDeletes`
3. Migración `crear_tabla_valoraciones_puesto`:
    - `id`, `puesto_id` (FK), `factor`, `puntos` (decimal),
    - `observacion` (nullable), `valorado_por` (FK users),
    - `valorado_en` (timestamp), `timestamps`
4. Modelo `UnidadAdministrativa` con `#[ObservedBy]`, SoftDeletes, relación `hijos()`, `padre()`, `puestos()`
5. Modelo `Puesto` con `#[ObservedBy]`, SoftDeletes, relación `unidadAdministrativa()`
6. Observer `UnidadAdministrativaObserver` y `PuestoObserver` — registrar en activity_log
7. `app/Policies/Estructura/UnidadAdministrativaPolicy.php`
8. `app/Policies/Estructura/PuestoPolicy.php`
9. Form Requests (Store y Update separados, mensajes en español):
    - `StoreUnidadAdministrativaRequest`, `UpdateUnidadAdministrativaRequest`
    - `StorePuestoRequest`, `UpdatePuestoRequest`
10. API Resources:
    - `UnidadAdministrativaResource` (con relación hijos anidados para organigrama)
    - `PuestoResource`
11. `app/Contracts/Estructura/EstructuraServiceInterface.php`
12. `app/Services/Estructura/EstructuraService.php`:
    - `listarUnidades(array $filtros)`, `crearUnidad(array $datos)`, etc.
    - `obtenerOrganigrama(): array` — árbol jerárquico completo
    - `listarPuestos(array $filtros)`, `crearPuesto(array $datos)`, etc.
13. Binding en `AppServiceProvider`
14. Controllers: `UnidadAdministrativaController`, `PuestoController`, `OrgangramaController`
15. Rutas bajo `/api/v1/estructura` con middleware role
16. Seeder `UnidadAdministrativaSeeder` con las unidades base del GAD
17. Test: GET `/api/v1/estructura/organigrama` devuelve árbol jerárquico

**Entregable:** CRUD completo de unidades y puestos. Organigrama disponible como árbol JSON.

---

## SPRINT 3 — Módulo 02: Expediente Digital del Servidor

**Duración estimada:** 7 días
**Prerequisito:** Sprint 2 completado

### Tareas en orden:

#### 3.1 — Completar la migración `servidores` con el expediente completo

Crear una migración adicional `completar_tabla_servidores_expediente` que agrega
todas las secciones del expediente a la tabla base creada en Sprint 1.

**Sección A — Datos personales completos:**

- `fecha_nacimiento` (date)
- `genero` (enum: masculino/femenino/otro)
- `estado_civil` (enum: soltero/casado/union_libre/divorciado/viudo)
- `tipo_sangre` (enum: A+/A-/B+/B-/AB+/AB-/O+/O- — nullable hasta que se registre)
- `es_extranjero` (boolean default false)
- `nacionalidad` (string nullable — requerido si es_extranjero = true)
- `pais_origen` (string nullable — requerido si es_extranjero = true)
- `provincia_nacimiento` (string nullable — requerido si es_extranjero = false)
- `ciudad_nacimiento` (string nullable — requerido si es_extranjero = false)

**Sección B — Documentos de identidad y ciudadanía:**

- `numero_papeleta_votacion` (string nullable — obligatorio para ecuatorianos mayores de edad)
- `pasaporte_numero` (string nullable — para servidores extranjeros)
- `pasaporte_vencimiento` (date nullable)

**Sección C — Contacto:**

- `telefono_celular` (string nullable)
- `telefono_convencional` (string nullable)
- `correo_institucional` (string unique — correo @gadpe.gob.ec)
- `correo_personal` (string nullable)
- `direccion_domicilio` (string nullable)
- `provincia_domicilio` (string nullable)
- `ciudad_domicilio` (string nullable)

**Sección D — Discapacidad (CONADIS):**

- `tiene_discapacidad` (boolean default false)
- `tipo_discapacidad` (enum nullable):
    - `fisica` — Discapacidad Física
    - `sensorial` — Discapacidad Sensorial (visual, auditiva, del habla)
    - `intelectual` — Discapacidad Intelectual
    - `psicosocial` — Discapacidad Psicosocial o Mental
    - `visceral` — Discapacidad Visceral u Orgánica
    - `multiple` — Discapacidad Múltiple
- `porcentaje_discapacidad` (decimal 5,2 nullable — ej: 45.00%)
- `numero_carnet_conadis` (string nullable — número único del carnet CONADIS)
- `carnet_conadis_ruta` (string nullable — ruta del archivo PDF/imagen del carnet subido)
- `carnet_conadis_vencimiento` (date nullable)

**Sección E — Enfermedad catastrófica:**

- `tiene_enfermedad_catastrofica` (boolean default false)
- `tipo_enfermedad_catastrofica` (string nullable — texto libre para especificar la enfermedad según el diagnóstico médico registrado en el MSP/IESS)
- `enfermedad_catastrofica_certificado_ruta` (string nullable — ruta del certificado médico)

**Sección F — Datos laborales e institucionales:**

- `tipo_nombramiento` (enum: nombramiento_permanente/nombramiento_provisional/contrato_servicios_ocasionales/libre_nombramiento_remocion/codigo_trabajo)
- `numero_contrato` (string nullable — número del contrato si aplica)
- `fecha_ingreso_institucion` (date — fecha en que el servidor ingresó al GAD)
- `fecha_ingreso_sector_publico` (date nullable — fecha de inicio en el sector público, puede ser antes del GAD)
- `fecha_nombramiento` (date nullable — fecha del nombramiento definitivo si aplica)
- `fecha_inicio_ultimo_contrato` (date nullable — fecha de inicio del contrato vigente)
- `fecha_fin_ultimo_contrato` (date nullable — fecha de vencimiento del contrato vigente)

**Sección G — Biométrico:**

- `codigo_marcacion` (string 10, nullable — código alfanumérico asignado por el reloj biométrico del GAD. Nullable porque algunos servidores no marcan según su tipo de contrato)
- NOTA IMPORTANTE: Las marcaciones NO se almacenan en esta tabla. El código `codigo_marcacion` es el identificador que se usa como parámetro al llamar el Stored Procedure de la base de datos SQL Server del sistema biométrico. Ver `BiometricoService` para la implementación de lectura.
- Agregar índice: `$table->index('codigo_marcacion')` para optimizar las consultas al importar marcaciones

#### 3.2 — Enum de tipos de discapacidad

Crear `app/Enums/TipoDiscapacidad.php`:

```php
enum TipoDiscapacidad: string
{
    case FISICA       = 'fisica';
    case SENSORIAL    = 'sensorial';
    case INTELECTUAL  = 'intelectual';
    case PSICOSOCIAL  = 'psicosocial';
    case VISCERAL     = 'visceral';
    case MULTIPLE     = 'multiple';

    public function etiqueta(): string
    {
        return match($this) {
            self::FISICA       => 'Discapacidad Física',
            self::SENSORIAL    => 'Discapacidad Sensorial',
            self::INTELECTUAL  => 'Discapacidad Intelectual',
            self::PSICOSOCIAL  => 'Discapacidad Psicosocial o Mental',
            self::VISCERAL     => 'Discapacidad Visceral u Orgánica',
            self::MULTIPLE     => 'Discapacidad Múltiple',
        };
    }
}
```

#### 3.3 — Enum de tipos de nombramiento

Crear `app/Enums/TipoNombramiento.php`:

```php
enum TipoNombramiento: string
{
    case PERMANENTE              = 'nombramiento_permanente';
    case PROVISIONAL             = 'nombramiento_provisional';
    case SERVICIOS_OCASIONALES   = 'contrato_servicios_ocasionales';
    case LIBRE_NOMBRAMIENTO      = 'libre_nombramiento_remocion';
    case CODIGO_TRABAJO          = 'codigo_trabajo';

    public function etiqueta(): string
    {
        return match($this) {
            self::PERMANENTE            => 'Nombramiento Permanente',
            self::PROVISIONAL           => 'Nombramiento Provisional',
            self::SERVICIOS_OCASIONALES => 'Contrato de Servicios Ocasionales',
            self::LIBRE_NOMBRAMIENTO    => 'Libre Nombramiento y Remoción',
            self::CODIGO_TRABAJO        => 'Código del Trabajo',
        };
    }

    // Los tipos bajo Código del Trabajo tienen vacaciones por días calendario
    public function esCodigoTrabajo(): bool
    {
        return $this === self::CODIGO_TRABAJO;
    }
}
```

#### 3.4 — Actualizar el modelo Servidor

Actualizar `app/Models/Expediente/Servidor.php` con:

- Cast de `regimen_laboral` → `RegimenLaboral` enum
- Cast de `tipo_discapacidad` → `TipoDiscapacidad` enum (nullable)
- Cast de `tipo_nombramiento` → `TipoNombramiento` enum
- Cast de `tiene_discapacidad` → boolean
- Cast de `tiene_enfermedad_catastrofica` → boolean
- Cast de `es_extranjero` → boolean
- Cast de `fecha_nacimiento`, `fecha_ingreso_institucion`, `fecha_nombramiento`,
  `fecha_inicio_ultimo_contrato`, `fecha_fin_ultimo_contrato` → date
- Relaciones: `user()`, `unidadAdministrativa()`, `puesto()`, `documentos()`, `movimientos()`
- Accessor `nombreCompleto()`: combina nombre + segundo_nombre + apellido + segundo_apellido
- Scope `conDiscapacidad()`: filtra servidores con discapacidad registrada
- Scope `conEnfermedadCatastrofica()`: filtra servidores con enfermedad catastrófica
- Scope `porCodigo($codigo)`: busca por `codigo_marcacion` para el biométrico

#### 3.5 — Migración `crear_tabla_documentos_servidor`

```
id
servidor_id (FK servidores)
tipo_documento (enum):
  - cedula_identidad
  - papeleta_votacion
  - titulo_tercer_nivel
  - titulo_cuarto_nivel
  - certificado_trabajo_anterior
  - carnet_conadis
  - certificado_enfermedad_catastrofica
  - contrato_laboral
  - nombramiento
  - certificado_medico
  - otro
nombre_archivo (string — nombre original del archivo)
ruta_archivo (string — ruta en storage)
tamanio_bytes (unsignedBigInteger nullable)
mime_type (string nullable)
fecha_vencimiento (date nullable — para documentos que caducan)
descripcion (string nullable — observaciones adicionales)
estado (boolean default true)
subido_por (FK users)
timestamps
softDeletes
```

#### 3.6 — Migración `crear_tabla_movimientos_personal`

Sin SoftDeletes — registro histórico inmutable:

```
id
servidor_id (FK servidores)
tipo_movimiento (enum: traslado/ascenso/subrogacion/comision_servicios/
                        cambio_regimen/cambio_puesto/ingreso/egreso)
descripcion (text)
fecha_efectiva (date)
unidad_origen_id (FK unidades_administrativas nullable)
unidad_destino_id (FK unidades_administrativas nullable)
puesto_origen_id (FK puestos nullable)
puesto_destino_id (FK puestos nullable)
resolucion_numero (string nullable — número de la resolución administrativa)
documento_respaldo (string nullable — ruta del archivo de respaldo)
autorizado_por (FK users)
observacion (text nullable)
timestamps — SIN softDeletes
```

#### 3.7 — Actualizar BiometricoService con código de marcación

Actualizar `app/Services/Biometrico/BiometricoService.php`:

- El método `importarMarcaciones()` ahora usa `codigo_marcacion` del servidor
  para llamar al Stored Procedure de SQL Server
- El SP recibe el `codigo_marcacion` como parámetro de filtro
- Lógica: obtener todos los servidores con `codigo_marcacion NOT NULL`,
  llamar al SP por cada código y guardar las marcaciones en `marcaciones`
- Ejemplo de llamada al SP:

```php
// Llamada al Stored Procedure del sistema biométrico
$stmt = $this->conexionBiometrico->prepare(
    'EXEC sp_ObtenerMarcaciones @CodigoEmpleado = ?, @FechaDesde = ?, @FechaHasta = ?'
);
$stmt->execute([$servidor->codigo_marcacion, $desde->format('Y-m-d'), $hasta->format('Y-m-d')]);
```

- El nombre exacto del SP y sus parámetros deben confirmarse con el proveedor del biométrico

#### 3.8 — Migración `crear_tabla_subrogaciones`

Sin SoftDeletes — registro histórico inmutable.

El GAD maneja dos figuras administrativas:

- **Subrogación:** el titular está ausente temporalmente (vacaciones, comisión, enfermedad).
  Existe un titular definido que regresará.
- **Encargo:** el cargo está vacante sin titular. Se asigna a alguien mientras
  se realiza el proceso de selección formal.

```
subrogaciones
├── id
├── tipo (enum: subrogacion / encargo)
│     subrogacion → existe titular ausente
│     encargo     → cargo vacante sin titular
├── servidor_subrogante_id (FK servidores — quien asume el cargo temporalmente)
├── servidor_subrogado_id  (FK servidores nullable — el titular ausente;
│                           NULL cuando es encargo porque no hay titular)
├── unidad_administrativa_id (FK unidades_administrativas — unidad donde se ejerce)
├── puesto_subrogado_id    (FK puestos — el cargo que se subroga o encarga)
├── fecha_inicio           (date — inicio del ejercicio temporal del cargo)
├── fecha_fin              (date — fin del ejercicio temporal del cargo)
├── motivo                 (enum: vacaciones / comision_servicios / enfermedad /
│                                 licencia / encargo_vacante / otro)
├── resolucion_numero      (string nullable — número de resolución administrativa)
├── documento_respaldo     (string nullable — ruta del archivo de la resolución)
├── estado                 (enum: activa / finalizada / cancelada)
├── observacion            (text nullable)
├── registrado_por         (FK users)
├── timestamps
└── SIN softDeletes — registro histórico inmutable
```

Índices requeridos para optimizar consultas en tiempo de ejecución:

```php
$table->index(['servidor_subrogante_id', 'estado', 'fecha_inicio', 'fecha_fin']);
$table->index(['unidad_administrativa_id', 'estado']);
```

Agregar Enum `TipoSubrogacion`:

```php
enum TipoSubrogacion: string
{
    case SUBROGACION = 'subrogacion';
    case ENCARGO     = 'encargo';

    public function etiqueta(): string
    {
        return match($this) {
            self::SUBROGACION => 'Subrogación',
            self::ENCARGO     => 'Encargo',
        };
    }
}
```

Agregar Enum `MotivoSubrogacion`:

```php
enum MotivoSubrogacion: string
{
    case VACACIONES        = 'vacaciones';
    case COMISION          = 'comision_servicios';
    case ENFERMEDAD        = 'enfermedad';
    case LICENCIA          = 'licencia';
    case ENCARGO_VACANTE   = 'encargo_vacante';
    case OTRO              = 'otro';

    public function etiqueta(): string
    {
        return match($this) {
            self::VACACIONES      => 'Vacaciones',
            self::COMISION        => 'Comisión de Servicios',
            self::ENFERMEDAD      => 'Enfermedad',
            self::LICENCIA        => 'Licencia',
            self::ENCARGO_VACANTE => 'Encargo por Vacante',
            self::OTRO            => 'Otro',
        };
    }
}
```

Agregar Enum `EstadoSubrogacion`:

```php
enum EstadoSubrogacion: string
{
    case ACTIVA     = 'activa';
    case FINALIZADA = 'finalizada';
    case CANCELADA  = 'cancelada';
}
```

Modelo `Subrogacion` sin SoftDeletes:

```php
#[ObservedBy(SubrogacionObserver::class)]
class Subrogacion extends Model
{
    protected $table = 'subrogaciones';

    // Sin SoftDeletes — registro histórico inmutable

    protected $fillable = [
        'tipo', 'servidor_subrogante_id', 'servidor_subrogado_id',
        'unidad_administrativa_id', 'puesto_subrogado_id',
        'fecha_inicio', 'fecha_fin', 'motivo',
        'resolucion_numero', 'documento_respaldo',
        'estado', 'observacion', 'registrado_por',
    ];

    protected function casts(): array
    {
        return [
            'tipo'         => TipoSubrogacion::class,
            'motivo'       => MotivoSubrogacion::class,
            'estado'       => EstadoSubrogacion::class,
            'fecha_inicio' => 'date',
            'fecha_fin'    => 'date',
        ];
    }

    // Relaciones
    public function subrogante(): BelongsTo
    {
        return $this->belongsTo(Servidor::class, 'servidor_subrogante_id');
    }

    public function subrogado(): BelongsTo
    {
        // nullable — en encargos no hay titular
        return $this->belongsTo(Servidor::class, 'servidor_subrogado_id');
    }

    public function unidadAdministrativa(): BelongsTo
    {
        return $this->belongsTo(UnidadAdministrativa::class);
    }

    public function puestoSubrogado(): BelongsTo
    {
        return $this->belongsTo(Puesto::class, 'puesto_subrogado_id');
    }

    // Scope para consultas en tiempo de ejecución (Policies)
    public function scopeActivaEnFecha(Builder $query, Carbon $fecha): Builder
    {
        return $query->where('estado', EstadoSubrogacion::ACTIVA)
                     ->where('fecha_inicio', '<=', $fecha)
                     ->where('fecha_fin', '>=', $fecha);
    }
}
```

#### 3.9 — SubrogacionService y Policy

Crear `app/Contracts/Expediente/SubrogacionServiceInterface.php` y
`app/Services/Expediente/SubrogacionService.php` con:

```
registrar(array $datos): Subrogacion
    → valida que servidor_subrogado_id sea NULL si tipo = encargo
    → valida que no exista otra subrogación activa del mismo subrogante
    → valida que fecha_fin > fecha_inicio
    → registra el movimiento en movimientos_personal automáticamente

finalizar(int $subrogacionId): Subrogacion
    → cambia estado a 'finalizada'
    → registra el fin en movimientos_personal

cancelar(int $subrogacionId, string $motivo): Subrogacion
    → cambia estado a 'cancelada'

listarActivas(): Collection
    → retorna todas las subrogaciones y encargos activos en este momento

listarPorServidor(int $servidorId): Collection
    → historial completo de subrogaciones de un servidor

verificarSubrogacionActiva(int $servidorId, int $unidadId): ?Subrogacion
    → usado por esJefeDeServidor() — retorna la subrogación activa o null
```

Crear `app/Policies/Expediente/SubrogacionPolicy.php`:

- Solo `admin-uath` puede registrar, finalizar y cancelar subrogaciones
- Cualquier usuario autenticado puede consultar las subrogaciones activas
  (necesario para que los Policies de otros módulos funcionen)

Crear `app/Http/Controllers/Expediente/SubrogacionController.php`

Agregar rutas bajo `/api/v1/expediente/subrogaciones`

#### 3.10 — Impacto de las subrogaciones en otros módulos (Opción 2 — Permisos dinámicos)

La tabla `subrogaciones` se consulta en tiempo de ejecución en los Policies.
NO se modifican los roles del usuario en Spatie Permission.

Regla que aplica en todos los Policies del sistema:

> Si el usuario tiene una subrogación o encargo activo en la unidad del recurso
> solicitado, hereda los permisos del puesto subrogado para esa consulta específica.

Ejemplo en `ViaticoPolicy`:

```php
public function aprobar(User $user, Viatico $viatico): bool
{
    // Aprobación directa por rol
    if ($user->can(Permiso::APROBAR_VIATICO->value)) return true;

    // Aprobación por subrogación activa en la unidad del solicitante
    $solicitante = $viatico->servidor;
    return $user->esJefeDeServidor($solicitante->id);
    // esJefeDeServidor ya verifica subrogaciones activas internamente
}
```

Con este diseño:

- Un director en vacaciones NO puede aprobar viáticos (correcto)
- El subrogante activo SÍ puede aprobar viáticos de la unidad (correcto)
- Al vencer la fecha_fin, el acceso se revoca automáticamente sin ningún job ni proceso manual
- No hay riesgo de roles temporales que queden asignados por error

#### 3.11 — Validaciones en StoreServidorRequest

Reglas de validación en español para los campos sensibles:

- Si `es_extranjero = false`: `provincia_nacimiento` y `ciudad_nacimiento` son requeridos
- Si `es_extranjero = true`: `nacionalidad` y `pais_origen` son requeridos
- Si `tiene_discapacidad = true`: `tipo_discapacidad`, `porcentaje_discapacidad`
  y `numero_carnet_conadis` son requeridos
- Si `tiene_enfermedad_catastrofica = true`: `tipo_enfermedad_catastrofica` es requerido
- `porcentaje_discapacidad` debe estar entre 0.01 y 100.00
- `codigo_marcacion` si se provee: exactamente 10 caracteres alfanuméricos (regex: /^[A-Z0-9]{10}$/i)
- `fecha_fin_ultimo_contrato` debe ser posterior a `fecha_inicio_ultimo_contrato`

#### 3.12 — Resto de tareas del sprint

9. Modelos con SoftDeletes: `Servidor`, `DocumentoServidor`
10. Modelo sin SoftDeletes: `MovimientoPersonal`
11. Observers: `ServidorObserver`, `DocumentoServidorObserver` con `#[ObservedBy]`
12. `app/Policies/Expediente/ServidorPolicy.php` — lógica diferenciada por rol
13. Form Requests (Store y Update separados, mensajes en español):
    - `StoreServidorRequest` (con validaciones condicionales descritas en 3.8)
    - `UpdateServidorRequest`
    - `StoreDocumentoServidorRequest`
14. API Resources: `ServidorResource` (nunca exponer `carnet_conadis_ruta` directamente,
    devolver URL firmada temporal), `DocumentoServidorResource`
15. `app/Contracts/Expediente/ExpedienteServiceInterface.php`
16. `app/Services/Expediente/ExpedienteService.php`:
    - `crearServidor(array $datos): Servidor`
    - `actualizarServidor(int $id, array $datos): Servidor`
    - `subirDocumento(int $servidorId, array $datos, UploadedFile $archivo): DocumentoServidor`
    - `obtenerExpedienteCompleto(int $servidorId): Servidor`
    - `listarServidores(array $filtros): mixed` — filtros por unidad, estado, tipo_nombramiento, discapacidad
17. Controllers: `ServidorController`, `DocumentoServidorController`, `MovimientoPersonalController`
18. Rutas bajo `/api/v1/expediente`
19. Test: campos condicionales de discapacidad y extranjería validan correctamente
20. Test: el servidor solo puede ver su propio expediente. UATH puede ver todos.

**Entregable:** Expediente digital completo y profesional con:

- Todos los campos del CONADIS, LOSEP y normativa ecuatoriana presentes
- `codigo_marcacion` disponible para que `BiometricoService` lo use como parámetro del SP de SQL Server
- Validaciones condicionales funcionando (extranjería, discapacidad, fechas de contrato)
- Tabla `subrogaciones` operativa con soporte para subrogaciones (titular ausente) y encargos (vacante)
- Método `esJefeDeServidor` del modelo `User` resuelve correctamente tanto jefes titulares como subrogantes activos
- Los Policies de todos los módulos del sistema reconocen automáticamente subrogaciones y encargos activos sin modificar roles en Spatie Permission

---

## SPRINT 4 — Módulo 03: Nómina y Remuneraciones

**Duración estimada:** 8 días
**Prerequisito:** Sprint 3 completado

### Tareas en orden:

1. Migración `crear_tabla_nominas`:
    - `id`, `periodo` (string: "2026-05"), `fecha_inicio`, `fecha_fin`,
    - `estado` (enum EstadoNomina), `total_ingresos` (decimal),
    - `total_descuentos` (decimal), `total_neto` (decimal),
    - `cerrado_por` (FK users nullable), `cerrado_en` (timestamp nullable),
    - `timestamps`, `softDeletes`
2. Migración `crear_tabla_conceptos_nomina`:
    - `id`, `codigo`, `nombre`, `tipo` (ingreso/descuento/aporte),
    - `formula` (nullable), `porcentaje` (decimal nullable),
    - `activo` (boolean), `timestamps`, `softDeletes`
3. Migración `crear_tabla_detalle_nomina` — SIN softDeletes:
    - `id`, `nomina_id` (FK), `servidor_id` (FK),
    - `concepto_nomina_id` (FK), `valor` (decimal),
    - `observacion` (nullable), `timestamps`
4. Migración `crear_tabla_roles_pago`:
    - `id`, `nomina_id` (FK), `servidor_id` (FK),
    - `total_ingresos`, `total_descuentos`, `total_neto` (decimal),
    - `enviado_por_correo` (boolean default false),
    - `enviado_en` (timestamp nullable), `timestamps`, `softDeletes`
5. Migración `crear_tabla_handoffs_erp` — SIN softDeletes:
    - `id`, `tipo` (nomina/viatico_compromiso/viatico_devengado/novedad_personal),
    - `referencia_id` (int), `archivo_nombre`, `archivo_ruta`,
    - `hash_integridad` (string), `generado_por` (FK users),
    - `generado_en` (timestamp), `importado_erp_en` (timestamp nullable),
    - `timestamps`
6. Enums `EstadoNomina`, `TipoConcepto` (ya en Sprint 0)
7. Modelos con SoftDeletes: `Nomina`, `ConceptoNomina`, `RolPago`
8. Modelos sin SoftDeletes: `DetalleNomina`, `HandoffErp`
9. `app/Policies/Nomina/NominaPolicy.php`, `RolPagoPolicy.php`
10. Form Requests: `StoreNominaRequest`, `CerrarNominaRequest`
11. `app/Contracts/Nomina/NominaServiceInterface.php`
12. `app/Services/Nomina/NominaService.php`:
    - `calcularNomina(string $periodo): Nomina`
    - `cerrarNomina(int $nominaId, int $userId): Nomina`
    - Cálculo: RMU + horas extras - IESS personal - retención IR - otros descuentos
13. `app/Contracts/Handoff/HandoffErpServiceInterface.php`
14. `app/Services/Handoff/HandoffErpService.php`:
    - `generarHandoffNomina(int $nominaId): HandoffErp`
    - Genera XML con hash SHA-256 para integridad
15. Jobs: `ProcesarCierreNominaJob`, `EnviarRolPagoJob`, `GenerarHandoffErpJob`
16. Comando `CerrarNominaCommand`
17. Controllers: `NominaController`, `ConceptoNominaController`, `RolPagoController`, `HandoffErpController`
18. Seeder `ConceptoNominaSeeder` y `EscalaRmuSeeder`

**Entregable:** POST `/api/v1/nomina/cerrar` genera detalle de nómina y dispara job de handoff. GET `/api/v1/nomina/{id}/rol-pago/{servidorId}` devuelve rol de pago en JSON.

---

## SPRINT 5 — Módulo 04: Asistencia, Permisos y Vacaciones

**Duración estimada:** 8 días
**Prerequisito:** Sprint 4 completado

### Tareas en orden:

1. Migración `crear_tabla_marcaciones` — SIN softDeletes:
    - `id`, `servidor_id` (FK), `fecha_hora` (timestamp),
    - `tipo` (entrada/salida), `dispositivo_id`, `timestamps`
2. Migración `crear_tabla_permisos_servidor`:
    - `id`, `servidor_id` (FK), `tipo` (enum TipoPermiso),
    - `fecha` (date), `hora_inicio` (time), `hora_fin` (time),
    - `observacion` (nullable — obligatorio para tipo oficial),
    - `estado` (enum EstadoPermiso default pendiente),
    - `folio` (string unique nullable),
    - `confirmado_por` (FK users nullable — Recepción),
    - `confirmado_en` (timestamp nullable),
    - `validado_ts_por` (FK users nullable — Trabajo Social),
    - `validado_ts_en` (timestamp nullable),
    - `anulado_por` (FK users nullable),
    - `anulado_en` (timestamp nullable),
    - `vence_en` (timestamp — 72h laborables),
    - `timestamps`, `softDeletes`
3. Migración `crear_tabla_folios_permiso` — SIN softDeletes:
    - `id`, `permiso_id` (FK unique), `folio` (string unique),
    - `qr_ruta` (string nullable), `timestamps`
4. Migración `crear_tabla_vacaciones`:
    - `id`, `servidor_id` (FK), `fecha_inicio` (date), `fecha_fin` (date),
    - `dias_solicitados` (decimal), `tipo_dias` (habiles/calendario),
    - `estado` (pendiente/aprobada/rechazada/gozada),
    - `aprobado_por` (FK users nullable), `timestamps`, `softDeletes`
5. Modelos con SoftDeletes: `PermisoServidor`, `Vacacion`
6. Modelos sin SoftDeletes: `Marcacion`, `FolioPermiso`
7. `app/Services/Biometrico/BiometricoService.php`:
    - Conecta al SP SQL Server via PDO con driver ODBC
    - `importarMarcaciones(Carbon $desde, Carbon $hasta): int`
    - NUNCA escribe en la BD del biométrico
8. `app/Services/Asistencia/PermisoService.php`:
    - `crear(array $datos, int $servidorId): PermisoServidor`
    - Validar regla 4h para tipo PERSONAL
    - Validar observación obligatoria para tipo OFICIAL
    - Calcular vencimiento 72h laborables
    - Generar folio único y QR
    - `confirmarRecepcion(string $folio, int $recepcionUserId): PermisoServidor`
    - `validarTrabajoSocial(int $permisoId, int $tsUserId): PermisoServidor`
    - `anular(int $permisoId, int $jefeUserId): PermisoServidor`
9. `app/Services/Asistencia/VacacionLosepService.php`:
    - `calcularDiasCorrespondientes(int $aniosSectorPublico): int`
    - `calcularSaldoDisponible(int $servidorId): float`
    - `validarAcumulacion(int $servidorId): void` — alerta si supera 45 días
10. `app/Services/Asistencia/VacacionCodigoTrabajoService.php`:
    - `calcularDiasCorrespondientes(int $aniosEnGad): int`
    - `calcularSaldoDisponible(int $servidorId): float`
11. `app/Services/Qr/QrService.php` — genera QR con URL de verificación pública
12. `app/Services/Pdf/PdfService.php` — genera PDF del permiso con membrete oficial GAD
13. `app/Policies/Asistencia/PermisoPolicy.php` (ver Archivo 05)
14. Form Requests: `StorePermisoRequest` con `withValidator` para regla 4h (ver Archivo 02)
15. Form Requests: `UpdatePermisoRequest`, `ConfirmarRecepcionRequest`
16. Controllers: `PermisoController`, `VacacionController`, `AsistenciaController`, `RecepcionController`, `TrabajoSocialController`
17. Endpoint público: `GET /api/v1/permisos/verificar/{folio}` — sin autenticación
18. Jobs: `VencerPermisosJob` (marca FALTA_INJUSTIFICADA), `ImportarMarcacionesJob`
19. Comandos: `VencerPermisosCommand` (cron diario), `ImportarMarcacionesBiometricoCommand` (cron cada hora)
20. Seeder `CatalogoPermisosSeeder` y `TarifaViaticoSeeder`

**Entregable:** Flujo completo de permiso funcional. Vacaciones calcula días correctamente según régimen. Job automático marca faltas a las 72h.

---

## SPRINT 6 — Módulos 05 y 06: SGD y Autoservicio

**Duración estimada:** 5 días

### Tareas:

1. Migraciones: `documentos_institucionales`, `expedientes_electronicos`, `tramites`, `series_documentales`
2. Modelos, Policies, Services y Controllers del SGD
3. Endpoints de autoservicio bajo `/api/v1/autoservicio`:
    - `GET /mis-permisos` — lista con todos los estados y filtro por año
    - `GET /mis-vacaciones` — saldo y solicitudes
    - `GET /mis-roles-pago` — histórico de roles de pago
    - `GET /mi-expediente` — datos personales del servidor
    - `GET /mis-actividades` — bitácora de actividades laborales

**Entregable:** Servidor accede a su información sin ir a la UATH.

---

## SPRINT 7 — Módulos 07, 08 y 14: Selección, Evaluación y Disciplinario

**Duración estimada:** 6 días

### Tareas:

1. M07: Migraciones, modelos, services y controllers de selección e incorporación
2. M08: Migraciones, modelos, services y controllers de evaluación del desempeño
3. M14: Migraciones, modelos, services y controllers de sumarios disciplinarios

**Entregable:** Flujos completos de los tres módulos.

---

## SPRINT 8 — Módulo 09: Viáticos

**Duración estimada:** 4 días

### Tareas:

1. Migraciones: `viaticos`, `liquidaciones_viatico`, `tarifas_viatico`
2. Modelos, Policies, Services y Controllers
3. `ViaticoService`: cálculo tarifas MRL, control plazo 5 días hábiles, bloqueo por liquidaciones pendientes
4. Job `GenerarHandoffViaticoJob` (compromiso y devengado)

**Entregable:** Solicitud → aprobación multinivel → anticipo → liquidación → handoff ERP.

---

## SPRINT 9 — Módulos 10 y 11: SSO y Dispensario Médico

**Duración estimada:** 8 días

### Tareas SSO:

1. Migraciones: `riesgos_laborales`, `accidentes_trabajo`, `equipos_proteccion`
2. Modelos, Policies, Services y Controllers

### Tareas Dispensario:

3. Migraciones: `historias_clinicas`, `consultas_medicas`, `recetas_medicas`,
   `fichas_salud_ocupacional`, `inventario_medicinas`, `movimientos_inventario_med`, `agendas_medicas`
4. `HistoriaClinicaPolicy` — restricción estricta: SOLO personal médico
5. Campos de `historias_clinicas` y `consultas_medicas` cifrados con `Crypt` de Laravel
6. Services: `HistoriaClinicaService`, `RecetaService`, `AgendaService`, `InventarioMedicinasService`
7. Vinculación: cita médica → genera solicitud de permiso por enfermedad (Módulo 04)
8. Alertas: stock mínimo de medicinas, caducidad próxima (60 días)

**Entregable:** Médico accede a HCE, crea consulta, emite receta vinculada al inventario.

---

## SPRINT 10 — Módulos 12 y 13: Inventario TI y Helpdesk

**Duración estimada:** 5 días

### Tareas:

1. M12: Migraciones, modelos, services y controllers de inventario TI
2. M13: Migraciones, modelos, services y controllers de Helpdesk
3. `SlaService`: control de tiempos y alertas antes del vencimiento
4. Job `EnviarAlertaSlaJob`
5. Vinculación ticket ↔ bien informático del Módulo 12

**Entregable:** Ticket creado → asignado → SLA controlado → encuesta al cerrar.

---

## SPRINT 11 — Módulos 15, 16 y 17: Capacitación, Actividades y Bienestar

**Duración estimada:** 5 días

### Tareas:

1. M15: Migraciones, modelos, services y controllers de capacitación
2. M16: Migraciones, modelos, services y controllers de actividades laborales
    - `GenerarInformeActividadesService`: PDF con membrete oficial del GAD
    - Cruce con marcaciones biométricas
3. M17: Migraciones, modelos, services básicos de bienestar

**Entregable:** Servidor registra actividades y exporta PDF con membrete.

---

## SPRINT 12 — Módulo 18: Reportería

**Duración estimada:** 4 días

### Tareas:

1. `DashboardController` con KPIs en tiempo real
2. `ReporteController` con generador ad hoc
3. `GenerarReporteJob` para reportes pesados en background
4. Reportes legales: distributivo de sueldos, nómina consolidada, planilla IESS, Formulario 107, PAC
5. Exportación Excel con Maatwebsite
6. Caché de reportes frecuentes con Redis

**Entregable:** Dashboard KPIs disponible. Generador de reportes ad hoc funcional.

---

## SPRINT 13 — Pruebas, Seguridad y Go-Live

**Duración estimada:** 5 días

### Tareas:

1. Tests de integración: flujo de permisos, cierre de nómina, handoff ERP, dispensario
2. Tests de autorización: verificar restricciones por rol
3. Pentesting básico: SQL injection, XSS, CSRF, autenticación
4. Configurar entorno producción: Nginx + Docker Compose prod
5. Configurar Sentry para errores en producción
6. Configurar Laravel Pulse para monitoreo
7. Backup automático con cron
8. Go-live con módulos 🔴 críticos primero (M01 al M05)

---

## RESUMEN GENERAL

| Sprint    | Contenido                                          | Días        | Prioridad |
| --------- | -------------------------------------------------- | ----------- | --------- |
| S0        | Infraestructura base                               | 3           | 🔴        |
| S1        | Auth + Usuarios                                    | 4           | 🔴        |
| S2        | M01 Estructura                                     | 5           | 🔴        |
| S3        | M02 Expediente                                     | 5           | 🔴        |
| S4        | M03 Nómina                                         | 8           | 🔴        |
| S5        | M04 Asistencia                                     | 8           | 🔴        |
| S6        | M05 SGD + M06 Autoservicio                         | 5           | 🟠        |
| S7        | M07 Selección + M08 Eval + M14 Disciplinario       | 6           | 🟠        |
| S8        | M09 Viáticos                                       | 4           | 🟠        |
| S9        | M10 SSO + M11 Dispensario                          | 8           | 🟠        |
| S10       | M12 Inventario + M13 Helpdesk                      | 5           | 🟠        |
| S11       | M15 Capacitación + M16 Actividades + M17 Bienestar | 5           | 🟡        |
| S12       | M18 Reportería                                     | 4           | 🟢        |
| S13       | Pruebas + Go-live                                  | 5           | 🔴        |
| **TOTAL** | **18 módulos**                                     | **75 días** |           |
