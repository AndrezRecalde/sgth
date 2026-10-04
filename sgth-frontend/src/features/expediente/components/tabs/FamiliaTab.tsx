'use client'

import { useState } from 'react'
import { Button, Stack } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconPlus, IconUsers } from '@tabler/icons-react'
import { DataState, SectionCard, SgthTable } from '@/components/ui'
import { useCargasFamiliares } from '../../hooks/useCargasFamiliares'
import { useCargaFamiliarMutations } from '../../hooks/useCargaFamiliarMutations'
import { getCargasFamiliaresColumns } from '../cargasFamiliares.columns'
import { CargaFamiliarCondiciones } from '../CargaFamiliarCondiciones'
import { CargaFamiliarModal } from '../CargaFamiliarModal'
import type { CargaFamiliar } from '@/types/api'

interface Props { servidorId: number }

export function FamiliaTab({ servidorId }: Props) {
  const [opened, { open, close }] = useDisclosure(false)
  const [editItem, setEditItem] = useState<CargaFamiliar | null>(null)
  const { data: cargas = [], isLoading, error } = useCargasFamiliares(servidorId)
  const { eliminar, toggleEstado } = useCargaFamiliarMutations(servidorId)

  const columns = getCargasFamiliaresColumns({
    onEdit: (carga) => { setEditItem(carga); open() },
    onToggleEstado: (id) => toggleEstado.mutate(id),
    onDelete: (id) => eliminar.mutate(id),
  })

  return (
    <Stack gap="md">
      <SectionCard
        title="Cargas familiares"
        actions={
          <Button size="xs" variant="light"
            leftSection={<IconPlus size={14} />}
            onClick={() => { setEditItem(null); open() }}>
            Agregar carga familiar
          </Button>
        }
      >
        <DataState
          loading={isLoading}
          error={error}
          empty={cargas.length === 0}
          skeletonRows={2}
          emptyProps={{
            icon: IconUsers,
            title: 'Sin cargas familiares',
            description: 'Registra los familiares dependientes del servidor.',
          }}
        >
          <SgthTable
            records={cargas}
            columns={columns}
            minHeight={80}
            // Las condiciones de salud se despliegan bajo la fila, en todas:
            // es ahí donde se registran, y la marca sale de esos registros.
            rowExpansion={{
              allowMultiple: true,
              trigger: 'click',
              content: ({ record }) => (
                <CargaFamiliarCondiciones carga={record} servidorId={servidorId} />
              ),
            }}
          />
        </DataState>
      </SectionCard>

      <CargaFamiliarModal
        key={editItem?.id ?? 'nueva'}
        opened={opened}
        onClose={() => { setEditItem(null); close() }}
        servidorId={servidorId}
        initialValues={editItem}
      />
    </Stack>
  )
}
