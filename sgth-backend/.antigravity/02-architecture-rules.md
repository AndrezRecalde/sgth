# SGTH — Sistema de Gestión de Talento Humano
# Contexto del Agente — Archivo 02: Reglas de Arquitectura y Código

---

## REGLA PRINCIPAL — NUNCA VIOLAR

> **Cero lógica de negocio en controladores.**
> Los controladores solo coordinan: reciben el request, llaman al Service, devuelven la respuesta.
> Toda lógica de negocio vive en los Services.

---

## 1. SINTAXIS LARAVEL 13 OBLIGATORIA

### 1.1 Rutas — usar sintaxis fluida
```php
// ✅ CORRECTO
Route::prefix('estructura')
    ->middleware(['auth:sanctum', 'role:admin-uath,asistente-uath'])
    ->group(function () {
        Route::apiResource('puestos', PuestoController::class);
        Route::get('organigrama', [OrgangramaController::class, 'index']);
    });

// ❌ INCORRECTO — sintaxis antigua
Route::group(['prefix' => 'estructura', 'middleware' => 'auth'], function() {});
```

### 1.2 Modelos — PHP 8.3 con tipos y Attributes
```php
<?php

namespace App\Models\Estructura;

use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Observers\Estructura\PuestoObserver;

#[ObservedBy(PuestoObserver::class)]
class Puesto extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'puestos';

    protected $fillable = [
        'codigo',
        'denominacion',
        'unidad_administrativa_id',
        'grupo_ocupacional',
        'grado_rmu',
        'rmu',
        'nivel',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'rmu'        => 'decimal:2',
            'estado'     => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }
}
```

### 1.3 Controladores — solo coordinan, nunca tienen lógica
```php
<?php

namespace App\Http\Controllers\Estructura;

use App\Http\Controllers\Controller;
use App\Http\Requests\Estructura\StorePuestoRequest;
use App\Http\Requests\Estructura\UpdatePuestoRequest;
use App\Http\Resources\Estructura\PuestoResource;
use App\Http\Responses\ApiResponse;
use App\Contracts\Estructura\PuestoServiceInterface;
use Illuminate\Http\JsonResponse;

final class PuestoController extends Controller
{
    public function __construct(
        private readonly PuestoServiceInterface $puestoService,
    ) {}

    public function index(): JsonResponse
    {
        $puestos = $this->puestoService->listar(request()->all());
        return ApiResponse::paginado($puestos);
    }

    public function store(StorePuestoRequest $request): JsonResponse
    {
        $puesto = $this->puestoService->crear($request->validated());
        return ApiResponse::created(
            new PuestoResource($puesto),
            'Puesto creado exitosamente.'
        );
    }

    public function show(int $id): JsonResponse
    {
        $puesto = $this->puestoService->obtener($id);
        return ApiResponse::ok(new PuestoResource($puesto));
    }

    public function update(UpdatePuestoRequest $request, int $id): JsonResponse
    {
        $puesto = $this->puestoService->actualizar($id, $request->validated());
        return ApiResponse::ok(
            new PuestoResource($puesto),
            'Puesto actualizado exitosamente.'
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $this->puestoService->eliminar($id);
        return ApiResponse::noContent('Puesto eliminado exitosamente.');
    }
}
```

### 1.4 Services — interfaz + implementación
```php
// Contrato (interfaz)
<?php

namespace App\Contracts\Estructura;

interface PuestoServiceInterface
{
    public function listar(array $filtros): mixed;
    public function crear(array $datos): mixed;
    public function obtener(int $id): mixed;
    public function actualizar(int $id, array $datos): mixed;
    public function eliminar(int $id): void;
}

// Implementación
<?php

namespace App\Services\Estructura;

use App\Contracts\Estructura\PuestoServiceInterface;
use App\Models\Estructura\Puesto;

final class PuestoService implements PuestoServiceInterface
{
    public function listar(array $filtros): mixed
    {
        return Puesto::query()
            ->when(isset($filtros['unidad_id']), fn($q) =>
                $q->where('unidad_administrativa_id', $filtros['unidad_id'])
            )
            ->when(isset($filtros['estado']), fn($q) =>
                $q->where('estado', $filtros['estado'])
            )
            ->orderBy('denominacion')
            ->paginate($filtros['por_pagina'] ?? 15);
    }

    public function crear(array $datos): Puesto
    {
        return Puesto::create($datos);
    }

    public function obtener(int $id): Puesto
    {
        return Puesto::findOrFail($id);
    }

    public function actualizar(int $id, array $datos): Puesto
    {
        $puesto = $this->obtener($id);
        $puesto->update($datos);
        return $puesto->fresh();
    }

    public function eliminar(int $id): void
    {
        $this->obtener($id)->delete();
    }
}
```

