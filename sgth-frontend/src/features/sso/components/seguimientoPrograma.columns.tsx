'use client'

import { Button } from '@mantine/core'
import { IconEdit } from '@tabler/icons-react'
import { StatusBadge } from '@/components/ui'
import { TONO_ACTIVIDAD_PROGRAMA, ESTADO_ACTIVIDAD_PROGRAMA_LABELS } from '../schemas/programaDrogas.schema'
import { formatFecha } from '@/lib/fecha'
import type { DataTableColumn } from 'mantine-datatable'
import type { FilaSeguimientoPrograma } from '../services/programaDrogasService'

/**
 * Columnas de la matriz de seguimiento del programa de prevención de drogas.
 *
 * `onRegistrar` sin valor es quien solo lee el módulo: la celda queda vacía,
 * igual que antes. Sobre el botón con su etiqueta en vez de un `TableActions`,
 * ver `listaVerificacion.columns.tsx`, que resuelve lo mismo.
 */
export function columnasSeguimientoPrograma(
  onRegistrar?: (fila: FilaSeguimientoPrograma) => void,
): DataTableColumn<FilaSeguimientoPrograma>[] {
  return [
    { accessor: 'actividad.nombre', title: 'Actividad' },
    {
      accessor: 'estado',
      title: 'Estado',
      width: 130,
      render: (fila) => (
        <StatusBadge tone={TONO_ACTIVIDAD_PROGRAMA[fila.estado] ?? 'neutral'}>
          {ESTADO_ACTIVIDAD_PROGRAMA_LABELS[fila.estado] ?? fila.estado}
        </StatusBadge>
      ),
    },
    {
      accessor: 'seguimiento.fecha_ejecucion',
      title: 'Fecha',
      width: 110,
      render: (fila) => formatFecha(fila.seguimiento?.fecha_ejecucion),
    },
    {
      accessor: 'acciones',
      title: '',
      width: 110,
      render: (fila) => onRegistrar ? (
        <Button size="xs" variant="subtle" leftSection={<IconEdit size={14} />} onClick={() => onRegistrar(fila)}>
          Registrar
        </Button>
      ) : null,
    },
  ]
}
