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
                'name' => $permiso->value,
                'guard_name' => 'sanctum',
            ]);
        }

        // Permisos base para cualquier servidor público en la institución
        $permisosBase = [
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
        ];

        // 2. Crear roles y asignar permisos según la matriz

        // ADMIN_TI
        $this->crearRol(Rol::ADMIN_TI, array_merge($permisosBase, [
            Permiso::ACTIVAR_USUARIO,
            Permiso::GESTIONAR_USUARIOS,
            Permiso::GESTIONAR_ROLES,
            Permiso::RESTABLECER_CONTRASENA,
            Permiso::VER_AUDITORIA,
            Permiso::GESTIONAR_INVENTARIO_TI,
            Permiso::GESTIONAR_TECNICOS,
            Permiso::CONFIGURAR_SISTEMA,
            Permiso::CONFIGURAR_SLA,
            Permiso::VER_TICKETS_TODOS,
            Permiso::GESTIONAR_TICKETS,
        ]));

        // ADMIN_UATH
        $this->crearRol(Rol::ADMIN_UATH, array_merge($permisosBase, [
            Permiso::ACTIVAR_USUARIO,
            Permiso::VER_ESTRUCTURA,
            Permiso::GESTIONAR_PUESTOS,
            Permiso::GESTIONAR_ORGANIGRAMA,
            Permiso::VER_DISTRIBUTIVO,
            Permiso::GESTIONAR_DISTRIBUTIVO,
            Permiso::VER_EXPEDIENTE_UNIDAD,
            Permiso::VER_EXPEDIENTE_TODOS,
            Permiso::GESTIONAR_EXPEDIENTE,
            Permiso::CARGAR_DOCUMENTOS,
            Permiso::GESTIONAR_CARGAS_FAMILIARES,
            Permiso::VER_NOMINA_UNIDAD,
            Permiso::VER_NOMINA_TODAS,
            Permiso::PROCESAR_NOMINA,
            Permiso::CERRAR_NOMINA,
            Permiso::GENERAR_HANDOFF_ERP,
            Permiso::VER_ASISTENCIA_UNIDAD,
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
            Permiso::VER_EVALUACIONES_UNIDAD,
            Permiso::VER_EVALUACIONES_TODAS,
            Permiso::GESTIONAR_EVALUACIONES,
            Permiso::VER_VIATICOS_TODOS,
            Permiso::GESTIONAR_VIATICOS,
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
            Permiso::VER_ACTIVIDADES_UNIDAD,
            Permiso::PUEDE_MARCAR_ONLINE,
            Permiso::SOLICITAR_CERTIFICACION_MEDICA,
        ]));

        // ASISTENTE_UATH
        $this->crearRol(Rol::ASISTENTE_UATH, array_merge($permisosBase, [
            Permiso::VER_ESTRUCTURA,
            Permiso::GESTIONAR_PUESTOS,
            Permiso::VER_EXPEDIENTE_UNIDAD,
            Permiso::VER_EXPEDIENTE_TODOS,
            Permiso::GESTIONAR_EXPEDIENTE,
            Permiso::GESTIONAR_CARGAS_FAMILIARES,
            Permiso::VER_NOMINA_UNIDAD,
            Permiso::VER_NOMINA_TODAS,
            Permiso::PROCESAR_NOMINA,
            Permiso::VER_ASISTENCIA_UNIDAD,
            Permiso::VER_ASISTENCIA_TODOS,
            Permiso::VER_PERMISOS_TODOS,
            Permiso::VER_VACACIONES_UNIDAD,
            Permiso::VER_ACTIVIDADES_UNIDAD,
            Permiso::GESTIONAR_VIATICOS,
            Permiso::GENERAR_REPORTES,
            Permiso::PUEDE_MARCAR_ONLINE,
            Permiso::SOLICITAR_CERTIFICACION_MEDICA,
            Permiso::GESTIONAR_SSO,
            Permiso::VER_REPORTES_SSO,
            Permiso::REGISTRAR_ACCIDENTE,
        ]));

        // ANALISTA_UATH
        $this->crearRol(Rol::ANALISTA_UATH, array_merge($permisosBase, [
            Permiso::VER_EXPEDIENTE_UNIDAD,
            Permiso::VER_EXPEDIENTE_TODOS,
            Permiso::GESTIONAR_CONVOCATORIAS,
            Permiso::VER_POSTULANTES,
            Permiso::EVALUAR_POSTULANTES,
            Permiso::GESTIONAR_ONBOARDING,
            Permiso::SOLICITAR_CERTIFICACION_MEDICA,
        ]));

        // MAXIMA_AUTORIDAD
        $this->crearRol(Rol::MAXIMA_AUTORIDAD, array_merge($permisosBase, [
            Permiso::VER_ESTRUCTURA,
            Permiso::GESTIONAR_DISTRIBUTIVO,
            Permiso::VER_EXPEDIENTE_UNIDAD,
            Permiso::VER_EXPEDIENTE_TODOS,
            Permiso::VER_NOMINA_UNIDAD,
            Permiso::VER_NOMINA_TODAS,
            Permiso::CERRAR_NOMINA,
            Permiso::VER_ASISTENCIA_UNIDAD,
            Permiso::VER_ASISTENCIA_TODOS,
            Permiso::VER_PERMISOS_TODOS,
            Permiso::VER_VACACIONES_UNIDAD,
            Permiso::VER_ACTIVIDADES_UNIDAD,
            Permiso::APROBAR_ACTIVIDADES,
            Permiso::VER_AUDITORIA,
            Permiso::VER_DASHBOARD_EJECUTIVO,
            Permiso::GENERAR_REPORTES,
        ]));

        // DIRECTOR
        $this->crearRol(Rol::DIRECTOR, array_merge($permisosBase, [
            Permiso::VER_ESTRUCTURA,
            Permiso::VER_EXPEDIENTE_UNIDAD,
            Permiso::VER_NOMINA_UNIDAD,
            Permiso::VER_ASISTENCIA_UNIDAD,
            Permiso::VER_VACACIONES_UNIDAD,
            Permiso::VER_ACTIVIDADES_UNIDAD,
            Permiso::APROBAR_ACTIVIDADES,
            Permiso::VER_DASHBOARD_EJECUTIVO,
            Permiso::GENERAR_REPORTES,
        ]));

        // JEFE_UNIDAD
        $this->crearRol(Rol::JEFE_UNIDAD, array_merge($permisosBase, [
            Permiso::VER_ESTRUCTURA,
            Permiso::VER_EXPEDIENTE_UNIDAD,
            Permiso::VER_NOMINA_UNIDAD,
            Permiso::VER_ASISTENCIA_UNIDAD,
            Permiso::VER_VACACIONES_UNIDAD,
            Permiso::VER_ACTIVIDADES_UNIDAD,
            Permiso::APROBAR_ACTIVIDADES,
        ]));

        // SERVIDOR
        $this->crearRol(Rol::SERVIDOR, array_merge($permisosBase, [
            Permiso::PUEDE_MARCAR_ONLINE,
        ]));

        // RECEPCION
        $this->crearRol(Rol::RECEPCION, array_merge($permisosBase, [
            Permiso::CONFIRMAR_RECEPCION,
        ]));

        // TRABAJO_SOCIAL
        $this->crearRol(Rol::TRABAJO_SOCIAL, array_merge($permisosBase, [
            Permiso::VALIDAR_TRABAJO_SOCIAL,
        ]));

        // MEDICO
        $this->crearRol(Rol::MEDICO, array_merge($permisosBase, [
            Permiso::VER_AGENDA_DISPENSARIO,
            Permiso::VER_HISTORIA_CLINICA,
            Permiso::CREAR_CONSULTA,
            Permiso::EMITIR_RECETA,
            Permiso::GESTIONAR_FICHAS_SSO_MED,
        ]));

        // ODONTOLOGO
        $this->crearRol(Rol::ODONTOLOGO, array_merge($permisosBase, [
            Permiso::VER_AGENDA_DISPENSARIO,
            Permiso::VER_HISTORIA_CLINICA,
            Permiso::CREAR_CONSULTA,
            Permiso::EMITIR_RECETA,
        ]));

        // ENFERMERA
        $this->crearRol(Rol::ENFERMERA, array_merge($permisosBase, [
            Permiso::VER_AGENDA_DISPENSARIO,
            Permiso::VER_HISTORIA_CLINICA,
            Permiso::DESPACHAR_MEDICAMENTO,
        ]));

        // ADMIN_DISPENSARIO
        $this->crearRol(Rol::ADMIN_DISPENSARIO, array_merge($permisosBase, [
            Permiso::VER_AGENDA_DISPENSARIO,
            Permiso::VER_HISTORIA_CLINICA,
            Permiso::DESPACHAR_MEDICAMENTO,
            Permiso::EVALUAR_PERSONAL_MEDICO,
        ]));

        // TECNICO_DTIC
        $this->crearRol(Rol::TECNICO_DTIC, array_merge($permisosBase, [
            Permiso::GESTIONAR_TICKETS,
            Permiso::ASIGNAR_TICKET,
            Permiso::VER_TICKETS_TODOS,
            Permiso::GESTIONAR_BASE_CONOCIMIENTO,
            Permiso::VER_INVENTARIO_TI,
            Permiso::GESTIONAR_INVENTARIO_TI,
        ]));

        // AUDITOR
        $this->crearRol(Rol::AUDITOR, array_merge($permisosBase, [
            Permiso::VER_ESTRUCTURA,
            Permiso::VER_EXPEDIENTE_UNIDAD,
            Permiso::VER_EXPEDIENTE_TODOS,
            Permiso::VER_NOMINA_UNIDAD,
            Permiso::VER_NOMINA_TODAS,
            Permiso::VER_ASISTENCIA_UNIDAD,
            Permiso::VER_ASISTENCIA_TODOS,
            Permiso::VER_PERMISOS_TODOS,
            Permiso::VER_VACACIONES_UNIDAD,
            Permiso::VER_ACTIVIDADES_UNIDAD,
            Permiso::VER_AUDITORIA,
            Permiso::VER_DASHBOARD_EJECUTIVO,
            Permiso::GENERAR_REPORTES,
            Permiso::VER_REPORTES_SSO,
        ]));
    }

    private function crearRol(Rol $rol, array $permisos): void
    {
        $rolModel = Role::firstOrCreate([
            'name' => $rol->value,
            'guard_name' => 'sanctum',
        ]);

        $rolModel->syncPermissions(
            array_unique(array_map(fn (Permiso $p) => $p->value, $permisos))
        );
    }
}
