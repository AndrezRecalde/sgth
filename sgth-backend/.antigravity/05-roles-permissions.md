# SGTH — Sistema de Gestión de Talento Humano
# Contexto del Agente — Archivo 05: Roles, Permisos y Políticas

---

## ENUM ROL — 15 ROLES DEL SISTEMA

```php
<?php

namespace App\Enums;

enum Rol: string
{
    // Administración RRHH
    case ADMIN_UATH        = 'admin-uath';
    case ASISTENTE_UATH    = 'asistente-uath';

    // Autoridades
    case MAXIMA_AUTORIDAD  = 'maxima-autoridad';
    case DIRECTOR          = 'director';
    case JEFE_UNIDAD       = 'jefe-unidad';

    // Servidor público
    case SERVIDOR          = 'servidor';

    // Roles operativos institucionales
    case RECEPCION         = 'recepcion';
    case TRABAJO_SOCIAL    = 'trabajo-social';

    // Personal médico del dispensario
    case MEDICO            = 'medico';
    case ODONTOLOGO        = 'odontologo';
    case ENFERMERA         = 'enfermera';
    case ADMIN_DISPENSARIO = 'admin-dispensario';

    // Tecnología
    case TECNICO_DTIC      = 'tecnico-dtic';
    case ADMIN_TI          = 'admin-ti';

    // Control y auditoría
    case AUDITOR           = 'auditor';

    public function etiqueta(): string
    {
        return match($this) {
            self::ADMIN_UATH        => 'Administrador UATH',
            self::ASISTENTE_UATH    => 'Asistente UATH',
            self::MAXIMA_AUTORIDAD  => 'Máxima Autoridad',
            self::DIRECTOR          => 'Director de Área',
            self::JEFE_UNIDAD       => 'Jefe de Unidad',
            self::SERVIDOR          => 'Servidor Público',
            self::RECEPCION         => 'Recepción',
            self::TRABAJO_SOCIAL    => 'Trabajo Social',
            self::MEDICO            => 'Médico',
            self::ODONTOLOGO        => 'Odontólogo',
            self::ENFERMERA         => 'Enfermera',
            self::ADMIN_DISPENSARIO => 'Administrativo Dispensario',
            self::TECNICO_DTIC      => 'Técnico DTIC',
            self::ADMIN_TI          => 'Administrador TI',
            self::AUDITOR           => 'Auditor',
        };
    }
}
```

---

## ENUM PERMISO — PERMISOS GRANULARES POR MÓDULO