### 1.5 Binding en AppServiceProvider
```php
public function register(): void
{
    // Un binding por módulo
    $this->app->bind(
        \App\Contracts\Estructura\PuestoServiceInterface::class,
        \App\Services\Estructura\PuestoService::class,
    );
    $this->app->bind(
        \App\Contracts\Asistencia\PermisoServiceInterface::class,
        \App\Services\Asistencia\PermisoService::class,
    );
    // ... todos los services
}
```

### 1.6 Form Requests — Store y Update SIEMPRE separados
```php
// StorePuestoRequest.php
final class StorePuestoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Puesto::class);
    }

    public function rules(): array
    {
        return [
            'codigo'                   => ['required', 'string', 'max:20', 'unique:puestos,codigo'],
            'denominacion'             => ['required', 'string', 'max:255'],
            'unidad_administrativa_id' => ['required', 'integer', 'exists:unidades_administrativas,id'],
            'grupo_ocupacional'        => ['required', 'string', 'max:100'],
            'grado_rmu'                => ['required', 'integer', 'min:1', 'max:20'],
            'rmu'                      => ['required', 'numeric', 'min:0'],
            'nivel'                    => ['required', 'string', 'max:50'],
            'estado'                   => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'codigo.required'                   => 'El código del puesto es obligatorio.',
            'codigo.unique'                     => 'Ya existe un puesto con este código.',
            'denominacion.required'             => 'La denominación del puesto es obligatoria.',
            'unidad_administrativa_id.required' => 'La unidad administrativa es obligatoria.',
            'unidad_administrativa_id.exists'   => 'La unidad administrativa seleccionada no existe.',
            'grupo_ocupacional.required'        => 'El grupo ocupacional es obligatorio.',
            'grado_rmu.required'                => 'El grado RMU es obligatorio.',
            'rmu.required'                      => 'La remuneración mensual unificada es obligatoria.',
            'nivel.required'                    => 'El nivel del puesto es obligatorio.',
        ];
    }
}

// UpdatePuestoRequest.php — SIEMPRE separado del Store
final class UpdatePuestoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('puesto'));
    }

    public function rules(): array
    {
        return [
            'denominacion'             => ['sometimes', 'string', 'max:255'],
            'unidad_administrativa_id' => ['sometimes', 'integer', 'exists:unidades_administrativas,id'],
            'grupo_ocupacional'        => ['sometimes', 'string', 'max:100'],
            'grado_rmu'                => ['sometimes', 'integer', 'min:1', 'max:20'],
            'rmu'                      => ['sometimes', 'numeric', 'min:0'],
            'nivel'                    => ['sometimes', 'string', 'max:50'],
            'estado'                   => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'unidad_administrativa_id.exists' => 'La unidad administrativa seleccionada no existe.',
            'grado_rmu.min'                   => 'El grado RMU debe ser mayor a 0.',
            'grado_rmu.max'                   => 'El grado RMU no puede superar 20.',
            'rmu.numeric'                     => 'La remuneración debe ser un valor numérico.',
        ];
    }
}
```

---

## 2. ESTRUCTURA DE CARPETAS OBLIGATORIA

