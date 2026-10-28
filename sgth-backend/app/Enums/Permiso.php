<?php

namespace App\Enums;

/**
 * Los permisos del sistema, con guard `sanctum`.
 *
 * Al 2026-10-28, 40 de ellos no los comprobaba ningún código (ni rutas, ni
 * policies, ni pantallas): docs/plan-roles-y-accesos.md, PR 16. Cada sección
 * dice por qué cuando el motivo es uno solo —módulo futuro, o Dispensario
 * hasta su parte del PR 17, que los va usando—. Los otros 15 se revisaron
 * pantalla por pantalla en docs/revision-permisos-sin-uso.md, aprobado el
 * 2026-10-28, que se aplica en cuatro PR. Ya tienen uso los cuatro del
 * Expediente (`ver-expediente-todos`, `gestionar-expediente`,
 * `cargar-documentos` y `gestionar-cargas-familiares`) y los tres de Régimen
 * disciplinario. Se retiraron `ver-distributivo`, `ver-expediente-unidad`,
 * `ver-actividades-unidad`, `aprobar-actividades` y
 * `evaluar-personal-medico`. Faltan `gestionar-tarifas-viatico`,
 * `gestionar-base-conocimiento`, `exportar-reportes` y `ver-auditoria`. Antes
 * de darles uso, comprobar quién los tiene: el seeder los asigna aunque nadie
 * los mire.
 */
enum Permiso: string
{
    // ── MÓDULO 01: Estructura ────────────────────────────────────
    case VER_ESTRUCTURA = 'ver-estructura';
    case GESTIONAR_PUESTOS = 'gestionar-puestos';
    case GESTIONAR_ORGANIGRAMA = 'gestionar-organigrama';
    // Aquí estaba `ver-distributivo`, retirado el 2026-10-28
    // (docs/revision-permisos-sin-uso.md, D1): quién ocupa cada puesto y con
    // qué RMU ya se ve en Puestos con `ver-estructura`, y es público por la
    // LOTAIP.
    case GESTIONAR_DISTRIBUTIVO = 'gestionar-distributivo';

    // ── MÓDULO 02: Expediente ────────────────────────────────────
    case VER_EXPEDIENTE_PROPIO = 'ver-expediente-propio';
    // `ver-expediente-unidad` se retiró el 2026-10-28 (D3): la jefatura no
    // tiene pantalla de expedientes. Una «Mi equipo» llevaría un permiso hecho
    // para lo que muestre, no el Expediente entero.
    // Listar y abrir la ficha de cualquier servidor (`ServidorPolicy::verAny`
    // y `ver`). Talento Humano; hasta el 2026-10-28 lo decidía su rol.
    case VER_EXPEDIENTE_TODOS = 'ver-expediente-todos';
    // Los historiales del expediente, las cuentas bancarias y el certificado
    // laboral, y `ServidorPolicy::actualizar`.
    case GESTIONAR_EXPEDIENTE = 'gestionar-expediente';
    // Registrar la ficha de un servidor nuevo, antes de su acción de personal.
    // Lo hacía `ServidorPolicy::crear` por el rol admin-uath, y el botón se
    // pintaba a todo quien abría el Expediente (docs/plan-roles-y-accesos.md,
    // PR 14).
    case REGISTRAR_FICHA_SERVIDOR = 'registrar-ficha-servidor';
    // Buscar a un servidor por nombre o cédula para elegirlo en un formulario
    // —un ticket, una custodia, un accidente—. No abre el Expediente: devuelve
    // nombre, cédula y unidad. Hasta el 2026-10-26 el buscador pedía el listado
    // del Expediente, que solo sirve a Talento Humano, y a TIC le daba 403
    // (docs/plan-roles-y-accesos.md, PR 2).
    case BUSCAR_SERVIDORES = 'buscar-servidores';
    /**
     * Carga inicial de servidores ya vinculados: crea la ficha y el contrato
     * vigente sin Acción de Personal. Es temporal — se otorga para migrar lo
     * que Talento Humano lleva en Excel y se revoca al terminar.
     */
    case VINCULAR_SERVIDOR_INICIAL = 'vincular-servidor-inicial';
    // Subir documentos al expediente. Borrarlos sigue siendo del rol
    // admin-uath. El asistente lo tiene por decisión de Talento Humano del
    // 2026-10-03, y se le puede quitar desde Usuarios.
    case CARGAR_DOCUMENTOS = 'cargar-documentos';
    // Las cargas familiares del expediente, sus condiciones de salud y las que
    // piden los servidores desde «Mi perfil» («Cargas por validar»).
    case GESTIONAR_CARGAS_FAMILIARES = 'gestionar-cargas-familiares';
    // El trámite de una acción de personal, paso por paso (diseño de Acciones
    // de Personal, 6.3; TH 22). El asistente prepara y notifica; el director
    // suscribe, registra y anula. Hasta la fase 1.3 lo decidía el rol, y el
    // asistente podía todo.
    case PREPARAR_ACCION_PERSONAL = 'preparar-accion-personal';
    case SUSCRIBIR_ACCION_PERSONAL = 'suscribir-accion-personal';
    case REGISTRAR_ACCION_PERSONAL = 'registrar-accion-personal';
    case NOTIFICAR_ACCION_PERSONAL = 'notificar-accion-personal';
    case ANULAR_ACCION_PERSONAL = 'anular-accion-personal';
    // Corregir a mano la fecha de ingreso a la institución, que desde la fase
    // 1.5 sale de la historia de vínculos. Para errores de la carga inicial;
    // el cambio queda en el registro de auditoría de la ficha.
    case CORREGIR_DATOS_LABORALES = 'corregir-datos-laborales';

