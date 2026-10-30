<?php

namespace Database\Seeders;

use App\Enums\Permiso;
use App\Enums\Rol;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolPermisoSeeder extends Seeder
{
    /** Lo que hace el técnico de TIC (docs/diseno-subsistema-tic.md, 4.4). */
    public const PERMISOS_TECNICO_TIC = [
        Permiso::GESTIONAR_TICKETS,
        Permiso::GESTIONAR_BASE_CONOCIMIENTO,
        Permiso::VER_INVENTARIO_TI,
        Permiso::GESTIONAR_INVENTARIO_TI,
        Permiso::REGISTRAR_MANTENIMIENTO,
        Permiso::GESTIONAR_PRESTAMOS,
        // A quién se le registra un ticket, se le entrega un equipo o se le
        // presta uno.
        Permiso::BUSCAR_SERVIDORES,
        // Las extensiones son de la central telefónica, que lleva TIC.
        Permiso::GESTIONAR_DIRECTORIO,
    ];

    /** El jefe de TIC: lo del técnico, más asignar, configurar y dar de baja. */
    public const PERMISOS_JEFE_TIC = [
        ...self::PERMISOS_TECNICO_TIC,
        Permiso::VER_TICKETS_TODOS,
        Permiso::ASIGNAR_TICKET,
        Permiso::GESTIONAR_TECNICOS,
        Permiso::CONFIGURAR_SLA,
        Permiso::DAR_BAJA_BIEN,
    ];

    /**
     * El equipo de Comunicación Social: toma las fotos e imprime las
     * credenciales (docs/diseno-comunicacion-social.md, 7.3).
     */
    public const PERMISOS_COMUNICACION_SOCIAL = [
        Permiso::GESTIONAR_FOTOGRAFIAS,
        Permiso::GESTIONAR_CREDENCIALES,
        Permiso::VER_CREDENCIALES,
    ];

    /**
     * La persona designada para los logos y los datos institucionales. Es un
     * rol aparte y no un permiso del equipo, porque un logo equivocado sale en
     * todos los documentos (D2). Si también toma fotos, tiene los dos roles.
     */
    public const PERMISOS_RESPONSABLE_IDENTIDAD = [
        Permiso::GESTIONAR_IDENTIDAD_INSTITUCIONAL,
    ];

    /**
     * Las noticias y comunicados de Talento Humano
     * (docs/diseno-noticias-comunicados.md, 7.3). Publicar no va en el rol del
     * asistente: se le da a la persona como permiso adicional (D1).
     */
    public const PERMISOS_COMUNICADOS = [
        Permiso::REDACTAR_COMUNICADOS,
        Permiso::PUBLICAR_COMUNICADOS,
        Permiso::VER_LECTURAS_COMUNICADOS,
    ];

    /**
     * El técnico de Asistencia: marcaciones, permisos, vacaciones, licencias,
     * períodos y consolidado de toda la institución, con sus acciones, y buscar
     * a la persona en los formularios. Nada más de Talento Humano (decisión del
     * 2026-10-30). Validar enfermedad y calamidad sigue siendo de Trabajo Social.
     */
    public const PERMISOS_TECNICO_ASISTENCIA = [
        Permiso::VER_ASISTENCIA_TODOS,
        Permiso::VER_PERMISOS_TODOS,
        Permiso::REGISTRAR_PERMISOS_SERVIDORES,
        Permiso::CONFIRMAR_RECEPCION,
        Permiso::ANULAR_PERMISO_PENDIENTE,
        Permiso::ANULAR_PERMISO,
        Permiso::APROBAR_PERMISO_SIRHA7,
        Permiso::VER_VACACIONES_UNIDAD,
        Permiso::GESTIONAR_VACACIONES,
        Permiso::APROBAR_VACACIONES,
        Permiso::GESTIONAR_LICENCIAS,
        Permiso::BUSCAR_SERVIDORES,
    ];

    /**
     * El técnico de seguridad y salud ocupacional: todo Riesgos laborales, y
     * buscar a la persona de un accidente o una entrega de EPP. Nada más de
     * Talento Humano (docs/plan-roles-y-accesos.md, PR 12).
     */
    public const PERMISOS_TECNICO_SSO = [
        Permiso::GESTIONAR_SSO,
        Permiso::VER_REPORTES_SSO,
        Permiso::REGISTRAR_ACCIDENTE,
        Permiso::BUSCAR_SERVIDORES,
    ];

    /**
     * El Expediente de Talento Humano, para admin-uath y asistente-uath: verlo
     * entero, gestionar sus historiales, subir documentos y llevar las cargas
     * familiares. Hasta el 2026-10-28 lo decidía el rol en las rutas y en
     * `ServidorPolicy` (docs/revision-permisos-sin-uso.md, 3.2). Borrar un
     * documento sigue siendo de admin-uath, por su rol.
     */
    public const PERMISOS_EXPEDIENTE_TH = [
        Permiso::VER_EXPEDIENTE_TODOS,
        Permiso::GESTIONAR_EXPEDIENTE,
        Permiso::CARGAR_DOCUMENTOS,
        Permiso::GESTIONAR_CARGAS_FAMILIARES,
    ];

    /**
     * Régimen disciplinario, solo para admin-uath: leer, abrir y hacer avanzar
     * los trámites, y resolver un sumario imponiendo la sanción. Hasta el
     * 2026-10-28 lo decidía su rol en la ruta (docs/revision-permisos-sin-
     * uso.md, D8).
     */
    public const PERMISOS_DISCIPLINARIO = [
        Permiso::VER_SUMARIOS,
        Permiso::GESTIONAR_SUMARIOS,
        Permiso::REGISTRAR_SANCION,
    ];

    /** Los permisos de cualquier servidor público de la institución. */
    public const PERMISOS_BASE = [
        Permiso::VER_EXPEDIENTE_PROPIO,
        Permiso::VER_ASISTENCIA_PROPIA,
        Permiso::CREAR_PERMISO,
        Permiso::VER_PERMISOS,
        Permiso::ACCESO_AUTOSERVICIO,
        Permiso::CAMBIAR_CONTRASENA,
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

    /*
    | El Dispensario pasa de rol a permiso por partes (docs/plan-roles-y-
    | accesos.md, PR 17). Cada rol tiene su constante, como los de TIC, para
    | que las pruebas creen el rol con lo mismo que le da este seeder
    | (`rolesDelDispensario()` en tests/Support/RolesFixtures.php).
    */

    public const PERMISOS_MEDICO = [
        Permiso::VER_AGENDA_DISPENSARIO,
        Permiso::VER_HISTORIA_CLINICA,
        Permiso::CREAR_CONSULTA,
        Permiso::EMITIR_RECETA,
        // Farmacia (PR 17a): consulta el inventario y sus recetas.
        Permiso::VER_INVENTARIO_MED,
        Permiso::VER_RECETAS,
        // Exámenes (PR 17c): la bandeja, las órdenes y los resultados.
        Permiso::VER_EXAMENES,
        // Reportes (PR 17d). La salud ocupacional es de `medico-ocupacional`.
        Permiso::VER_REPORTES_DISPENSARIO,
    ];

    /**
     * El médico que hace las evaluaciones ocupacionales: inicia, llena y
     * dictamina la FEMO, y ve las solicitudes y la cobertura. Es un rol
     * complementario, sin los permisos base ni los de consulta: se da junto
     * con `medico`, que sigue decidiendo la cola, la especialidad y «Mi
     * jornada» (decisión del 2026-10-29). Hasta entonces lo tenía todo médico.
     */
    public const PERMISOS_MEDICO_OCUPACIONAL = [
        Permiso::VER_FICHAS_SSO_MED,
        Permiso::GESTIONAR_FICHAS_SSO_MED,
        Permiso::VER_CERTIFICACIONES_MEDICAS,
    ];

    public const PERMISOS_ODONTOLOGO = [
        Permiso::VER_AGENDA_DISPENSARIO,
        Permiso::VER_HISTORIA_CLINICA,
        Permiso::CREAR_CONSULTA,
        Permiso::EMITIR_RECETA,
        Permiso::VER_INVENTARIO_MED,
        Permiso::VER_RECETAS,
        Permiso::VER_EXAMENES,
        // El odontograma lo registra solo el odontólogo (PR 4).
        Permiso::REGISTRAR_ODONTOGRAMA,
        Permiso::VER_REPORTES_DISPENSARIO,
    ];

    public const PERMISOS_ENFERMERA = [
        Permiso::VER_AGENDA_DISPENSARIO,
        Permiso::VER_HISTORIA_CLINICA,
        Permiso::DESPACHAR_MEDICAMENTO,
        Permiso::VER_INVENTARIO_MED,
        Permiso::VER_RECETAS,
        // Agenda y triaje (PR 17b): lleva la cola de todos y registra el
        // triaje y los servicios de enfermería.
        Permiso::GESTIONAR_AGENDA,
        Permiso::REGISTRAR_TRIAJE,
        Permiso::VER_REPORTES_DISPENSARIO,
    ];

    public const PERMISOS_ADMIN_DISPENSARIO = [
        Permiso::VER_AGENDA_DISPENSARIO,
        Permiso::VER_HISTORIA_CLINICA,
        Permiso::DESPACHAR_MEDICAMENTO,
        Permiso::VER_INVENTARIO_MED,
        Permiso::VER_RECETAS,
        // El catálogo de medicamentos, el ajuste de stock y las adquisiciones.
        Permiso::GESTIONAR_INVENTARIO_MED,
        // Anula o ve lo que registró otro profesional.
        Permiso::SUPERVISAR_DISPENSARIO,
        Permiso::GESTIONAR_AGENDA,
        Permiso::REGISTRAR_TRIAJE,
        Permiso::VER_EXAMENES,
        Permiso::GESTIONAR_CATALOGO_EXAMENES,
        // Tablero de gestión, salud ocupacional (lectura) y reportes (PR 17d).
        Permiso::VER_TABLERO_DISPENSARIO,
        Permiso::VER_FICHAS_SSO_MED,
        Permiso::VER_CERTIFICACIONES_MEDICAS,
        Permiso::VER_REPORTES_DISPENSARIO,
    ];

    /**
     * Toda la Farmacia, sin nada clínico: el inventario y el mostrador
     * (docs/plan-roles-y-accesos.md, PR 13) y, desde el 2026-10-29, también
     * el catálogo de medicamentos, los ajustes, las adquisiciones y los dos
     * reportes de Farmacia (existencias y movimiento). Sin
     * `ver-historia-clinica` ni la agenda, a diferencia de Enfermería.
     */
    public const PERMISOS_AUXILIAR_FARMACIA = [
        Permiso::DESPACHAR_MEDICAMENTO,
        Permiso::VER_INVENTARIO_MED,
        Permiso::VER_RECETAS,
        Permiso::GESTIONAR_INVENTARIO_MED,
        // Solo los de Farmacia: su perfil en AlcanceReporte no ve otros.
        Permiso::VER_REPORTES_DISPENSARIO,
    ];

    public function run(): void
    {
        // 1. Crear todos los permisos del Enum
        foreach (Permiso::cases() as $permiso) {
            Permission::firstOrCreate([
                'name' => $permiso->value,
                'guard_name' => 'sanctum',
            ]);
        }

        $permisosBase = self::PERMISOS_BASE;

        // 2. Crear roles y asignar permisos según la matriz

        // ADMIN_TI
        $this->crearRol(Rol::ADMIN_TI, array_merge($permisosBase, [
            Permiso::ACTIVAR_USUARIO,
            Permiso::GESTIONAR_USUARIOS,
            Permiso::GESTIONAR_ROLES,
            Permiso::RESTABLECER_CONTRASENA,
            Permiso::VER_AUDITORIA,
            Permiso::CONFIGURAR_SISTEMA,
            // Todo lo de TIC, como el jefe. Las rutas lo piden por permiso y
            // la pantalla de Usuarios debe mostrar lo que el rol puede hacer
            // (docs/diseno-subsistema-tic.md, 4.4).
            ...self::PERMISOS_JEFE_TIC,
            // Todo lo de Comunicación, por la misma razón (D2).
            ...self::PERMISOS_COMUNICACION_SOCIAL,
            ...self::PERMISOS_RESPONSABLE_IDENTIDAD,
            ...self::PERMISOS_COMUNICADOS,
            // Carga inicial de servidores. Se deja en TI y no en UATH porque
            // es una vía excepcional que se revoca al terminar la migración:
            // durante ese lapso se le concede también a quien la ejecute.
            Permiso::VINCULAR_SERVIDOR_INICIAL,
        ]));

        // ADMIN_UATH. Sin `activar-usuario` desde el 2026-10-29: las cuentas
        // las activa y desactiva TIC desde Usuarios, y con él Talento Humano
        // podía desactivar cualquiera —también las de TIC— por el API, sin
        // pantalla.
        $this->crearRol(Rol::ADMIN_UATH, array_merge($permisosBase, [
            Permiso::VER_ESTRUCTURA,
            Permiso::GESTIONAR_PUESTOS,
            Permiso::GESTIONAR_ORGANIGRAMA,
            Permiso::GESTIONAR_DISTRIBUTIVO,
            ...self::PERMISOS_EXPEDIENTE_TH,
            Permiso::REGISTRAR_FICHA_SERVIDOR,
            Permiso::BUSCAR_SERVIDORES,
            // El trámite entero de una acción de personal (diseño, 6.3).
            Permiso::PREPARAR_ACCION_PERSONAL,
            Permiso::SUSCRIBIR_ACCION_PERSONAL,
            Permiso::REGISTRAR_ACCION_PERSONAL,
            Permiso::NOTIFICAR_ACCION_PERSONAL,
            Permiso::ANULAR_ACCION_PERSONAL,
            // Corregir a mano la fecha de ingreso (fase 1.5).
            Permiso::CORREGIR_DATOS_LABORALES,
            // Las novedades del mes, y su entrega a Financiero (fase 7.3a).
            Permiso::VER_NOVEDADES_NOMINA,
            Permiso::ENTREGAR_NOVEDADES_NOMINA,
            Permiso::VER_ASISTENCIA_UNIDAD,
            Permiso::VER_ASISTENCIA_TODOS,
            Permiso::VER_PERMISOS_TODOS,
            Permiso::REGISTRAR_PERMISOS_SERVIDORES,
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
            // Consulta los viáticos; los opera Financiero.
            Permiso::VER_VIATICOS_TODOS,
            Permiso::GESTIONAR_SSO,
            ...self::PERMISOS_DISCIPLINARIO,
            Permiso::GESTIONAR_PLAN_CAPACITACION,
            Permiso::GESTIONAR_CURSOS,
            Permiso::GESTIONAR_BIENESTAR,
            Permiso::VER_DASHBOARD_EJECUTIVO,
            Permiso::GENERAR_REPORTES,
            Permiso::EXPORTAR_REPORTES,
            Permiso::VER_AUDITORIA,
            Permiso::SOLICITAR_CERTIFICACION_MEDICA,
            // El seguimiento y la cobertura de las certificaciones (PR 17d).
            Permiso::VER_CERTIFICACIONES_MEDICAS,
            Permiso::ANULAR_PERMISO_PENDIENTE,
            Permiso::APROBAR_PERMISO_SIRHA7,
            Permiso::GESTIONAR_LICENCIAS,
            // Opera Recepción y Trabajo Social cuando no hay nadie: antes lo
            // hacía por su rol en la ruta, ahora con los permisos.
            Permiso::CONFIRMAR_RECEPCION,
            Permiso::VALIDAR_TRABAJO_SOCIAL,
            // Consulta las credenciales; las imprime Comunicación Social.
            Permiso::VER_CREDENCIALES,
            // Redacta, publica y sigue las noticias y comunicados.
            ...self::PERMISOS_COMUNICADOS,
        ]));

        // ASISTENTE_UATH
        $this->crearRol(Rol::ASISTENTE_UATH, array_merge($permisosBase, [
            Permiso::VER_ESTRUCTURA,
            Permiso::GESTIONAR_PUESTOS,
            // También sube documentos, por decisión de Talento Humano del
            // 2026-10-03; si se le quita, se hace desde Usuarios.
            ...self::PERMISOS_EXPEDIENTE_TH,
            Permiso::BUSCAR_SERVIDORES,
            // Prepara y notifica; suscribir, registrar y anular son del
            // director (TH 22).
            Permiso::PREPARAR_ACCION_PERSONAL,
            Permiso::NOTIFICAR_ACCION_PERSONAL,
            // Las ve; entregarlas es del director (fase 7.3a).
            Permiso::VER_NOVEDADES_NOMINA,
            Permiso::VER_ASISTENCIA_UNIDAD,
            Permiso::VER_ASISTENCIA_TODOS,
            Permiso::VER_PERMISOS_TODOS,
            Permiso::REGISTRAR_PERMISOS_SERVIDORES,
            Permiso::VER_VACACIONES_UNIDAD,
            Permiso::GENERAR_REPORTES,
            Permiso::SOLICITAR_CERTIFICACION_MEDICA,
            Permiso::VER_CERTIFICACIONES_MEDICAS,
            Permiso::GESTIONAR_SSO,
            Permiso::VER_REPORTES_SSO,
            Permiso::REGISTRAR_ACCIDENTE,
            // Anula pendientes, pero no revierte confirmaciones: eso devuelve
            // saldo y sigue siendo de admin-uath (`anular-permiso`).
            Permiso::ANULAR_PERMISO_PENDIENTE,
            Permiso::APROBAR_PERMISO_SIRHA7,
            Permiso::GESTIONAR_LICENCIAS,
            // Confirma y rechaza el documento físico, como hacía por su rol en
            // la ruta de confirmar.
            Permiso::CONFIRMAR_RECEPCION,
            // Redacta y sigue las noticias y comunicados. Publicar no: se le
            // da a la persona como permiso adicional desde Usuarios (D1).
            Permiso::REDACTAR_COMUNICADOS,
            Permiso::VER_LECTURAS_COMUNICADOS,
        ]));

        // Aquí estaban ANALISTA_UATH y MAXIMA_AUTORIDAD, retirados el
        // 2026-10-26 (docs/plan-roles-y-accesos.md, PR 7): ninguno tenía
        // usuarios ni subsistema donde entrar. Su lectura la conservan
        // admin-uath, asistente-uath y el auditor.

        // DIRECTOR
        $this->crearRol(Rol::DIRECTOR, array_merge($permisosBase, [
            Permiso::VER_ESTRUCTURA,
            Permiso::VER_ASISTENCIA_UNIDAD,
            Permiso::VER_VACACIONES_UNIDAD,
            Permiso::VER_DASHBOARD_EJECUTIVO,
            Permiso::GENERAR_REPORTES,
        ]));

        // JEFE_UNIDAD
        $this->crearRol(Rol::JEFE_UNIDAD, array_merge($permisosBase, [
            Permiso::VER_ESTRUCTURA,
            Permiso::VER_ASISTENCIA_UNIDAD,
            Permiso::VER_VACACIONES_UNIDAD,
        ]));

        // SERVIDOR: lo de todo servidor. La marcación en línea no va por rol:
        // `marcar-en-linea` se le da a la persona desde Usuarios.
        $this->crearRol(Rol::SERVIDOR, $permisosBase);

        // RECEPCION
        $this->crearRol(Rol::RECEPCION, array_merge($permisosBase, [
            Permiso::CONFIRMAR_RECEPCION,
        ]));

        // TRABAJO_SOCIAL
        $this->crearRol(Rol::TRABAJO_SOCIAL, array_merge($permisosBase, [
            Permiso::VALIDAR_TRABAJO_SOCIAL,
        ]));

        // TECNICO_ASISTENCIA
        $this->crearRol(Rol::TECNICO_ASISTENCIA, array_merge($permisosBase, self::PERMISOS_TECNICO_ASISTENCIA));

        // FINANCIERO
        // Gestión Financiera: aprueba las solicitudes y los vuelos, entrega el
        // anticipo, revisa la liquidación y la contabiliza.
        $this->crearRol(Rol::FINANCIERO, array_merge($permisosBase, [
            Permiso::VER_VIATICOS_TODOS,
            Permiso::APROBAR_VIATICO,
            Permiso::GESTIONAR_VIATICOS,
            Permiso::LIQUIDAR_VIATICO,
            Permiso::GESTIONAR_TARIFAS_VIATICO,
            // Las novedades del mes no: le llegan por correo, en Excel, cuando
            // Talento Humano entrega el mes (PR 8 del plan de roles).
        ]));

        // El Dispensario, de sus constantes.
        $this->crearRol(Rol::MEDICO, array_merge($permisosBase, self::PERMISOS_MEDICO));
        // Complementario de `medico`: sin los permisos base, que ya trae aquel.
        $this->crearRol(Rol::MEDICO_OCUPACIONAL, self::PERMISOS_MEDICO_OCUPACIONAL);
        $this->crearRol(Rol::ODONTOLOGO, array_merge($permisosBase, self::PERMISOS_ODONTOLOGO));
        $this->crearRol(Rol::ENFERMERA, array_merge($permisosBase, self::PERMISOS_ENFERMERA));
        $this->crearRol(Rol::ADMIN_DISPENSARIO, array_merge($permisosBase, self::PERMISOS_ADMIN_DISPENSARIO));
        $this->crearRol(Rol::AUXILIAR_FARMACIA, array_merge($permisosBase, self::PERMISOS_AUXILIAR_FARMACIA));

        // TECNICO_DTIC: atiende su cola y la de nadie, y lleva el inventario.
        // Asignar tickets y ver los de todos es del jefe (diseño TIC, 4.4).
        $this->crearRol(Rol::TECNICO_DTIC, array_merge($permisosBase, self::PERMISOS_TECNICO_TIC));

        // JEFE_TIC
        $this->crearRol(Rol::JEFE_TIC, array_merge($permisosBase, self::PERMISOS_JEFE_TIC));

        // COMUNICACION_SOCIAL
        $this->crearRol(Rol::COMUNICACION_SOCIAL, array_merge($permisosBase, self::PERMISOS_COMUNICACION_SOCIAL));

        // RESPONSABLE_IDENTIDAD
        $this->crearRol(Rol::RESPONSABLE_IDENTIDAD, array_merge($permisosBase, self::PERMISOS_RESPONSABLE_IDENTIDAD));

        // TECNICO_SSO
        $this->crearRol(Rol::TECNICO_SSO, array_merge($permisosBase, self::PERMISOS_TECNICO_SSO));

        // AUDITOR
        $this->crearRol(Rol::AUDITOR, array_merge($permisosBase, [
            Permiso::VER_ESTRUCTURA,
            // Sin `ver-expediente-todos`: el Expediente lo abre Talento Humano
            // y el auditor nunca entró (docs/revision-permisos-sin-uso.md, D2).
            Permiso::VER_NOVEDADES_NOMINA,
            Permiso::VER_ASISTENCIA_UNIDAD,
            Permiso::VER_ASISTENCIA_TODOS,
            Permiso::VER_PERMISOS_TODOS,
            Permiso::VER_VACACIONES_UNIDAD,
            Permiso::VER_AUDITORIA,
            Permiso::VER_DASHBOARD_EJECUTIVO,
            Permiso::GENERAR_REPORTES,
            Permiso::VER_REPORTES_SSO,
            // El inventario de equipos, solo para consultarlo.
            Permiso::VER_INVENTARIO_TI,
            // Las credenciales, solo para consultarlas.
            Permiso::VER_CREDENCIALES,
            // Quién leyó y confirmó cada comunicado, solo para consultarlo.
            Permiso::VER_LECTURAS_COMUNICADOS,
            // Para filtrar por servidor en Accidentes, EPP, Consolidado y
            // Períodos.
            Permiso::BUSCAR_SERVIDORES,
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
