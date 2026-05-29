'use client'

import { Stack, Group, Button, Text, Badge } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import {
  IconPlus, IconCheck, IconX,
  IconShieldCheck, IconClipboardList,
} from '@tabler/icons-react'
import { SgthTable } from '@/components/ui/SgthTable'
import { TableActions } from '@/components/ui/TableActions'
import { EmptyState } from '@/components/ui/EmptyState'
import { PermisoModal } from './PermisoModal'
import { usePermisos } from '../hooks/usePermisos'
import { usePermisoMutations } from '../hooks/usePermisoMutations'
import type { PermisoServidor } from '@/types/api'
import type { DataTableColumn } from 'mantine-datatable'

const ESTADO_COLORS: Record<string, string> = {
  pendiente:               'orange',
  activo:                  'blue',
  validado_trabajo_social: 'emerald',
  anulado:                 'red',
}

const ESTADO_LABELS: Record<string, string> = {
  pendiente:               'Pendiente',
  activo:                  'Activo',
  validado_trabajo_social: 'Validado TS',
  anulado:                 'Anulado',
}

const TIPO_LABELS: Record<string, string> = {
  personal:   'Personal',
  oficial:    'Oficial',
  enfermedad: 'Enfermedad',
  calamidad:  'Calamidad',
}

export function PermisosTab() {
  const [opened, { open, close }] = useDisclosure(false)
  const { data: permisos = [], isLoading } = usePermisos()
  const { confirmar, anular, validarTs } = usePermisoMutations()

  const lista = permisos as PermisoServidor[]

  const columns: DataTableColumn<PermisoServidor>[] = [
    {
      accessor: 'folio',
      title:    'Folio',
      width:    140,
      render: ({ folio }) => (
        <Text size="sm" ff="monospace" fw={500}>
          {folio ?? '—'}
        </Text>
      ),
    },
    {
      accessor: 'servidor',
      title:    'Servidor',
      render: (p) => {
        const s = p.servidor
        if (!s) return <Text size="sm" c="dimmed">—</Text>
        return (
          <Text size="sm">
            {[s.apellido, s.nombre].filter(Boolean).join(' ')}
          </Text>
        )
      },
    },
    {
      accessor: 'unidad_administrativa',
      title:    'Unidad',
      render: (p) => {
        const nombre = (p.unidad_administrativa as {
          nombre?: string
        } | null)?.nombre
        return (
          <Text size="sm" c="dimmed">
            {nombre ?? '—'}
          </Text>
        )
      },
    },
    {
      accessor: 'tipo',
      title:    'Tipo',
      width:    100,
      render: ({ tipo }) => (
        <Badge size="sm" variant="light" color="blue">
          {TIPO_LABELS[tipo] ?? tipo}
        </Badge>
      ),
    },
    {
      accessor: 'fecha',
      title:    'Fecha',
      width:    110,
      render: ({ fecha }) => (
        <Text size="sm">
          {fecha ? new Date(fecha).toLocaleDateString('es-EC', {
            timeZone: 'UTC',
            day: '2-digit', month: '2-digit', year: 'numeric',
          }) : '—'}
        </Text>
      ),
    },
    {
      accessor: 'hora_inicio',
      title:    'Horario',
      width:    110,
      render: ({ hora_inicio, hora_fin }) => (
        <Text size="sm" ff="monospace">
          {hora_inicio?.substring(0,5) ?? '—'}
          {' — '}
          {hora_fin?.substring(0,5) ?? '—'}
        </Text>
      ),
    },
    {
      accessor: 'estado',
      title:    'Estado',
      width:    120,
      render: ({ estado }) => (
        <Badge
          color={ESTADO_COLORS[estado as string] ?? 'gray'}
          variant="light" size="sm"
        >
          {ESTADO_LABELS[estado as string] ?? estado}
        </Badge>
      ),
    },
    {
      accessor: 'acciones',
      title:    '',
      width:    50,
      render: (p) => (
        <TableActions actions={[
          {
            label:    'Confirmar recepción',
            icon:     <IconCheck size={14} />,
            color:    'blue',
            onClick:  () => p.folio && confirmar.mutate(p.folio),
            hidden: (p.estado as string) !== 'pendiente',
          },
          {
            label:    'Validar Trabajo Social',
            icon:     <IconShieldCheck size={14} />,
            color:    'emerald',
            onClick:  () => validarTs.mutate(p.id),
            hidden: (p.estado as string) !== 'activo',
          },
          {
            label:    'Anular',
            icon:     <IconX size={14} />,
            color:    'red',
            onClick:  () => {
              if (confirm('¿Anular este permiso?'))
                anular.mutate(p.id)
            },
            hidden: (p.estado as string) !== 'pendiente',
          },
        ]} />
      ),
    },
  ]

  return (
    <Stack gap="md">
      <Group justify="flex-end">
        <Button
          size="xs" color="emerald" variant="light"
          leftSection={<IconPlus size={14} />}
          onClick={open}
        >
          Nuevo permiso
        </Button>
      </Group>

      {lista.length === 0 && !isLoading ? (
        <EmptyState
          icon={IconClipboardList}
          title="Sin permisos registrados"
        />
      ) : (
        <SgthTable
          records={lista}
          columns={columns}
          fetching={isLoading}
          minHeight={200}
        />
      )}

      <PermisoModal opened={opened} onClose={close} />
    </Stack>
  )
}