    // ── MÓDULO 03: Nómina ────────────────────────────────────────
    // El SGTH no calcula la nómina desde el 2026-10-20: de este módulo solo
    // quedan las novedades del mes, que Financiero aplica en su sistema (fase
    // 7.3a): verlas, y entregarlas, que congela el mes.
    case VER_NOVEDADES_NOMINA = 'ver-novedades-nomina';
    case ENTREGAR_NOVEDADES_NOMINA = 'entregar-novedades-nomina';

    // ── MÓDULO 04: Asistencia ────────────────────────────────────
    case VER_ASISTENCIA_PROPIA = 'ver-asistencia-propia';
    case VER_ASISTENCIA_UNIDAD = 'ver-asistencia-unidad';
    case VER_ASISTENCIA_TODOS = 'ver-asistencia-todos';
    // Marcar asistencia desde el navegador, con ubicación. No va en ningún
    // rol: TI lo asigna persona por persona desde Usuarios, a pedido de TH
    // (decisión del 2026-10-06). Además hace falta `puede_marcar`.
    case MARCAR_EN_LINEA = 'marcar-en-linea';
    case CREAR_PERMISO = 'crear-permiso';
    // Registrar permisos a nombre de cualquier servidor. `crear-permiso` lo
    // tiene todo el mundo y solo alcanza para el propio.
    case REGISTRAR_PERMISOS_SERVIDORES = 'registrar-permisos-servidores';
    case VER_PERMISOS = 'ver-permisos';
    case VER_PERMISOS_TODOS = 'ver-permisos-todos';
    case ANULAR_PERMISO = 'anular-permiso';
    case CONFIRMAR_RECEPCION = 'confirmar-recepcion';
    case VALIDAR_TRABAJO_SOCIAL = 'validar-trabajo-social';
    // Anular el permiso PENDIENTE de cualquier servidor; el propio lo anula
    // cualquiera. No incluye revertir confirmaciones: eso es `anular-permiso`.
    case ANULAR_PERMISO_PENDIENTE = 'anular-permiso-pendiente';
    // Registrar en Sirha7 (el biométrico) un permiso personal u oficial ya
    // confirmado. Enfermedad y calamidad las registra Trabajo Social al
    // validarlas, con `validar-trabajo-social` (decisión del 2026-10-08).
    case APROBAR_PERMISO_SIRHA7 = 'aprobar-permiso-sirha7';
    // Registrar y anular las licencias con remuneración, que ya no son motivos
    // de vacaciones (fase 5.1). Las aprueban `aprobar-permiso-sirha7` y, las
    // de salud y la calamidad, `validar-trabajo-social`.
    case GESTIONAR_LICENCIAS = 'gestionar-licencias';
    case GESTIONAR_VACACIONES = 'gestionar-vacaciones';
    case APROBAR_VACACIONES = 'aprobar-vacaciones';
    case VER_VACACIONES_UNIDAD = 'ver-vacaciones-unidad';
    // Aquí estaba `puede-marcar-online`, retirado el 2026-10-28
    // (docs/plan-roles-y-accesos.md, PR 16): no lo comprobaba nada y se
    // confundía con `marcar-en-linea`, que es el que manda.

