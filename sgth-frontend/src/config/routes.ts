export const ROUTES = {
  AUTH: {
    LOGIN: '/login',
    CAMBIAR_PASSWORD: '/cambiar-password',
  },
  DASHBOARD: {
    HOME: '/',
    SERVIDORES: '/servidores',
  },
  TALENTO_HUMANO: {
    ESTRUCTURA: '/estructura',
    EXPEDIENTE: '/expediente',
    NOMINA: '/nomina',
    ASISTENCIA: '/asistencia',
    VIATICOS: '/viaticos',
  },
  BIENESTAR: {
    DISPENSARIO: '/dispensario',
    SSO: '/sso',
    CAPACITACION: '/capacitacion',
    BIENESTAR: '/bienestar',
  },
  PROCESOS: {
    SELECCION: '/seleccion',
    EVALUACION: '/evaluacion',
    DISCIPLINARIO: '/disciplinario',
    INVENTARIO_TI: '/inventario-ti',
    HELPDESK: '/helpdesk',
    SGD: '/sgd',
  },
  ANALISIS: {
    REPORTERIA: '/reporteria',
  },
  AUTOSERVICIO: {
    MI_PERFIL: '/autoservicio/mi-perfil',
    MIS_PERMISOS: '/autoservicio/mis-permisos',
    MIS_VACACIONES: '/autoservicio/mis-vacaciones',
    MIS_MARCACIONES: '/autoservicio/mis-marcaciones',
    MIS_ACTIVIDADES: '/autoservicio/mis-actividades',
    MIS_TICKETS: '/autoservicio/mis-tickets',
  },
} as const;