```php
<?php

namespace App\Enums;

enum Permiso: string
{
    // ── MÓDULO 01: Estructura ────────────────────────────────────
    case VER_ESTRUCTURA              = 'ver-estructura';
    case GESTIONAR_PUESTOS           = 'gestionar-puestos';
    case GESTIONAR_ORGANIGRAMA       = 'gestionar-organigrama';
    case VER_DISTRIBUTIVO            = 'ver-distributivo';
    case GESTIONAR_DISTRIBUTIVO      = 'gestionar-distributivo';

    // ── MÓDULO 02: Expediente ────────────────────────────────────
    case VER_EXPEDIENTE_PROPIO       = 'ver-expediente-propio';
    case VER_EXPEDIENTE_UNIDAD       = 'ver-expediente-unidad';
    case VER_EXPEDIENTE_TODOS        = 'ver-expediente-todos';
    case GESTIONAR_EXPEDIENTE        = 'gestionar-expediente';
    case CARGAR_DOCUMENTOS           = 'cargar-documentos';

    // ── MÓDULO 03: Nómina ────────────────────────────────────────
    case VER_ROL_PAGO_PROPIO         = 'ver-rol-pago-propio';
    case VER_NOMINA_UNIDAD           = 'ver-nomina-unidad';
    case VER_NOMINA_TODAS            = 'ver-nomina-todas';
    case PROCESAR_NOMINA             = 'procesar-nomina';
    case CERRAR_NOMINA               = 'cerrar-nomina';
    case GENERAR_HANDOFF_ERP         = 'generar-handoff-erp';

    // ── MÓDULO 04: Asistencia ────────────────────────────────────
    case VER_ASISTENCIA_PROPIA       = 'ver-asistencia-propia';
    case VER_ASISTENCIA_UNIDAD       = 'ver-asistencia-unidad';
    case VER_ASISTENCIA_TODOS        = 'ver-asistencia-todos';
    case CREAR_PERMISO               = 'crear-permiso';
    case VER_PERMISOS                = 'ver-permisos';
    case VER_PERMISOS_TODOS          = 'ver-permisos-todos';
    case ANULAR_PERMISO              = 'anular-permiso';
    case CONFIRMAR_RECEPCION         = 'confirmar-recepcion';
    case VALIDAR_TRABAJO_SOCIAL      = 'validar-trabajo-social';
    case GESTIONAR_VACACIONES        = 'gestionar-vacaciones';
    case APROBAR_VACACIONES          = 'aprobar-vacaciones';
    case VER_VACACIONES_UNIDAD       = 'ver-vacaciones-unidad';

    // ── MÓDULO 05: SGD ───────────────────────────────────────────
    case VER_DOCUMENTOS              = 'ver-documentos';
    case CREAR_DOCUMENTOS            = 'crear-documentos';
    case FIRMAR_DOCUMENTOS           = 'firmar-documentos';
    case GESTIONAR_TRAMITES          = 'gestionar-tramites';
    case GESTIONAR_RETENCION         = 'gestionar-retencion';

    // ── MÓDULO 06: Autoservicio ──────────────────────────────────
    case ACCESO_AUTOSERVICIO         = 'acceso-autoservicio';
    case CAMBIAR_CONTRASENA          = 'cambiar-contrasena';

    // ── MÓDULO 07: Selección ─────────────────────────────────────
    case GESTIONAR_CONVOCATORIAS     = 'gestionar-convocatorias';
    case VER_POSTULANTES             = 'ver-postulantes';
    case EVALUAR_POSTULANTES         = 'evaluar-postulantes';
    case GESTIONAR_ONBOARDING        = 'gestionar-onboarding';

    // ── MÓDULO 08: Evaluación ────────────────────────────────────
    case VER_EVALUACION_PROPIA       = 'ver-evaluacion-propia';
    case VER_EVALUACIONES_UNIDAD     = 'ver-evaluaciones-unidad';
    case VER_EVALUACIONES_TODAS      = 'ver-evaluaciones-todas';
    case REALIZAR_EVALUACION         = 'realizar-evaluacion';
    case GESTIONAR_EVALUACIONES      = 'gestionar-evaluaciones';

    // ── MÓDULO 09: Viáticos ──────────────────────────────────────
    case SOLICITAR_VIATICO           = 'solicitar-viatico';
    case APROBAR_VIATICO             = 'aprobar-viatico';
    case LIQUIDAR_VIATICO            = 'liquidar-viatico';
    case VER_VIATICOS_TODOS          = 'ver-viaticos-todos';
    case GESTIONAR_TARIFAS_VIATICO   = 'gestionar-tarifas-viatico';

    // ── MÓDULO 10: SSO ───────────────────────────────────────────
    case GESTIONAR_SSO               = 'gestionar-sso';
    case REGISTRAR_ACCIDENTE         = 'registrar-accidente';
    case VER_REPORTES_SSO            = 'ver-reportes-sso';

    // ── MÓDULO 11: Dispensario ───────────────────────────────────
    case VER_AGENDA_DISPENSARIO      = 'ver-agenda-dispensario';
    case GESTIONAR_AGENDA            = 'gestionar-agenda';
    case SOLICITAR_CITA              = 'solicitar-cita';
    case VER_HISTORIA_CLINICA_PROPIA = 'ver-historia-clinica-propia';
    case VER_HISTORIA_CLINICA        = 'ver-historia-clinica';       // SOLO médicos
    case CREAR_CONSULTA              = 'crear-consulta';             // SOLO médicos
    case EMITIR_RECETA               = 'emitir-receta';              // SOLO médicos/odontólogos
    case DESPACHAR_MEDICAMENTO       = 'despachar-medicamento';      // enfermeras/admin-dispensario
    case GESTIONAR_INVENTARIO_MED    = 'gestionar-inventario-med';
    case GESTIONAR_FICHAS_SSO_MED    = 'gestionar-fichas-sso-med';
    case EVALUAR_PERSONAL_MEDICO     = 'evaluar-personal-medico';    // admin-dispensario

    // ── MÓDULO 12: Inventario TI ─────────────────────────────────
    case VER_INVENTARIO_TI           = 'ver-inventario-ti';
    case GESTIONAR_INVENTARIO_TI     = 'gestionar-inventario-ti';
    case DAR_BAJA_BIEN               = 'dar-baja-bien';

    // ── MÓDULO 13: Helpdesk ──────────────────────────────────────
    case CREAR_TICKET                = 'crear-ticket';
    case VER_TICKET_PROPIO           = 'ver-ticket-propio';
    case VER_TICKETS_TODOS           = 'ver-tickets-todos';
    case GESTIONAR_TICKETS           = 'gestionar-tickets';
    case ASIGNAR_TICKET              = 'asignar-ticket';
    case GESTIONAR_TECNICOS          = 'gestionar-tecnicos';
    case CONFIGURAR_SLA              = 'configurar-sla';
    case GESTIONAR_BASE_CONOCIMIENTO = 'gestionar-base-conocimiento';

    // ── MÓDULO 14: Disciplinario ─────────────────────────────────
    case VER_SUMARIOS                = 'ver-sumarios';
    case GESTIONAR_SUMARIOS          = 'gestionar-sumarios';
    case REGISTRAR_SANCION           = 'registrar-sancion';

    // ── MÓDULO 15: Capacitación ──────────────────────────────────
    case VER_PLAN_CAPACITACION       = 'ver-plan-capacitacion';
    case GESTIONAR_PLAN_CAPACITACION = 'gestionar-plan-capacitacion';
    case INSCRIBIRSE_CURSO           = 'inscribirse-curso';
    case GESTIONAR_CURSOS            = 'gestionar-cursos';

    // ── MÓDULO 16: Actividades ───────────────────────────────────
    case REGISTRAR_ACTIVIDADES         = 'registrar-actividades';
    case VER_ACTIVIDADES_UNIDAD        = 'ver-actividades-unidad';
    case APROBAR_ACTIVIDADES           = 'aprobar-actividades';
    case EXPORTAR_INFORME_ACTIVIDADES  = 'exportar-informe-actividades';

    // ── MÓDULO 17: Bienestar ─────────────────────────────────────
    case GESTIONAR_BIENESTAR         = 'gestionar-bienestar';
    case RESPONDER_ENCUESTA_CLIMA    = 'responder-encuesta-clima';

    // ── MÓDULO 18: Reportería ────────────────────────────────────
    case VER_DASHBOARD_EJECUTIVO     = 'ver-dashboard-ejecutivo';
    case GENERAR_REPORTES            = 'generar-reportes';
    case EXPORTAR_REPORTES           = 'exportar-reportes';

    // ── ADMIN SISTEMA ────────────────────────────────────────────
    case GESTIONAR_USUARIOS          = 'gestionar-usuarios';
    case GESTIONAR_ROLES             = 'gestionar-roles';
    case VER_AUDITORIA               = 'ver-auditoria';
    case CONFIGURAR_SISTEMA          = 'configurar-sistema';
    case RESTABLECER_CONTRASENA      = 'restablecer-contrasena';
}
```