    // ── MÓDULO 05: SGD ───────────────────────────────────────────
    // Módulo futuro: la gestión documental no tiene pantallas. Ningún código
    // comprueba estos permisos todavía.
    case VER_DOCUMENTOS = 'ver-documentos';
    case CREAR_DOCUMENTOS = 'crear-documentos';
    case FIRMAR_DOCUMENTOS = 'firmar-documentos';
    case GESTIONAR_TRAMITES = 'gestionar-tramites';
    case GESTIONAR_RETENCION = 'gestionar-retencion';

    // ── MÓDULO 06: Autoservicio ──────────────────────────────────
    case ACCESO_AUTOSERVICIO = 'acceso-autoservicio';
    case CAMBIAR_CONTRASENA = 'cambiar-contrasena';

    // ── MÓDULO 07: Selección ─────────────────────────────────────
    case GESTIONAR_CONVOCATORIAS = 'gestionar-convocatorias';
    case VER_POSTULANTES = 'ver-postulantes';
    case EVALUAR_POSTULANTES = 'evaluar-postulantes';
    case GESTIONAR_ONBOARDING = 'gestionar-onboarding';

    // ── MÓDULO 08: Evaluación ────────────────────────────────────
    // Módulo futuro: la evaluación del desempeño espera su diseño
    // (docs/diseno-evaluacion-desempeno.md). Ningún código los comprueba.
    case VER_EVALUACION_PROPIA = 'ver-evaluacion-propia';
    case VER_EVALUACIONES_UNIDAD = 'ver-evaluaciones-unidad';
    case VER_EVALUACIONES_TODAS = 'ver-evaluaciones-todas';
    case REALIZAR_EVALUACION = 'realizar-evaluacion';
    case GESTIONAR_EVALUACIONES = 'gestionar-evaluaciones';

    // ── MÓDULO 09: Viáticos ──────────────────────────────────────
    case SOLICITAR_VIATICO = 'solicitar-viatico';
    case APROBAR_VIATICO = 'aprobar-viatico';
    case LIQUIDAR_VIATICO = 'liquidar-viatico';
    case VER_VIATICOS_TODOS = 'ver-viaticos-todos';
    case GESTIONAR_TARIFAS_VIATICO = 'gestionar-tarifas-viatico';
    case GESTIONAR_VIATICOS = 'gestionar-viaticos';

    // ── MÓDULO 10: SSO ───────────────────────────────────────────
    case GESTIONAR_SSO = 'gestionar-sso';
    case REGISTRAR_ACCIDENTE = 'registrar-accidente';
    case VER_REPORTES_SSO = 'ver-reportes-sso';

