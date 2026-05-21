'use client'

import { Stack, Text, Badge, Card, Group, Button, ActionIcon } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconPlus, IconTrash } from '@tabler/icons-react'
import { useContratos } from '../../hooks/useContratos'
import { useContratoMutations } from '../../hooks/useContratoMutations'
import { ContratoModal } from '../ContratoModal'
import type { ContratoConRelaciones, EstadoContrato } from '@/types/api'

const ESTADO_COLORS: Record<EstadoContrato, string> = {
  vigente: 'emerald', terminado: 'gray', cancelado: 'red',
}

interface Props { servidorId: number }

export function ContratosTab({ servidorId }: Props) {
  const [opened, { open, close }] = useDisclosure(false)
  const { data: contratos = [], isLoading } = useContratos(servidorId)
  const { eliminar } = useContratoMutations(servidorId)

  return (
    <Stack gap="sm">
      <Group justify="flex-end">
        <Button size="sm" leftSection={<IconPlus size={14} />}
          color="emerald" onClick={open}>
          Nuevo contrato
        </Button>
      </Group>
      {isLoading && <Text size="sm" c="dimmed">Cargando contratos...</Text>}
      {(contratos as ContratoConRelaciones[]).map(c => (
        <Card key={c.id} withBorder radius="md" p="sm">
          <Group justify="space-between" wrap="nowrap">
            <Stack gap={2}>
              <Group gap="xs">
                <Text size="sm" fw={600}>
                  {c.puesto?.nombre ?? 'Sin cargo'}
                </Text>
                {c.estado && (
                  <Badge size="xs" color={ESTADO_COLORS[c.estado]}>
                    {c.estado}
                  </Badge>
                )}
              </Group>
              <Text size="xs" c="dimmed">
                {c.unidad_administrativa?.nombre ?? '-'}
              </Text>
              <Text size="xs" c="dimmed">
                {c.tipo_nombramiento ?? '-'} ·{' '}
                Desde {c.fecha_ingreso ?? '-'}
                {c.fecha_fin ? ` hasta ${c.fecha_fin}` : ''}
              </Text>
              <Text size="xs" fw={500}>
                ${c.remuneracion?.toFixed(2) ?? '-'}
              </Text>
            </Stack>
            <ActionIcon variant="subtle" color="red"
              onClick={() => {
                if (confirm('¿Eliminar este contrato?'))
                  eliminar.mutate(Number(c.id))
              }}
              aria-label="Eliminar contrato"
            >
              <IconTrash size={16} />
            </ActionIcon>
          </Group>
        </Card>
      ))}
      {!isLoading && contratos.length === 0 && (
        <Text size="sm" c="dimmed">No hay contratos registrados.</Text>
      )}
      <ContratoModal
        opened={opened}
        onClose={close}
        servidorId={servidorId}
      />
    </Stack>
  )
}