---

## MATRIZ DE PERMISOS POR ROL

### Leyenda: ✅ tiene el permiso | ❌ no tiene el permiso

```
PERMISO                      | AU | AS | MA | DI | JU | SE | RE | TS | ME | OD | EN | AD | TD | TI | AU
                             |    |    |    |    |    |    |    |    |    |    |    | DI |    |    | DI
─────────────────────────────────────────────────────────────────────────────────────────────────────────
MÓDULO 01 ESTRUCTURA
ver-estructura               | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅
gestionar-puestos            | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌
gestionar-distributivo       | ✅ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌

MÓDULO 02 EXPEDIENTE
ver-expediente-propio        | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅
ver-expediente-unidad        | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅
ver-expediente-todos         | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅
gestionar-expediente         | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌

MÓDULO 03 NÓMINA
ver-rol-pago-propio          | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅
ver-nomina-todas             | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅
procesar-nomina              | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌
cerrar-nomina                | ✅ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌
generar-handoff-erp          | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌

MÓDULO 04 ASISTENCIA
crear-permiso                | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅
ver-permisos-todos           | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅
ver-vacaciones-unidad        | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅
confirmar-recepcion          | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌
validar-trabajo-social       | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌

MÓDULO 11 DISPENSARIO
ver-historia-clinica         | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌
crear-consulta               | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌
emitir-receta                | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌
despachar-medicamento        | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ | ❌ | ❌ | ❌
evaluar-personal-medico      | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌

MÓDULO 13 HELPDESK
crear-ticket                 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅
gestionar-tickets            | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ | ❌
gestionar-tecnicos           | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌

MÓDULO 16 ACTIVIDADES
registrar-actividades        | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅
ver-actividades-unidad       | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅
aprobar-actividades          | ❌ | ❌ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌

ADMIN SISTEMA
gestionar-usuarios           | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌
gestionar-roles              | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌
restablecer-contrasena       | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌
ver-auditoria                | ✅ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ | ✅
ver-dashboard-ejecutivo      | ✅ | ❌ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅
generar-reportes             | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅

Claves columnas:
AU=admin-uath | AS=asistente-uath | MA=maxima-autoridad | DI=director
JU=jefe-unidad | SE=servidor | RE=recepcion | TS=trabajo-social
ME=medico | OD=odontologo | EN=enfermera | AD-DI=admin-dispensario
TD=tecnico-dtic | TI=admin-ti | AUDI=auditor
```

