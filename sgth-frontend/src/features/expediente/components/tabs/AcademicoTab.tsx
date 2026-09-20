'use client'

import { useState } from 'react'
import { Stack, Group, Button } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconPlus, IconSchool } from '@tabler/icons-react'
import { DataState, SgthTable } from '@/components/ui'
import { useHistorialAcademico } from '../../hooks/useHistorialAcademico'
import { useHistorialAcademicoMutations } from '../../hooks/useHistorialAcademicoMutations'
import { getHistorialAcademicoColumns } from '../historialAcademico.columns'
import { HistorialAcademicoModal } from '../HistorialAcademicoModal'
import type { HistorialAcademicoServidor } from '@/types/api'

interface Props { servidorId: number }

export function AcademicoTab({ servidorId }: Props) {
  const [opened, { open, close }] = useDisclosure(false)
  const [editItem, setEditItem] = useState<HistorialAcademicoServidor | null>(null)
  const { data: historial = [], isLoading, error } = useHistorialAcademico(servidorId)
  const { eliminar } = useHistorialAcademicoMutations(servidorId)

  const columns = getHistorialAcademicoColumns({
    onEdit: (item) => { setEditItem(item); open() },
    onDelete: (id) => eliminar.mutate(id),
  })

  return (
    <Stack gap="md">
      <Group justify="flex-end">
        <Button size="xs" variant="light"
          leftSection={<IconPlus size={14} />} onClick={open}>
          Agregar registro
        </Button>
      </Group>

      <DataState
        loading={isLoading}
        error={error}
        empty={historial.length === 0}
        skeletonRows={3}
        emptyProps={{
          icon: IconSchool,
          title: 'Sin historial académico',
          description: 'Registra los títulos académicos o capacitaciones del servidor.',
        }}
      >
        <SgthTable records={historial} columns={columns} minHeight={100} />
      </DataState>

      <HistorialAcademicoModal
        key={editItem?.id ?? 'nuevo'}
        opened={opened}
        onClose={() => { setEditItem(null); close() }}
        servidorId={servidorId}
        initialValues={editItem}
      />
    </Stack>
  )
}
