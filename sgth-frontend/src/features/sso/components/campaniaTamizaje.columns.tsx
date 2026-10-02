'use client'

import { Text } from '@mantine/core'
import { StatusBadge, TableActions, type TableAction } from '@/components/ui'
import type { DataTableColumn } from 'mantine-datatable'

/**
 * Lo que las columnas necesitan de una campaña de tamizaje.
 *
 * `CampaniaAssist` y `CampaniaPsicosocial` son hoy la misma interfaz campo por
 * campo, y las dos tablas eran el mismo bloque de columnas copiado: mismos
 * accesores, mismos anchos, mismos textos. Pedir la forma y no el tipo
 * concreto deja un solo archivo para las dos pantallas, así que un cambio en
 * la tabla —una columna nueva, otro ancho— se hace una vez.
 */
export interface CampaniaTamizaje {
  id: number
  periodo: string
  codigo_acceso: string
  activa: boolean
  unidad_administrativa?: { id: number; nombre: string } | null
  respuestas_count?: number
}

/**
 * Columnas del listado de campañas de tamizaje (ASSIST y psicosocial).
 *
 * Las acciones llegan desde la vista: el enlace público, los resultados y el
 * cierre dependen de la ruta, de las mutaciones y del permiso, que son de
 * cada pantalla.
 */
export function columnasCampaniaTamizaje<T extends CampaniaTamizaje>(
  accionesDe: (campania: T) => TableAction[],
): DataTableColumn<T>[] {
  return [
    { accessor: 'periodo', title: 'Período', width: 100 },
    {
      accessor: 'unidad_administrativa',
      title: 'Unidad',
      render: (c) => c.unidad_administrativa?.nombre ?? 'Toda la institución',
    },
    {
      accessor: 'codigo_acceso',
      title: 'Código',
      width: 120,
      render: (c) => <Text ff="monospace" size="sm">{c.codigo_acceso}</Text>,
    },
    {
      accessor: 'activa',
      title: 'Estado',
      width: 100,
      render: (c) => (
        <StatusBadge tone={c.activa ? 'success' : 'neutral'}>
          {c.activa ? 'Abierta' : 'Cerrada'}
        </StatusBadge>
      ),
    },
    {
      accessor: 'respuestas_count',
      title: 'Respuestas',
      width: 100,
      textAlign: 'center',
      render: (c) => c.respuestas_count ?? 0,
    },
    {
      accessor: 'acciones',
      title: '',
      width: 50,
      render: (c) => <TableActions actions={accionesDe(c)} />,
    },
  ]
}