---

## EJEMPLO DE POLICY — PermisoPolicy

```php
<?php

namespace App\Policies\Asistencia;

use App\Enums\EstadoPermiso;
use App\Models\Asistencia\Permiso;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class PermisoPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can(\App\Enums\Permiso::VER_PERMISOS->value);
    }

    public function view(User $user, Permiso $permiso): bool
    {
        // El servidor ve sus propios permisos
        if ($user->servidor_id === $permiso->servidor_id) {
            return true;
        }
        // El jefe ve los de su unidad
        if ($user->esJefeDeServidor($permiso->servidor_id)) {
            return $user->can(\App\Enums\Permiso::VER_PERMISOS->value);
        }
        // UATH y auditor ven todos
        return $user->can(\App\Enums\Permiso::VER_PERMISOS_TODOS->value);
    }

    public function create(User $user): bool
    {
        return $user->can(\App\Enums\Permiso::CREAR_PERMISO->value);
    }

    public function update(User $user, Permiso $permiso): bool
    {
        // Solo se puede editar si está en estado PENDIENTE
        return $permiso->estado === EstadoPermiso::PENDIENTE
            && $user->servidor_id === $permiso->servidor_id;
    }

    public function anular(User $user, Permiso $permiso): bool
    {
        // Solo el jefe inmediato puede anular permisos PENDIENTES
        return $permiso->estado === EstadoPermiso::PENDIENTE
            && $user->esJefeDeServidor($permiso->servidor_id)
            && $user->can(\App\Enums\Permiso::ANULAR_PERMISO->value);
    }

    public function confirmarRecepcion(User $user): bool
    {
        return $user->can(\App\Enums\Permiso::CONFIRMAR_RECEPCION->value);
    }

    public function validarTrabajoSocial(User $user): bool
    {
        return $user->can(\App\Enums\Permiso::VALIDAR_TRABAJO_SOCIAL->value);
    }
}
```

---

## SEEDER DE ROLES Y PERMISOS

