'use client'

import { Stack, Text } from '@mantine/core'
import { IconCheck, IconEye, IconBan, IconPrinter } from '@tabler/icons-react'
import { StatusBadge, TableActions } from '@/components/ui'
import type { SemanticTone } from '@/config/design.tokens'
import type { DataTableColumn } from 'mantine-datatable'
import { admiteDespacho } from '../services/recetaService'
import type { RecetaMedica } from '../services/recetaService'
import { formatFechaMes } from '@/lib/fecha'

const TONO_ESTADO: Record<string, SemanticTone> = {
  pendiente:           'warning',
  despachada_parcial:  'info',
  despachada_completa: 'success',
  anulada:             'danger',
  // Nada que entregar aquí: ni es trabajo pendiente ni salió mal.
  externa:             'neutral',
}

const ESTADO_LABELS: Record<string, string> = {
  pendiente:           'Pendiente',
  despachada_parcial:  'Parcial',
  despachada_completa: 'Completada',
  anulada:             'Anulada',
  externa:             'Externa',
}

/**
 * Anular cierra lo que falta por entregar, así que no procede sobre una receta
 * ya cerrada. Una `externa` sí se anula —es la única vía para retirarla cuando
 * el médico cambia el tratamiento— y el servidor la acepta igual.
 */
const NO_ANULABLES = ['despachada_completa', 'anulada']

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
  onAnular: (receta: RecetaMedica) => void
  /** Abre el impreso para entregárselo al paciente. */
  onImprimir: (receta: RecetaMedica) => void
  /** Sin el rol de despacho, toda receta se abre como detalle. */
  puedeDespachar: boolean
  puedeAnular: (receta: RecetaMedica) => boolean
}

export function getRecetasColumns(
  { onAbrir, onAnular, onImprimir, puedeDespachar, puedeAnular }: ColumnActions
): DataTableColumn<RecetaMedica>[] {
  return [
    {
      accessor: 'folio',
      title:    'Receta N°',
      width:    130,
      render: (r) => (
        <Text size="sm" ff="monospace">{r.folio ?? '—'}</Text>
      ),
    },
    {
      accessor: 'fecha_emision',
      title:    'Fecha emisión',
      width:    130,
      render: (r) => (
        <Text size="sm">
          {formatFechaMes(r.fecha_emision)}
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
        const cerrada = !puedeDespachar || !admiteDespacho(r)
        return (
          <TableActions actions={[
            {
              label:   cerrada ? 'Ver detalle' : 'Despachar',
              icon:    cerrada
                ? <IconEye size={14} />
                : <IconCheck size={14} />,
              onClick: () => onAbrir(r),
            },
            {
              label:   'Imprimir receta',
              icon:    <IconPrinter size={14} />,
              onClick: () => onImprimir(r),
            },
            ...(NO_ANULABLES.includes(r.estado) || !puedeAnular(r) ? [] : [{
              label:   'Anular receta',
              icon:    <IconBan size={14} />,
              color:   'red',
              onClick: () => onAnular(r),
            }]),
          ]} />
        )
      },
    },
  ]
}
