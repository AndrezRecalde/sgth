'use client'

import { Stack, Group, Text, Badge, Button } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconPlus, IconTrash, IconUsers } from '@tabler/icons-react'
import { SgthTable } from '@/components/ui/SgthTable'
import { TableActions } from '@/components/ui/TableActions'
import { EmptyState } from '@/components/ui/EmptyState'
import { useCargasFamiliares } from '../../hooks/useCargasFamiliares'
import { useCargaFamiliarMutations } from '../../hooks/useCargaFamiliarMutations'
import { CargaFamiliarModal } from '../CargaFamiliarModal'
import type { CargaFamiliar } from '@/types/api'
import type { DataTableColumn } from 'mantine-datatable'

const PARENTESCO_LABELS: Record<string, string> = {
  conyugue: 'Cónyuge / Conviviente',
  hijo:     'Hijo/a',
}

interface Props { servidorId: number }

export function FamiliaTab({ servidorId }: Props) {
  const [opened, { open, close }] = useDisclosure(false)
  const { data: cargas = [], isLoading } = useCargasFamiliares(servidorId)
  const { eliminar } = useCargaFamiliarMutations(servidorId)

  const columns: DataTableColumn<CargaFamiliar>[] = [
    {
      accessor: 'nombres',
      title: 'Nombres completo',
      render: ({ nombres, apellidos }) => (
        <Text size="sm" fw={500}>{apellidos} {nombres}</Text>
      ),
    },
    {
      accessor: 'parentesco',
      title: 'Parentesco',
      width: 150,
      render: ({ parentesco }) => (
        <Badge variant="light" color="blue" size="sm">
          {PARENTESCO_LABELS[parentesco ?? ''] ?? parentesco ?? '-'}
        </Badge>
      ),
    },
    {
      accessor: 'fecha_nacimiento',
      title: 'Edad',
      width: 100,
      render: ({ fecha_nacimiento }) => {
        if (!fecha_nacimiento) return <Text size="sm">—</Text>
        const birth = new Date(fecha_nacimiento.split('T')[0])
        const age = new Date().getFullYear() - birth.getFullYear()
        return <Text size="sm">{age} años</Text>
      }
    },
    {
      accessor: 'persona_con_discapacidad',
      title: 'Condición Especial',
      render: ({ persona_con_discapacidad, posee_enfermedad_catastrofica }) => (
        <Group gap="xs">
          {persona_con_discapacidad && (
            <Badge color="orange" variant="light" size="xs">Discapacidad</Badge>
          )}
          {posee_enfermedad_catastrofica && (
            <Badge color="red" variant="light" size="xs">Enf. Catastrófica</Badge>
          )}
          {!persona_con_discapacidad && !posee_enfermedad_catastrofica && (
            <Text size="sm" c="dimmed">Ninguna</Text>
          )}
        </Group>
      ),
    },
    {
      accessor: 'acciones',
      title: '',
      width: 50,
      render: (item) => (
        <TableActions actions={[
          {
            label: 'Eliminar',
            icon: <IconTrash size={14} />,
            color: 'red',
            onClick: () => {
              if (confirm('¿Eliminar esta carga familiar?'))
                eliminar.mutate(Number(item.id))
            },
          },
        ]} />
      ),
    },
  ]

  return (
    <Stack gap="md">
      <Group justify="flex-end">
        <Button size="xs" color="emerald" variant="light"
          leftSection={<IconPlus size={14} />} onClick={open}>
          Agregar carga familiar
        </Button>
      </Group>
      {!isLoading && (cargas as CargaFamiliar[]).length === 0 ? (
        <EmptyState
          icon={IconUsers}
          title="Sin cargas familiares"
          description="Registra los familiares dependientes del servidor."
        />
      ) : (
        <SgthTable
          records={cargas as CargaFamiliar[]}
          columns={columns}
          fetching={isLoading}
          minHeight={100}
        />
      )}
      <CargaFamiliarModal
        opened={opened}
        onClose={close}
        servidorId={servidorId}
      />
    </Stack>
  )
}