```php
<?php

namespace Database\Seeders;

use App\Enums\Permiso;
use App\Enums\Rol;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolPermisoSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Crear todos los permisos del Enum
        foreach (Permiso::cases() as $permiso) {
            Permission::firstOrCreate([
                'name'       => $permiso->value,
                'guard_name' => 'sanctum',
            ]);
        }

        // 2. Crear roles y asignar permisos según la matriz
        $this->crearRol(Rol::ADMIN_TI, [
            Permiso::GESTIONAR_USUARIOS,
            Permiso::GESTIONAR_ROLES,
            Permiso::RESTABLECER_CONTRASENA,
            Permiso::VER_AUDITORIA,
            Permiso::GESTIONAR_INVENTARIO_TI,
            Permiso::GESTIONAR_TECNICOS,
            Permiso::CONFIGURAR_SISTEMA,
            Permiso::CONFIGURAR_SLA,
            Permiso::VER_TICKETS_TODOS,
            Permiso::CREAR_TICKET,
            Permiso::VER_TICKET_PROPIO,
            // + permisos básicos
            Permiso::VER_ROL_PAGO_PROPIO,
            Permiso::CREAR_PERMISO,
            Permiso::VER_PERMISOS,
            Permiso::REGISTRAR_ACTIVIDADES,
            Permiso::ACCESO_AUTOSERVICIO,
            Permiso::CAMBIAR_CONTRASENA,
        ]);

        $this->crearRol(Rol::ADMIN_UATH, [
            Permiso::VER_ESTRUCTURA,
            Permiso::GESTIONAR_PUESTOS,
            Permiso::GESTIONAR_ORGANIGRAMA,
            Permiso::VER_DISTRIBUTIVO,
            Permiso::GESTIONAR_DISTRIBUTIVO,
            Permiso::VER_EXPEDIENTE_TODOS,
            Permiso::GESTIONAR_EXPEDIENTE,
            Permiso::CARGAR_DOCUMENTOS,
            Permiso::VER_NOMINA_TODAS,
            Permiso::PROCESAR_NOMINA,
            Permiso::CERRAR_NOMINA,
            Permiso::GENERAR_HANDOFF_ERP,
            Permiso::VER_ASISTENCIA_TODOS,
            Permiso::VER_PERMISOS_TODOS,
            Permiso::ANULAR_PERMISO,
            Permiso::GESTIONAR_VACACIONES,
            Permiso::APROBAR_VACACIONES,
            Permiso::VER_VACACIONES_UNIDAD,
            Permiso::GESTIONAR_TRAMITES,
            Permiso::GESTIONAR_CONVOCATORIAS,
            Permiso::VER_POSTULANTES,
            Permiso::EVALUAR_POSTULANTES,
            Permiso::GESTIONAR_ONBOARDING,
            Permiso::VER_EVALUACIONES_TODAS,
            Permiso::GESTIONAR_EVALUACIONES,
            Permiso::VER_VIATICOS_TODOS,
            Permiso::GESTIONAR_TARIFAS_VIATICO,
            Permiso::GESTIONAR_SSO,
            Permiso::VER_SUMARIOS,
            Permiso::GESTIONAR_SUMARIOS,
            Permiso::REGISTRAR_SANCION,
            Permiso::GESTIONAR_PLAN_CAPACITACION,
            Permiso::GESTIONAR_CURSOS,
            Permiso::GESTIONAR_BIENESTAR,
            Permiso::VER_DASHBOARD_EJECUTIVO,
            Permiso::GENERAR_REPORTES,
            Permiso::EXPORTAR_REPORTES,
            Permiso::VER_AUDITORIA,
            // + permisos básicos de servidor
            Permiso::VER_ROL_PAGO_PROPIO,
            Permiso::CREAR_PERMISO,
            Permiso::VER_PERMISOS,
            Permiso::REGISTRAR_ACTIVIDADES,
            Permiso::EXPORTAR_INFORME_ACTIVIDADES,
            Permiso::ACCESO_AUTOSERVICIO,
            Permiso::CAMBIAR_CONTRASENA,
        ]);

        $this->crearRol(Rol::SERVIDOR, [
            Permiso::VER_EXPEDIENTE_PROPIO,
            Permiso::VER_ROL_PAGO_PROPIO,
            Permiso::VER_ASISTENCIA_PROPIA,
            Permiso::CREAR_PERMISO,
            Permiso::VER_PERMISOS,
            Permiso::ACCESO_AUTOSERVICIO,
            Permiso::CAMBIAR_CONTRASENA,
            Permiso::SOLICITAR_CITA,
            Permiso::VER_HISTORIA_CLINICA_PROPIA,
            Permiso::CREAR_TICKET,
            Permiso::VER_TICKET_PROPIO,
            Permiso::REGISTRAR_ACTIVIDADES,
            Permiso::EXPORTAR_INFORME_ACTIVIDADES,
            Permiso::RESPONDER_ENCUESTA_CLIMA,
            Permiso::INSCRIBIRSE_CURSO,
            Permiso::VER_EVALUACION_PROPIA,
            Permiso::SOLICITAR_VIATICO,
            Permiso::VER_PLAN_CAPACITACION,
        ]);

        $this->crearRol(Rol::RECEPCION, [
            Permiso::CONFIRMAR_RECEPCION,
            Permiso::VER_PERMISOS,
            // + permisos básicos de servidor
            Permiso::VER_ROL_PAGO_PROPIO,
            Permiso::CREAR_PERMISO,
            Permiso::REGISTRAR_ACTIVIDADES,
            Permiso::ACCESO_AUTOSERVICIO,
            Permiso::CAMBIAR_CONTRASENA,
            Permiso::CREAR_TICKET,
            Permiso::VER_TICKET_PROPIO,
        ]);

        $this->crearRol(Rol::TRABAJO_SOCIAL, [
            Permiso::VALIDAR_TRABAJO_SOCIAL,
            Permiso::VER_PERMISOS,
            // + permisos básicos de servidor
            Permiso::VER_ROL_PAGO_PROPIO,
            Permiso::CREAR_PERMISO,
            Permiso::REGISTRAR_ACTIVIDADES,
            Permiso::ACCESO_AUTOSERVICIO,
            Permiso::CAMBIAR_CONTRASENA,
            Permiso::CREAR_TICKET,
            Permiso::VER_TICKET_PROPIO,
        ]);

        $this->crearRol(Rol::MEDICO, [
            Permiso::VER_AGENDA_DISPENSARIO,
            Permiso::VER_HISTORIA_CLINICA,
            Permiso::CREAR_CONSULTA,
            Permiso::EMITIR_RECETA,
            Permiso::GESTIONAR_FICHAS_SSO_MED,
            // + permisos básicos de servidor
            Permiso::VER_ROL_PAGO_PROPIO,
            Permiso::CREAR_PERMISO,
            Permiso::REGISTRAR_ACTIVIDADES,
            Permiso::ACCESO_AUTOSERVICIO,
            Permiso::CAMBIAR_CONTRASENA,
            Permiso::CREAR_TICKET,
            Permiso::VER_TICKET_PROPIO,
        ]);

        $this->crearRol(Rol::TECNICO_DTIC, [
            Permiso::GESTIONAR_TICKETS,
            Permiso::ASIGNAR_TICKET,
            Permiso::VER_TICKETS_TODOS,
            Permiso::GESTIONAR_BASE_CONOCIMIENTO,
            Permiso::VER_INVENTARIO_TI,
            Permiso::GESTIONAR_INVENTARIO_TI,
            // + permisos básicos de servidor
            Permiso::VER_ROL_PAGO_PROPIO,
            Permiso::CREAR_PERMISO,
            Permiso::REGISTRAR_ACTIVIDADES,
            Permiso::ACCESO_AUTOSERVICIO,
            Permiso::CAMBIAR_CONTRASENA,
            Permiso::CREAR_TICKET,
            Permiso::VER_TICKET_PROPIO,
        ]);

        // ... completar para los demás roles según la matriz
    }

    private function crearRol(Rol $rol, array $permisos): void
    {
        $rolModel = Role::firstOrCreate([
            'name'       => $rol->value,
            'guard_name' => 'sanctum',
        ]);

        $rolModel->syncPermissions(
            array_map(fn(Permiso $p) => $p->value, $permisos)
        );
    }
}
```