```
app/
  Contracts/                    ← interfaces de todos los services
    Estructura/
      PuestoServiceInterface.php
    Asistencia/
      PermisoServiceInterface.php
      VacacionServiceInterface.php
    Nomina/
      NominaServiceInterface.php
    Dispensario/
      HistoriaClinicaServiceInterface.php
    Helpdesk/
      TicketServiceInterface.php
    ...un subdirectorio por módulo

  Services/                     ← implementaciones
    Estructura/
      PuestoService.php
    Asistencia/
      PermisoService.php        ← reglas 4h, 72h, folios, QR
      VacacionLosepService.php  ← motor vacaciones LOSEP (días hábiles)
      VacacionCodigoTrabajoService.php  ← motor vacaciones CT (días calendario)
    Nomina/
      NominaService.php
    Biometrico/
      BiometricoService.php     ← lectura SP SQL Server (SOLO LECTURA)
    Handoff/
      HandoffErpService.php     ← genera archivos XML/JSON para el ERP
    Pdf/
      PdfService.php            ← membrete oficial GAD Provincial de Esmeraldas
    Qr/
      QrService.php             ← folio único + QR verificable en navegador
    Dispensario/
      HistoriaClinicaService.php
      RecetaService.php
    Helpdesk/
      TicketService.php
      SlaService.php

  Http/
    Controllers/                ← un subdirectorio por módulo
      Auth/
        AuthController.php
        PasswordController.php
      Estructura/
        PuestoController.php
        UnidadAdministrativaController.php
        OrgangramaController.php
      Asistencia/
        AsistenciaController.php
        PermisoController.php
        RecepcionController.php   ← confirma permisos digitalmente
        TrabajoSocialController.php  ← valida Enfermedad y Calamidad
        VacacionController.php
      Nomina/
        NominaController.php
        RolPagoController.php
        HandoffErpController.php
      Dispensario/
        AgendaController.php
        HistoriaClinicaController.php
        RecetaController.php
        InventarioMedicinasController.php
      Helpdesk/
        TicketController.php
        TecnicoController.php
        AreaDticController.php
      Admin/
        UsuarioController.php
        RolController.php
        AuditoriaController.php
      ...un subdirectorio por módulo

    Requests/                   ← Store y Update siempre separados
      Estructura/
        StorePuestoRequest.php
        UpdatePuestoRequest.php
      Asistencia/
        StorePermisoRequest.php
        UpdatePermisoRequest.php
        ConfirmarRecepcionRequest.php
      ...

    Resources/                  ← API Resources por módulo
      Estructura/
        PuestoResource.php
        UnidadAdministrativaResource.php
      Asistencia/
        PermisoResource.php
      ...

    Responses/
      ApiResponse.php           ← respuesta JSON estándar (ver Archivo 04)

    Middleware/
      VerificarRol.php
      PrimerLoginMiddleware.php  ← bloquea acceso hasta cambiar contraseña
      LogAcceso.php

  Models/                       ← un subdirectorio por módulo
    Estructura/
      Puesto.php
      UnidadAdministrativa.php
    Expediente/
      Servidor.php
      DocumentoServidor.php
      MovimientoPersonal.php
    Asistencia/
      Permiso.php
      Marcacion.php
      Vacacion.php
      FolioPermiso.php
    Nomina/
      Nomina.php
      ConceptoNomina.php
      DetalleNomina.php
      RolPago.php
      HandoffErp.php
    Dispensario/
      HistoriaClinica.php
      ConsultaMedica.php
      RecetaMedica.php
      InventarioMedicina.php
      AgendaMedica.php
    Helpdesk/
      Ticket.php
      AreaDtic.php
      TecnicoDtic.php
      Sla.php
    ...

  Policies/                     ← un Policy por modelo principal
    Estructura/
      PuestoPolicy.php
    Asistencia/
      PermisoPolicy.php
      VacacionPolicy.php
    Nomina/
      NominaPolicy.php
    Dispensario/
      HistoriaClinicaPolicy.php  ← SOLO personal médico
    Helpdesk/
      TicketPolicy.php
    Admin/
      UsuarioPolicy.php
    ...

  Observers/                    ← registrados con #[ObservedBy]
    Estructura/
      PuestoObserver.php
    Expediente/
      ServidorObserver.php
    ...

  Jobs/                         ← tareas asíncronas
    ProcesarCierreNominaJob.php
    EnviarRolPagoJob.php
    ImportarMarcacionesJob.php
    GenerarHandoffErpJob.php
    VencerPermisosJob.php        ← falta injustificada automática a 72h
    EnviarAlertaSlaJob.php
    GenerarReporteJob.php

  Console/
    Commands/
      CerrarNominaCommand.php
      ImportarMarcacionesBiometricoCommand.php
      VencerPermisosCommand.php
      GenerarHandoffErpCommand.php
      EnviarRolesPagoCommand.php

  Enums/
    Rol.php
    Permiso.php
    EstadoPermiso.php
    TipoPermiso.php
    RegimenLaboral.php
    EstadoNomina.php
    EstadoTicket.php
    PrioridadTicket.php
    TipoConcepto.php
    EstadoCita.php

  Notifications/
    PermisoConfirmadoNotification.php
    FaltaInjustificadaNotification.php
    SlaVencidoNotification.php
    NominaCerradaNotification.php
    GarantiaVencidaNotification.php

  Exceptions/
    ReglaNegocioException.php
```

---

## 3. ENUMS OBLIGATORIOS — BACKED ENUMS CON STRING

```php
// Todos los enums son Backed Enums con string — NUNCA enums puros
enum EstadoPermiso: string
{
    case PENDIENTE           = 'pendiente';
    case ACTIVO              = 'activo';
    case ANULADO             = 'anulado';
    case RECHAZADO           = 'rechazado';
    case FALTA_INJUSTIFICADA = 'falta_injustificada';
    case VALIDADO_TS         = 'validado_trabajo_social';
}

enum TipoPermiso: string
{
    case PERSONAL   = 'personal';
    case OFICIAL    = 'oficial';
    case ENFERMEDAD = 'enfermedad';
    case CALAMIDAD  = 'calamidad';
}

enum RegimenLaboral: string
{
    case LOSEP          = 'losep';
    case CODIGO_TRABAJO = 'codigo_trabajo';
}

enum EstadoTicket: string
{
    case ABIERTO           = 'abierto';
    case ASIGNADO          = 'asignado';
    case EN_PROCESO        = 'en_proceso';
    case PENDIENTE_USUARIO = 'pendiente_usuario';
    case RESUELTO          = 'resuelto';
    case CERRADO           = 'cerrado';
}

enum EstadoNomina: string
{
    case BORRADOR      = 'borrador';
    case EN_PROCESO    = 'en_proceso';
    case CERRADA       = 'cerrada';
    case CONTABILIZADA = 'contabilizada';
    case PAGADA        = 'pagada';
}
```