    // ── MÓDULO 11: Dispensario ───────────────────────────────────
    // El Dispensario pasa de `role:` a permisos por partes, en el PR 17 de
    // docs/plan-roles-y-accesos.md. Desde el 17a (Farmacia) se usan
    // `emitir-receta`, `despachar-medicamento`, `gestionar-inventario-med`,
    // `ver-inventario-med`, `ver-recetas` y `supervisar-dispensario`; desde
    // el 17b (agenda, triaje, enfermería, historias), `ver-agenda-dispensario`,
    // `gestionar-agenda`, `ver-historia-clinica`, `crear-consulta` y
    // `registrar-triaje`; desde el 17c (consultas, exámenes, odontograma,
    // reposo), `ver-examenes`, `gestionar-catalogo-examenes` y
    // `registrar-odontograma`; desde el 17d (salud ocupacional, reportes,
    // tableros), `gestionar-fichas-sso-med` y los cuatro `ver-*` del final.
    // Sin uso queda `ver-historia-clinica-propia` (base);
    // `evaluar-personal-medico` se retiró el 2026-10-28 (D12): si el personal
    // médico se evalúa, lo hará el módulo de Evaluación. Lo que es identidad
    // —la especialidad, el perfil de «Mi jornada»— sigue por rol.
    case VER_AGENDA_DISPENSARIO = 'ver-agenda-dispensario';
    // Llevar la cola de todos: dar turnos y operar los de cualquier
    // profesional. Enfermería y la administración (PR 17b); el médico y el
    // odontólogo, solo los suyos.
    case GESTIONAR_AGENDA = 'gestionar-agenda';
    // El triaje, los signos vitales previos al FEMO y los servicios de
    // enfermería: Enfermería y la administración (PR 17b).
    case REGISTRAR_TRIAJE = 'registrar-triaje';
    // `solicitar-cita` se retiró junto con su ruta, que nunca funcionó.
    case VER_HISTORIA_CLINICA_PROPIA = 'ver-historia-clinica-propia';
    case VER_HISTORIA_CLINICA = 'ver-historia-clinica';       // SOLO médicos
    case CREAR_CONSULTA = 'crear-consulta';             // SOLO médicos
    case EMITIR_RECETA = 'emitir-receta';              // SOLO médicos/odontólogos
    case DESPACHAR_MEDICAMENTO = 'despachar-medicamento';      // enfermeras/admin-dispensario
    case GESTIONAR_INVENTARIO_MED = 'gestionar-inventario-med';
    case GESTIONAR_FICHAS_SSO_MED = 'gestionar-fichas-sso-med';
    case SOLICITAR_CERTIFICACION_MEDICA = 'solicitar-certificacion-medica'; // RRHH, desde Expediente
    // Consultar el inventario de medicinas, su kárdex y el stock bajo: todo el
    // Dispensario y el auxiliar de farmacia (PR 17a).
    case VER_INVENTARIO_MED = 'ver-inventario-med';
    // Leer las recetas: el médico y el odontólogo, las suyas; quien despacha,
    // todas (`AlcanceClinico`, PR 5). Va con la lista del personal médico,
    // que es el filtro de Despacho (PR 17a).
    case VER_RECETAS = 'ver-recetas';
    // La administración del Dispensario: anula o ve lo que registró otro
    // profesional. Hasta el PR 17 era `hasRole('admin-dispensario')` repetido
    // en cada controlador.
    case SUPERVISAR_DISPENSARIO = 'supervisar-dispensario';
    // Los exámenes: la bandeja, las órdenes, los resultados y los perfiles.
    // El médico, el odontólogo y la administración; cada uno ve su
    // especialidad (`AlcanceClinico`). Registrar resultados y órdenes es
    // `crear-consulta` (PR 17c).
    case VER_EXAMENES = 'ver-examenes';
    // El catálogo de exámenes, que mantiene la administración (PR 17c).
    case GESTIONAR_CATALOGO_EXAMENES = 'gestionar-catalogo-examenes';
    // Los procedimientos de cada pieza dental: el odontólogo (PR 4 y 17c).
    case REGISTRAR_ODONTOGRAMA = 'registrar-odontograma';
    // El tablero de gestión del Dispensario: la administración (PR 17d).
    case VER_TABLERO_DISPENSARIO = 'ver-tablero-dispensario';
    // Leer las fichas FEMO, su tablero y los exámenes por riesgo: el médico y
    // la administración. Llenarlas es `gestionar-fichas-sso-med` (PR 17d).
    case VER_FICHAS_SSO_MED = 'ver-fichas-sso-med';
    // El seguimiento de las certificaciones médicas y su cobertura: el
    // médico, la administración del Dispensario y Talento Humano (PR 17d).
    case VER_CERTIFICACIONES_MEDICAS = 'ver-certificaciones-medicas';
    // Los reportes del Dispensario; qué ve cada uno lo decide
    // `AlcanceReporte` (PR 17d).
    case VER_REPORTES_DISPENSARIO = 'ver-reportes-dispensario';

    // ── MÓDULO 12: Inventario TI ─────────────────────────────────
    case VER_INVENTARIO_TI = 'ver-inventario-ti';
    case GESTIONAR_INVENTARIO_TI = 'gestionar-inventario-ti';
    case DAR_BAJA_BIEN = 'dar-baja-bien';
    case REGISTRAR_MANTENIMIENTO = 'registrar-mantenimiento';
    // Préstamo de equipos (fase 2.1): aprobar, entregar, recibir y el préstamo
    // directo. Pedir uno desde el Portal va con `acceso-autoservicio`.
    case GESTIONAR_PRESTAMOS = 'gestionar-prestamos';

