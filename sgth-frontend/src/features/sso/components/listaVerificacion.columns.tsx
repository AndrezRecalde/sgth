'use client'

import { Button } from '@mantine/core'
import { IconEdit } from '@tabler/icons-react'
import { StatusBadge } from '@/components/ui'
import { TIPO_NORMATIVA_OPTIONS } from '../schemas/normativaLegal.schema'
import { TONO_ESTADO_CUMPLIMIENTO, ESTADO_CUMPLIMIENTO_LABELS } from '../schemas/cumplimiento.schema'
import type { DataTableColumn } from 'mantine-datatable'
import type { FilaListaVerificacion } from '../services/tipos'

const etiquetaTipo = (valor: string) =>
  TIPO_NORMATIVA_OPTIONS.find((o) => o.value === valor)?.label ?? valor

/**
 * Columnas de la lista de verificación del cumplimiento normativo.
 *
 * `onRegistrar` sin valor es quien solo lee el módulo: la celda queda vacía,
 * igual que antes. Registrar el cumplimiento de una normativa es la única
 * acción de la fila y va como botón con su etiqueta, no dentro de un
 * `TableActions`: el motivo de la regla —tres o cuatro iconos sueltos por
 * quince filas— no se da con una sola acción, y meterla en un menú añade un
 * clic a lo único que se hace en esta pantalla.
 */
export function columnasListaVerificacion(
  onRegistrar?: (fila: FilaListaVerificacion) => void,
): DataTableColumn<FilaListaVerificacion>[] {
  return [
    { accessor: 'normativa.nombre', title: 'Normativa' },
    {
      accessor: 'normativa.tipo',
      title: 'Tipo',
      render: (fila) => (
        <StatusBadge>{etiquetaTipo(fila.normativa.tipo)}</StatusBadge>
      ),
    },
    {
      accessor: 'estado',
      title: 'Estado',
      render: (fila) => (
        <StatusBadge tone={TONO_ESTADO_CUMPLIMIENTO[fila.estado] ?? 'neutral'}>
          {ESTADO_CUMPLIMIENTO_LABELS[fila.estado] ?? fila.estado}
        </StatusBadge>
      ),
    },
    {
      accessor: 'acciones',
      title: '',
      width: 120,
      render: (fila) => onRegistrar ? (
        <Button
          size="xs"
          variant="subtle"
          leftSection={<IconEdit size={14} />}
          onClick={() => onRegistrar(fila)}
        >
          Registrar
        </Button>
      ) : null,
    },
  ]
}
