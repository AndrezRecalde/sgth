'use client'

import {
  Modal, Stack, Text, Card, Badge,
  Button, Group, Radio, Divider,
  Alert, Skeleton,
} from '@mantine/core'
import {
  IconTemplate, IconCheck,
  IconInfoCircle, IconAlertTriangle,
} from '@tabler/icons-react'
import { useState } from 'react'
import { usePlantillas, useAplicarPlantilla } from '../hooks/usePlantilla'
import { TIPO_CONTRATO_PLANTILLA_OPTIONS } from '../services/plantillaService'

interface Props {
  opened:         boolean
  onClose:        () => void
  convocatoriaId: number
  tieneCriterios: boolean
}

export function SeleccionarPlantillaModal({
  opened, onClose, convocatoriaId, tieneCriterios,
}: Props) {
  const [seleccionada, setSeleccionada] = useState<number | null>(null)
  const { data: plantillas = [], isLoading } = usePlantillas()
  const aplicar = useAplicarPlantilla(convocatoriaId)

  const getLabelTipo = (tipo: string | null | undefined) =>
    TIPO_CONTRATO_PLANTILLA_OPTIONS.find(
      o => o.value === tipo
    )?.label ?? tipo ?? 'General'

  const handleAplicar = () => {
    if (!seleccionada) return
    aplicar.mutate(seleccionada, {
      onSuccess: () => {
        setSeleccionada(null)
        onClose()
      },
    })
  }

  const handleClose = () => {
    setSeleccionada(null)
    onClose()
  }

  return (
    <Modal
      opened={opened}
      onClose={handleClose}
      title={
        <Group gap="xs">
          <IconTemplate size={18} />
          <Text fw={600}>Seleccionar plantilla de evaluación</Text>
        </Group>
      }
      size="lg"
      radius="xl"
    >
      <Stack gap="md">
        {tieneCriterios && (
          <Alert
            color="orange"
            variant="light"
            icon={<IconAlertTriangle size={16} />}
          >
            <Text size="xs">
              Esta convocatoria ya tiene criterios configurados.
              Al aplicar una plantilla se <strong>reemplazarán</strong>
              todos los criterios actuales.
            </Text>
          </Alert>
        )}

        <Alert
          color="blue"
          variant="light"
          icon={<IconInfoCircle size={16} />}
        >
          <Text size="xs">
            Los criterios de la plantilla se copiarán a esta
            convocatoria. Podrá modificarlos después según
            las necesidades específicas del puesto.
          </Text>
        </Alert>

        {isLoading ? (
          <Stack gap="sm">
            <Skeleton height={80} radius="md" />
            <Skeleton height={80} radius="md" />
            <Skeleton height={80} radius="md" />
          </Stack>
        ) : (
          <Radio.Group
            value={String(seleccionada ?? '')}
            onChange={(v) => setSeleccionada(Number(v))}
          >
            <Stack gap="sm">
              {plantillas.filter(p => p.activa).map(p => {
                const isSelected = seleccionada === p.id
                return (
                  <Card
                    key={p.id}
                    withBorder
                    radius="md"
                    p="sm"
                    style={{
                      borderColor: isSelected
                        ? 'var(--mantine-color-blue-6)'
                        : undefined,
                      borderWidth: isSelected ? 2 : 1,
                      cursor: 'pointer',
                    }}
                    onClick={() => setSeleccionada(p.id)}
                  >
                    <Group justify="space-between" wrap="nowrap">
                      <Group gap="sm" wrap="nowrap">
                        <Radio value={String(p.id)} />
                        <Stack gap={2}>
                          <Text size="sm" fw={600}>{p.nombre}</Text>
                          {p.descripcion && (
                            <Text size="xs" c="dimmed" lineClamp={2}>
                              {p.descripcion}
                            </Text>
                          )}
                          <Group gap="xs" mt={2}>
                            <Badge
                              size="xs"
                              variant="light"
                              color="blue"
                            >
                              {getLabelTipo(p.tipo_contrato)}
                            </Badge>
                            <Badge
                              size="xs"
                              variant="light"
                              color="gray"
                            >
                              {p.criterios_count ?? 0} criterios
                            </Badge>
                          </Group>
                        </Stack>
                      </Group>
                    </Group>
                  </Card>
                )
              })}
            </Stack>
          </Radio.Group>
        )}

        <Divider />

        <Group justify="flex-end">
          <Button variant="default" onClick={handleClose}>
            Cancelar
          </Button>
          <Button
            color="emerald"
            leftSection={<IconCheck size={14} />}
            disabled={!seleccionada}
            loading={aplicar.isPending}
            onClick={handleAplicar}
          >
            Aplicar plantilla
          </Button>
        </Group>
      </Stack>
    </Modal>
  )
}
