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
    case GESTIONAR_CARGAS_FAMILIARES = 'gestionar-cargas-familiares';

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
    case PUEDE_MARCAR_ONLINE         = 'puede-marcar-online';

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
    case GESTIONAR_VIATICOS          = 'gestionar-viaticos';

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
    case ACTIVAR_USUARIO             = 'activar-usuario';
    case GESTIONAR_ROLES             = 'gestionar-roles';
    case VER_AUDITORIA               = 'ver-auditoria';
    case CONFIGURAR_SISTEMA          = 'configurar-sistema';
    case RESTABLECER_CONTRASENA      = 'restablecer-contrasena';
}
