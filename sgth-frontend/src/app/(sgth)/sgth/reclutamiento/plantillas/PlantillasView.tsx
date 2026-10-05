'use client'

import { ActionIcon, Button, Card, Group, Stack, Text, Tooltip } from '@mantine/core'
import { IconEdit, IconPlus, IconTemplate, IconTrash } from '@tabler/icons-react'
import { useDisclosure } from '@mantine/hooks'
import { useRouter } from 'next/navigation'
import { confirmar, DataState, PageHeader, PageShell, StatusBadge } from '@/components/ui'
import { ROUTES } from '@/config/routes'
import { NuevaPlantillaModal } from '@/features/seleccion/components/NuevaPlantillaModal'
import { useEliminarPlantilla, usePlantillas } from '@/features/seleccion/hooks/usePlantilla'
import { TIPO_CONTRATO_PLANTILLA_OPTIONS } from '@/features/seleccion/services/plantillaService'

const etiquetaTipo = (tipo?: string | null) =>
  TIPO_CONTRATO_PLANTILLA_OPTIONS.find(o => o.value === tipo)?.label ?? 'General'

export function PlantillasView() {
  const router = useRouter()
  const [modalAbierto, modal] = useDisclosure(false)
  const { data: plantillas = [], isLoading, error, refetch } = usePlantillas()
  const eliminar = useEliminarPlantilla()

  return (
    <PageShell>
      <PageHeader
        title="Plantillas de evaluación"
        description="Criterios reutilizables para las convocatorias"
        actions={<Button leftSection={<IconPlus size={14} />} onClick={modal.open}>Nueva plantilla</Button>}
      />

      <DataState
        loading={isLoading}
        error={error}
        errorTitle="No se pudieron cargar las plantillas"
        onRetry={refetch}
        empty={!plantillas.length}
        emptyProps={{
          icon: IconTemplate,
          title: 'Sin plantillas',
          description: 'Cree plantillas de criterios reutilizables para sus convocatorias.',
        }}
      >
        <Stack gap="sm">
          {plantillas.map(p => (
            <Card key={p.id} withBorder radius="lg" p="md">
              <Group justify="space-between" wrap="nowrap">
                <Stack gap={4}>
                  <Group gap="xs">
                    <Text fw={600}>{p.nombre}</Text>
                    {!p.activa && <StatusBadge size="xs">Inactiva</StatusBadge>}
                  </Group>
                  {p.descripcion && <Text size="xs" c="dimmed" lineClamp={2}>{p.descripcion}</Text>}
                  <Group gap="xs" mt={2}>
                    <StatusBadge size="xs">{etiquetaTipo(p.tipo_contrato)}</StatusBadge>
                    <StatusBadge size="xs">{p.criterios_count ?? 0} criterios</StatusBadge>
                  </Group>
                </Stack>
                <Group gap="xs" wrap="nowrap">
                  <Tooltip label="Abrir plantilla">
                    <ActionIcon variant="light" aria-label={`Abrir la plantilla ${p.nombre}`}
                      onClick={() => router.push(ROUTES.SGTH.PLANTILLA(p.id))}>
                      <IconEdit size={16} />
                    </ActionIcon>
                  </Tooltip>
                  <Tooltip label="Eliminar plantilla">
                    <ActionIcon variant="light" color="red" aria-label={`Eliminar la plantilla ${p.nombre}`}
                      onClick={() => confirmar({
                        title: 'Eliminar plantilla',
                        message: <>Se eliminará la plantilla <b>{p.nombre}</b>. No se puede deshacer.</>,
                        destructiva: true,
                        onConfirm: () => eliminar.mutate(p.id),
                      })}>
                      <IconTrash size={16} />
                    </ActionIcon>
                  </Tooltip>
                </Group>
              </Group>
            </Card>
          ))}
        </Stack>
      </DataState>

      <NuevaPlantillaModal opened={modalAbierto} onClose={modal.close} />
    </PageShell>
  )
}
