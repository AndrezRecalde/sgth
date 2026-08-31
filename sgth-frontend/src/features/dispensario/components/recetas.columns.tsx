'use client'

import { Stack, Text } from '@mantine/core'
import { IconCheck, IconEye } from '@tabler/icons-react'
import { StatusBadge, TableActions } from '@/components/ui'
import type { SemanticTone } from '@/config/design.tokens'
import type { DataTableColumn } from 'mantine-datatable'
import type { RecetaMedica } from '../services/recetaService'

const TONO_ESTADO: Record<string, SemanticTone> = {
  pendiente:           'warning',
  despachada_parcial:  'info',
  despachada_completa: 'success',
  anulada:             'danger',
}

const ESTADO_LABELS: Record<string, string> = {
  pendiente:           'Pendiente',
  despachada_parcial:  'Parcial',
  despachada_completa: 'Completada',
  anulada:             'Anulada',
}

export function getNombrePaciente(r: RecetaMedica): string {
  const historia = r.consulta_medica?.historia_clinica
  if (historia?.servidor) {
    return `${historia.servidor.nombre} ${historia.servidor.apellido}`
  }
  if (historia?.carga_familiar) {
    return `${historia.carga_familiar.nombres} ${historia.carga_familiar.apellidos}`
  }
  return '—'
}

interface ColumnActions {
  /** Abre el modal de despacho, o el detalle si la receta ya está cerrada. */
  onAbrir: (receta: RecetaMedica) => void
}

export function getRecetasColumns(
  { onAbrir }: ColumnActions
): DataTableColumn<RecetaMedica>[] {
  return [
    {
      accessor: 'fecha_emision',
      title:    'Fecha emisión',
      width:    130,
      render: (r) => (
        <Text size="sm">
          {new Date(r.fecha_emision).toLocaleDateString('es-EC', {
            day: '2-digit', month: 'short', year: 'numeric',
          })}
        </Text>
      ),
    },
    {
      accessor: 'paciente',
      title:    'Paciente',
      render: (r) => (
        <Stack gap={0}>
          <Text size="sm" fw={500}>{getNombrePaciente(r)}</Text>
          <Text size="xs" c="dimmed">
            {r.consulta_medica?.historia_clinica?.servidor
              ? 'Servidor' : 'Familiar'}
          </Text>
        </Stack>
      ),
    },
    {
      accessor: 'medico',
      title:    'Médico',
      width:    160,
      render: (r) => {
        const medico = r.consulta_medica?.medico?.servidor
        return (
          <Text size="sm">
            {medico
              ? `Dr. ${medico.nombre} ${medico.apellido}`
              : '—'}
          </Text>
        )
      },
    },
    {
      accessor: 'items',
      title:    'Ítems',
      width:    80,
      render: (r) => (
        <Text size="sm" ta="center">{r.items.length}</Text>
      ),
    },
    {
      accessor: 'estado',
      title:    'Estado',
      width:    130,
      render: (r) => (
        <StatusBadge tone={TONO_ESTADO[r.estado] ?? 'neutral'}>
          {ESTADO_LABELS[r.estado] ?? r.estado}
        </StatusBadge>
      ),
    },
    {
      accessor: 'acciones',
      title:    '',
      width:    50,
      render: (r) => {
        const cerrada = r.estado === 'despachada_completa'
          || r.estado === 'anulada'
        return (
          <TableActions actions={[{
            label:   cerrada ? 'Ver detalle' : 'Despachar',
            icon:    cerrada
              ? <IconEye size={14} />
              : <IconCheck size={14} />,
            color:   cerrada ? 'blue' : 'emerald',
            onClick: () => onAbrir(r),
          }]} />
        )
      },
    },
  ]
}
