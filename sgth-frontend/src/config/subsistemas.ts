import type { Icon } from '@tabler/icons-react'
import {
  IconUsers,
  IconBuildingHospital,
  IconUserCircle,
} from '@tabler/icons-react'
import { ROUTES, type Subsistema } from './routes'

/**
 * Configuración única de los tres subsistemas del SGTH.
 *
 * El acento visual del shell (sidebar, estado activo, foco, breadcrumb) se
 * deriva de `color`: el AppShell publica `--sgth-accent-*` a partir de esta
 * tabla, de modo que un solo CSS sirve para los tres subsistemas.
 *
 * Antes esta información vivía duplicada en `Sidebar.tsx` (sin usarse) y en
 * `AppGridSelector.tsx`. Cualquier subsistema nuevo se agrega SOLO aquí.
 */
export interface SubsistemaConfig {
  /** Nombre corto, para el conmutador de aplicaciones. */
  label: string
  /** Nombre completo, para la cabecera del sidebar. */
  nombre: string
  /** Una línea que explica de qué trata el subsistema. */
  descripcion: string
  icon: Icon
  /** Nombre de color registrado en el tema de Mantine. */
  color: 'emerald' | 'ocean' | 'amethyst'
  home: string
  /** Prefijo de ruta que identifica al subsistema. */
  prefix: string
}

export const SUBSISTEMAS: Record<Subsistema, SubsistemaConfig> = {
  sgth: {
    label: 'SGTH',
    nombre: 'Talento Humano',
    descripcion: 'Expedientes, nómina y asistencia',
    icon: IconUsers,
    color: 'emerald',
    home: ROUTES.SGTH.HOME,
    prefix: '/sgth',
  },
  salud: {
    label: 'Dispensario',
    nombre: 'Dispensario Médico',
    descripcion: 'Atención médica y ocupacional',
    icon: IconBuildingHospital,
    color: 'ocean',
    home: ROUTES.SALUD.HOME,
    prefix: '/salud',
  },
  portal: {
    label: 'Portal',
    nombre: 'Portal del Servidor',
    descripcion: 'Mi espacio personal',
    icon: IconUserCircle,
    color: 'amethyst',
    home: ROUTES.PORTAL.HOME,
    prefix: '/portal',
  },
}

export const SUBSISTEMAS_ORDEN: Subsistema[] = ['sgth', 'salud', 'portal']

/** Deduce el subsistema activo a partir del pathname. `sgth` es el fallback. */
export function getSubsistema(pathname: string): Subsistema {
  const match = SUBSISTEMAS_ORDEN.find((key) =>
    pathname.startsWith(SUBSISTEMAS[key].prefix),
  )
  return match ?? 'sgth'
}