    // ── MÓDULO 13: Helpdesk ──────────────────────────────────────
    case CREAR_TICKET = 'crear-ticket';
    case VER_TICKET_PROPIO = 'ver-ticket-propio';
    case VER_TICKETS_TODOS = 'ver-tickets-todos';
    case GESTIONAR_TICKETS = 'gestionar-tickets';
    case ASIGNAR_TICKET = 'asignar-ticket';
    case GESTIONAR_TECNICOS = 'gestionar-tecnicos';
    case CONFIGURAR_SLA = 'configurar-sla';
    case GESTIONAR_BASE_CONOCIMIENTO = 'gestionar-base-conocimiento';
    // Las extensiones del directorio telefónico, que mantiene TIC
    // (docs/plan-roles-y-accesos.md, PR 11). Consultarlo no pide permiso.
    case GESTIONAR_DIRECTORIO = 'gestionar-directorio';

    // ── MÓDULO 14: Disciplinario ─────────────────────────────────
    // Leer el módulo; gestionar sumarios, vistos buenos y el reglamento
    // interno; y resolver un sumario, que impone la sanción. Solo admin-uath.
    // Hasta el 2026-10-28 lo decidía su rol en la ruta.
    case VER_SUMARIOS = 'ver-sumarios';
    case GESTIONAR_SUMARIOS = 'gestionar-sumarios';
    case REGISTRAR_SANCION = 'registrar-sancion';

    // ── MÓDULO 15: Capacitación ──────────────────────────────────
    // Módulo futuro: sin pantallas. Ningún código los comprueba todavía.
    case VER_PLAN_CAPACITACION = 'ver-plan-capacitacion';
    case GESTIONAR_PLAN_CAPACITACION = 'gestionar-plan-capacitacion';
    case INSCRIBIRSE_CURSO = 'inscribirse-curso';
    case GESTIONAR_CURSOS = 'gestionar-cursos';

    // ── MÓDULO 16: Actividades ───────────────────────────────────
    // Cada servidor ve solo las suyas, sin revisión del jefe (2026-10-12,
    // #431). Por eso se retiraron `ver-actividades-unidad` y
    // `aprobar-actividades` el 2026-10-28 (D9).
    case REGISTRAR_ACTIVIDADES = 'registrar-actividades';
    case EXPORTAR_INFORME_ACTIVIDADES = 'exportar-informe-actividades';

    // ── MÓDULO 17: Bienestar ─────────────────────────────────────
    case GESTIONAR_BIENESTAR = 'gestionar-bienestar';
    // Módulo futuro: la encuesta de clima laboral no existe todavía.
    case RESPONDER_ENCUESTA_CLIMA = 'responder-encuesta-clima';

    // ── MÓDULO 18: Reportería ────────────────────────────────────
    case VER_DASHBOARD_EJECUTIVO = 'ver-dashboard-ejecutivo';
    case GENERAR_REPORTES = 'generar-reportes';
    case EXPORTAR_REPORTES = 'exportar-reportes';

    // ── Comunicación Social ──────────────────────────────────────
    // docs/diseno-comunicacion-social.md, 7.3. «Mi credencial» del Portal va
    // con `acceso-autoservicio`, que ya tienen todos.
    case GESTIONAR_FOTOGRAFIAS = 'gestionar-fotografias';
    case GESTIONAR_CREDENCIALES = 'gestionar-credenciales';
    case VER_CREDENCIALES = 'ver-credenciales';
    // Los logos salen en todos los documentos: solo la persona designada (D2).
    case GESTIONAR_IDENTIDAD_INSTITUCIONAL = 'gestionar-identidad-institucional';

    // ── Noticias y comunicados de Talento Humano ────────────────
    // docs/diseno-noticias-comunicados.md, 7.3. Leerlos en el Portal va con
    // `acceso-autoservicio`.
    case REDACTAR_COMUNICADOS = 'redactar-comunicados';
    // Por rol solo admin-uath. A un asistente se le da como permiso adicional
    // desde Usuarios, a la persona concreta (D1).
    case PUBLICAR_COMUNICADOS = 'publicar-comunicados';
    case VER_LECTURAS_COMUNICADOS = 'ver-lecturas-comunicados';

    // ── ADMIN SISTEMA ────────────────────────────────────────────
    case GESTIONAR_USUARIOS = 'gestionar-usuarios';
    case ACTIVAR_USUARIO = 'activar-usuario';
    case GESTIONAR_ROLES = 'gestionar-roles';
    case VER_AUDITORIA = 'ver-auditoria';
    case CONFIGURAR_SISTEMA = 'configurar-sistema';
    case RESTABLECER_CONTRASENA = 'restablecer-contrasena';
}
