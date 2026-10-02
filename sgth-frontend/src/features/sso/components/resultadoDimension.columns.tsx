'use client'

import type { DataTableColumn } from 'mantine-datatable'
import type { ResultadoDimensionAgregado } from '../services/psicosocialService'

/** La clave del objeto `por_dimension` entra como campo para que la tabla la use de id. */
export type FilaDimension = ResultadoDimensionAgregado & { key: string }

/** Columnas del agregado por dimensión de una evaluación psicosocial. */
export const columnasResultadoDimension: DataTableColumn<FilaDimension>[] = [
  { accessor: 'etiqueta', title: 'Dimensión' },
  { accessor: 'bajo', title: 'Bajo', textAlign: 'center', width: 90 },
  { accessor: 'medio', title: 'Medio', textAlign: 'center', width: 90 },
  { accessor: 'alto', title: 'Alto', textAlign: 'center', width: 90 },
]
