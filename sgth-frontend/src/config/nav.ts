import { ROUTES } from './routes';

export interface NavItem {
  label: string;
  href: string;
  icon: string;
  permiso?: string;
}

export interface NavGroup {
  label: string;
  items: NavItem[];
}

export const NAV_ADMIN: NavGroup[] = [
  {
    label: 'Principal',
    items: [
      { label: 'Dashboard', href: ROUTES.DASHBOARD.HOME, icon: 'IconHome' },
      { label: 'Servidores', href: ROUTES.DASHBOARD.SERVIDORES, icon: 'IconUsers' },
    ],
  },
  {
    label: 'Talento Humano',
    items: [
      { label: 'Estructura', href: ROUTES.TALENTO_HUMANO.ESTRUCTURA, icon: 'IconSitemap' },
      { label: 'Expediente', href: ROUTES.TALENTO_HUMANO.EXPEDIENTE, icon: 'IconFolder' },
      { label: 'Nómina', href: ROUTES.TALENTO_HUMANO.NOMINA, icon: 'IconCash' },
      { label: 'Asistencia', href: ROUTES.TALENTO_HUMANO.ASISTENCIA, icon: 'IconClock' },
      { label: 'Viáticos', href: ROUTES.TALENTO_HUMANO.VIATICOS, icon: 'IconPlane' },
    ],
  },
  {
    label: 'Bienestar',
    items: [
      { label: 'Dispensario', href: ROUTES.BIENESTAR.DISPENSARIO, icon: 'IconHeartbeat' },
      { label: 'SSO', href: ROUTES.BIENESTAR.SSO, icon: 'IconShield' },
      { label: 'Capacitación', href: ROUTES.BIENESTAR.CAPACITACION, icon: 'IconBook' },
      { label: 'Bienestar', href: ROUTES.BIENESTAR.BIENESTAR, icon: 'IconMoodSmile' },
    ],
  },
  {
    label: 'Procesos',
    items: [
      { label: 'Selección', href: ROUTES.PROCESOS.SELECCION, icon: 'IconUserPlus' },
      { label: 'Evaluación', href: ROUTES.PROCESOS.EVALUACION, icon: 'IconClipboardCheck' },
      { label: 'Disciplinario', href: ROUTES.PROCESOS.DISCIPLINARIO, icon: 'IconGavel' },
      { label: 'Inventario TI', href: ROUTES.PROCESOS.INVENTARIO_TI, icon: 'IconDevices' },
      { label: 'Helpdesk', href: ROUTES.PROCESOS.HELPDESK, icon: 'IconHeadset' },
      { label: 'SGD', href: ROUTES.PROCESOS.SGD, icon: 'IconFileText' },
    ],
  },
  {
    label: 'Análisis',
    items: [
      { label: 'Reportería', href: ROUTES.ANALISIS.REPORTERIA, icon: 'IconReportAnalytics' },
    ],
  },
];

export const NAV_SERVIDOR: NavGroup[] = [
  {
    label: 'Mi Cuenta',
    items: [
      { label: 'Mi Perfil', href: ROUTES.AUTOSERVICIO.MI_PERFIL, icon: 'IconUser' },
    ],
  },
  {
    label: 'Mis Solicitudes',
    items: [
      { label: 'Mis Permisos', href: ROUTES.AUTOSERVICIO.MIS_PERMISOS, icon: 'IconCalendarEvent' },
      { label: 'Mis Vacaciones', href: ROUTES.AUTOSERVICIO.MIS_VACACIONES, icon: 'IconBeach' },
      { label: 'Mis Marcaciones', href: ROUTES.AUTOSERVICIO.MIS_MARCACIONES, icon: 'IconClockRecord' },
    ],
  },
  {
    label: 'Mis Actividades',
    items: [
      { label: 'Mis Actividades', href: ROUTES.AUTOSERVICIO.MIS_ACTIVIDADES, icon: 'IconListCheck' },
    ],
  },
  {
    label: 'Soporte',
    items: [
      { label: 'Mis Tickets', href: ROUTES.AUTOSERVICIO.MIS_TICKETS, icon: 'IconTicket' },
    ],
  },
];

export function buildNavGroups(permisos: string[]): NavGroup[] {
  if (!permisos || permisos.length === 0) {
    return NAV_ADMIN;
  }

  const filteredGroups: NavGroup[] = [];

  for (const group of NAV_ADMIN) {
    const filteredItems = group.items.filter(
      (item) => !item.permiso || permisos.includes(item.permiso)
    );

    if (filteredItems.length > 0) {
      filteredGroups.push({ ...group, items: filteredItems });
    }
  }

  return filteredGroups;
}
