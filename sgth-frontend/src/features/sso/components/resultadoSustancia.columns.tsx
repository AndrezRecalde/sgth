'use client'

import { CountBadge } from '@/components/ui'
import { TONO_RIESGO_ASSIST } from '../schemas/assist.schema'
import type { DataTableColumn } from 'mantine-datatable'
import type { ResultadoSustanciaAgregado } from '../services/assistService'

/** La clave del objeto `por_sustancia` entra como campo para que la tabla la use de id. */
export type FilaSustancia = ResultadoSustanciaAgregado & { key: string }

/** Columnas del agregado por sustancia de una campaña ASSIST. */
export const columnasResultadoSustancia: DataTableColumn<FilaSustancia>[] = [
  { accessor: 'etiqueta', title: 'Sustancia' },
  { accessor: 'total_consumieron', title: 'Consumieron', textAlign: 'center', width: 100 },
  {
    accessor: 'bajo',
    title: 'Bajo',
    textAlign: 'center',
    width: 80,
    render: (f) => <CountBadge tone={TONO_RIESGO_ASSIST.bajo}>{f.bajo}</CountBadge>,
  },
  {
    accessor: 'moderado',
    title: 'Moderado',
    textAlign: 'center',
    width: 90,
    render: (f) => <CountBadge tone={TONO_RIESGO_ASSIST.moderado}>{f.moderado}</CountBadge>,
  },
  {
    accessor: 'alto',
    title: 'Alto',
    textAlign: 'center',
    width: 80,
    render: (f) => <CountBadge tone={TONO_RIESGO_ASSIST.alto}>{f.alto}</CountBadge>,
  },
]
