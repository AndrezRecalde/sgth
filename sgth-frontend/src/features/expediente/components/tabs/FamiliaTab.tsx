'use client'

import { useState } from 'react'
import Link from 'next/link'
import { Alert, Button, Group, Stack, Text } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconInbox, IconPlus, IconUsers } from '@tabler/icons-react'
import { DataState, SectionCard, SgthTable } from '@/components/ui'
import { SEMANTIC_COLOR } from '@/config/design.tokens'
import { ROUTES } from '@/config/routes'
import { useCargasFamiliares } from '../../hooks/useCargasFamiliares'
import { useCargasPorValidar } from '../../hooks/useValidacionCargas'
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
  // Lo que el servidor pidió desde «Mi perfil» y espera a Talento Humano
  // (docs/diseno-cargas-familiares-portal.md, 6.3).
  const { data: pendientes = [] } = useCargasPorValidar(servidorId)

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
        {pendientes.length > 0 && (
          <Alert color={SEMANTIC_COLOR.warning} variant="light" icon={<IconInbox size={16} />} mb="md">
            <Group justify="space-between" gap="sm">
              <Text size="sm">
                {pendientes.length === 1
                  ? '1 solicitud del Portal por validar.'
                  : `${pendientes.length} solicitudes del Portal por validar.`}
              </Text>
              <Button component={Link} href={ROUTES.SGTH.CARGAS_POR_VALIDAR_DE(servidorId)}
                size="xs" variant="light" color={SEMANTIC_COLOR.warning}>
                Revisarlas
              </Button>
            </Group>
          </Alert>
        )}

        <DataState
          loading={isLoading}
          error={error}
          empty={cargas.length === 0}
          skeletonRows={2}
          emptyProps={{
            icon: IconUsers,
            title: 'Sin cargas familiares',
            description: 'Registre los familiares dependientes del servidor.',
            action: (
              <Button variant="light" leftSection={<IconPlus size={14} />} onClick={() => { setEditItem(null); open() }}>
                Agregar carga familiar
              </Button>
            ),
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
