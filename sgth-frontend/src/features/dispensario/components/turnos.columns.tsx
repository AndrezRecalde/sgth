'use client'

import { Text, Badge, Group } from '@mantine/core'
import {
  IconUser, IconUsers, IconX, IconClipboardCheck,
} from '@tabler/icons-react'
import { TableActions } from '@/components/ui/TableActions'
import type { DataTableColumn } from 'mantine-datatable'
import type { AgendaMedica } from '../services/agendaService'

const ESTADO_COLORS: Record<string, string> = {
  en_espera:  'orange',
  en_sala:    'blue',
  atendida:   'emerald',
  cancelada:  'red',
}

const ESTADO_LABELS: Record<string, string> = {
  en_espera:  'En espera',
  en_sala:    'En sala / Triaje',
  atendida:   'Atendido',
  cancelada:  'Cancelado',
}

interface ColumnActions {
  onCancelar?:    (id: number) => void
  onTomarTriaje?: (turno: AgendaMedica) => void
}

export function getTurnosColumns(
  actions: ColumnActions
): DataTableColumn<AgendaMedica>[] {
  return [
    {
      accessor: 'folio',
      title:    'Folio',
      width:    140,
      render: (turno) => (
        <Text size="sm" ff="monospace" fw={500}>
          {turno.folio ?? '—'}
        </Text>
      ),
    },
    {
      accessor: 'paciente',
      title:    'Paciente',
      render: (turno) => {
        const esServidor = !!turno.servidor_id
        const nombre = esServidor
          ? `${turno.servidor?.nombre ?? ''} ${turno.servidor?.apellido ?? ''}`
          : `${turno.carga_familiar?.nombres ?? ''} ${turno.carga_familiar?.apellidos ?? ''}`

        return (
          <Group gap="xs" wrap="nowrap">
            {esServidor
              ? <IconUser size={14} color="var(--mantine-color-emerald-6)" />
              : <IconUsers size={14} color="var(--mantine-color-blue-6)" />}
            <Text size="sm">{nombre.trim() || '—'}</Text>
          </Group>
        )
      },
    },
    {
      accessor: 'tipo_atencion',
      title:    'Atención',
      width:    150,
      render: (turno) => (
        <Badge
          size="sm"
          variant="light"
          color={turno.tipo_atencion === 'odontologia' ? 'cyan' : 'blue'}
        >
          {turno.tipo_atencion === 'odontologia'
            ? 'Odontología' : 'Medicina General'}
        </Badge>
      ),
    },
    {
      accessor: 'medico',
      title:    'Profesional',
      render: (turno) => (
        <Text size="sm" c="dimmed">
          {turno.medico?.nombre_completo
            ?? turno.medico?.usuario_ti ?? '—'}
        </Text>
      ),
    },
    {
      accessor: 'registrado_en',
      title:    'Hora',
      width:    90,
      render: (turno) => (
        <Text size="sm" ff="monospace">
          {turno.registrado_en
            ? new Date(turno.registrado_en).toLocaleTimeString(
                'es-EC', { hour: '2-digit', minute: '2-digit' }
              )
            : '—'}
        </Text>
      ),
    },
    {
      accessor: 'estado',
      title:    'Estado',
      width:    150,
      render: (turno) => (
        <Badge
          size="sm"
          variant="light"
          color={ESTADO_COLORS[turno.estado] ?? 'gray'}
        >
          {ESTADO_LABELS[turno.estado] ?? turno.estado}
        </Badge>
      ),
    },
    {
      accessor: 'acciones',
      title:    '',
      width:    50,
      render: (turno) => (
        <TableActions actions={[
          {
            label:   'Tomar triaje',
            icon:    <IconClipboardCheck size={14} />,
            color:   'emerald',
            onClick: () => actions.onTomarTriaje?.(turno),
            hidden:  !actions.onTomarTriaje
              || turno.estado !== 'en_espera'
              || !turno.requiere_triaje,
          },
          {
            label:   'Cancelar turno',
            icon:    <IconX size={14} />,
            color:   'red',
            onClick: () => actions.onCancelar?.(turno.id),
            hidden:  !actions.onCancelar
              || turno.estado !== 'en_espera',
          },
        ]} />
      ),
    },
  ]
}
