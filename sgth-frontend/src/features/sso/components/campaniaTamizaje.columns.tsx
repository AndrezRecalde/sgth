'use client'

import { Text } from '@mantine/core'
import { StatusBadge, TableActions, type TableAction } from '@/components/ui'
import {
  ESTADO_CAMPANIA_LABELS, TONO_ESTADO_CAMPANIA, type EstadoCampaniaSso,
} from '../constants/campania'
import { formatFecha } from '@/lib/fecha'
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
  fecha_apertura: string
  fecha_cierre: string | null
  /** Si alguien la cerró a mano. Lo que se pinta es `estado_campania`. */
  activa: boolean
  /** La ventana real, calculada por el backend a partir de las dos fechas. */
  estado_campania: EstadoCampaniaSso
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
      accessor: 'fecha_apertura',
      title: 'Ventana',
      width: 180,
      // Las dos fechas a la vista: el estado de al lado se deduce de ellas, y
      // sin verlas no hay forma de entender por qué una campaña sale
      // «Programada» ni hasta cuándo se puede repartir el enlace.
      render: (c) => (
        <Text size="sm">
          {formatFecha(c.fecha_apertura)}
          {c.fecha_cierre ? ` – ${formatFecha(c.fecha_cierre)}` : ' – sin cierre'}
        </Text>
      ),
    },
    {
      accessor: 'estado_campania',
      title: 'Estado',
      width: 120,
      // `estado_campania` y no `activa`: la columna pintaba «Abierta» para una
      // campaña con la apertura en el futuro y para una con el cierre ya
      // pasado, mientras el enlace público rechazaba a quien entraba.
      render: (c) => (
        <StatusBadge tone={TONO_ESTADO_CAMPANIA[c.estado_campania]}>
          {ESTADO_CAMPANIA_LABELS[c.estado_campania]}
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