---

## 4. REGLAS DE NEGOCIO DEL MÓDULO 04 — PERMISOS Y VACACIONES

### 4.1 Tipos de permiso institucional

| Tipo | Límite horas/día | Descuenta vacaciones | Motivo visible | Flujo especial |
|------|:---:|:---:|:---:|------|
| Personal | 4 horas máximo | Sí, en horas | Solo UATH | Ninguno |
| Oficial | Sin límite | No | Sí, para todos | Observación obligatoria |
| Enfermedad | Sin límite | No | Sí | Trabajo Social valida documento físico |
| Calamidad | Sin límite | No | Sí | Trabajo Social valida documento físico |

### 4.2 Flujo de estados del permiso
```
PENDIENTE → (jefe anula antes de Recepción) → ANULADO
PENDIENTE → (Recepción confirma) → ACTIVO
PENDIENTE → (Recepción rechaza) → RECHAZADO
PENDIENTE → (vencen 72h laborables sin confirmación) → FALTA_INJUSTIFICADA
ACTIVO + Enfermedad/Calamidad → (Trabajo Social valida) → VALIDADO_TRABAJO_SOCIAL
```

### 4.3 Regla de 72 horas
- Plazo: 72 horas laborables desde el **inicio de la jornada** del día de la incidencia
- Al vencer: el sistema marca automáticamente como FALTA_INJUSTIFICADA (job automático)
- Reversión: solo la UATH puede revertir manualmente con documento de respaldo

### 4.4 Vacaciones — dos motores de cálculo

**VacacionLosepService:**
- Días: hábiles (no cuenta feriados ni fines de semana)
- Años 1–5: 15 días | Años 6–10: 20 días | Años 11–15: 25 días | Años 16+: 30 días
- Antigüedad: años en el sector público (no solo en el GAD)
- Límite acumulación: 60 días — alerta a los 45 días
- Compensación en activo: NO

**VacacionCodigoTrabajoService:**
- Días: calendario (incluye feriados y fines de semana)
- Base: 15 días + 1 día por cada año adicional de servicio en el GAD
- Límite acumulación: 3 años — alerta al acercarse a 2 años
- Compensación hasta la mitad: SÍ con acuerdo del trabajador

---

## 5. REGLAS DE SEGURIDAD OBLIGATORIAS

### 5.1 Historia clínica — restricción máxima
La tabla `historias_clinicas` y `consultas_medicas` son las más sensibles del sistema.
- Acceso ÚNICAMENTE para roles: `medico`, `odontologo`, `enfermera`, `admin-dispensario`
- Ningún otro rol puede acceder aunque tenga otros permisos administrativos
- La UATH no puede ver el contenido clínico — solo datos de citas (fecha/hora/médico)
- El servidor puede ver su propia historia clínica pero no la de otros

### 5.2 Auditoría obligatoria
Todo modelo con `#[ObservedBy]` registra automáticamente en el log de auditoría:
- Quién accedió o modificó
- Fecha y hora exacta
- IP del usuario
- Valor antes y después del cambio

### 5.3 Contraseñas
- Hash: bcrypt con factor de costo por defecto de Laravel
- Contraseña inicial: número de cédula del servidor (asignada por TI)
- Primer login: cambio obligatorio antes de acceder a cualquier endpoint
- Política nueva contraseña: mínimo 8 caracteres con letras y números
- Bloqueo: 5 intentos fallidos → 15 minutos bloqueado

---

## 6. NOMENCLATURA DE MÉTODOS EN SERVICES

```php
// Operaciones CRUD estándar
crear(array $datos): Model
actualizar(int $id, array $datos): Model
eliminar(int $id): void
listar(array $filtros): mixed      // con paginación
obtener(int $id): Model            // findOrFail

// Métodos de dominio específicos — ejemplos
cerrarPeriodo(int $nominaId): Nomina
generarHandoff(int $nominaId): HandoffErp
calcularVacaciones(int $servidorId): array
confirmarRecepcion(string $folio, int $recepcionId): Permiso
validarTrabajoSocial(int $permisoId, int $tsId): Permiso
importarMarcaciones(): int         // retorna número de marcaciones importadas
```

