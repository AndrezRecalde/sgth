'use client'

import { Text, Badge, Group, Button } from '@mantine/core'
import { IconUser, IconUsers } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import type { AgendaMedica } from '../services/agendaService'

interface ColumnActions {
  onSeleccionar: (turno: AgendaMedica) => void
}

export function getTriajePendientesColumns(
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
        <Badge size="sm" variant="light" color="blue">
          Medicina General
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
      accessor: 'estado',
      title:    'Estado',
      width:    120,
      render: () => (
        <Badge size="sm" variant="light" color="orange">
          En espera
        </Badge>
      ),
    },
    {
      accessor: 'acciones',
      title:    '',
      width:    140,
      render: (turno) => (
        <Button
          size="xs"
          color="emerald"
          onClick={() => actions.onSeleccionar(turno)}
        >
          Tomar triaje
        </Button>
      ),
    },
  ]
}
