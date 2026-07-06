'use client'

import {
  Stack, Text, Button, Group, Badge,
  Card, Table, Skeleton, Divider,
  ThemeIcon,
} from '@mantine/core'
import { IconPill, IconPlus } from '@tabler/icons-react'
import { useDisclosure } from '@mantine/hooks'
import { useQuery } from '@tanstack/react-query'
import { recetaService } from '../services/recetaService'
import { RecetaModal } from './RecetaModal'
import { EmptyState } from '@/components/ui/EmptyState'
import type { AgendaMedica } from '../services/agendaService'
import type { ConsultaMedica } from '../services/consultaMedicaService'

interface Props {
  turno:    AgendaMedica
  consulta: ConsultaMedica
}

function formatFecha(fecha: string): string {
  return new Date(fecha).toLocaleDateString('es-EC', {
    day: '2-digit', month: 'short', year: 'numeric',
  })
}

const ESTADO_RECETA: Record<string, { label: string; color: string }> = {
  pendiente:          { label: 'Pendiente',   color: 'gray'   },
  despachada_parcial: { label: 'Parcial',     color: 'orange' },
  despachada_completa:{ label: 'Despachada',  color: 'emerald'},
  anulada:            { label: 'Anulada',     color: 'red'    },
}

export function TabReceta({ turno, consulta }: Props) {
  const [modalOpened,
    { open: abrirModal, close: cerrarModal }] = useDisclosure(false)

  const { data: recetas = [], isLoading } = useQuery({
    queryKey: ['recetas', 'consulta', consulta.id],
    queryFn:  () => recetaService.listarPorConsulta(consulta.id),
    staleTime: 1000 * 30,
  })

  if (isLoading) {
    return (
      <Stack gap="sm" p="md">
        <Skeleton height={80} radius="md" />
      </Stack>
    )
  }

  return (
    <Stack gap="md" p="md">
      <Group justify="space-between">
        <Text size="sm" fw={500}>
          Recetas de esta consulta
          {recetas.length > 0 && (
            <Text span c="dimmed" ml={4}>
              ({recetas.length})
            </Text>
          )}
        </Text>
        <Button
          size="xs"
          color="emerald"
          leftSection={<IconPlus size={13} />}
          onClick={abrirModal}
        >
          Nueva receta
        </Button>
      </Group>

      {recetas.length === 0 ? (
        <EmptyState
          icon={IconPill}
          title="Sin recetas"
          description="No se han emitido recetas para esta consulta."
        />
      ) : (
        <Stack gap="sm">
          {recetas.map((receta) => {
            const estadoConfig = ESTADO_RECETA[receta.estado]
              ?? { label: receta.estado, color: 'gray' }
            return (
              <Card key={receta.id} withBorder radius="md" p="sm">
                <Stack gap="xs">
                  <Group justify="space-between">
                    <Group gap="xs">
                      <ThemeIcon
                        size="sm" color="emerald" variant="light"
                      >
                        <IconPill size={12} />
                      </ThemeIcon>
                      <Text size="sm" fw={500}>
                        Receta — {formatFecha(receta.fecha_emision)}
                      </Text>
                    </Group>
                    <Badge
                      size="sm"
                      variant="light"
                      color={estadoConfig.color}
                    >
                      {estadoConfig.label}
                    </Badge>
                  </Group>

                  {receta.indicaciones_generales && (
                    <Text size="xs" c="dimmed">
                      {receta.indicaciones_generales}
                    </Text>
                  )}

                  {receta.items.length > 0 && (
                    <Table withTableBorder withColumnBorders>
                      <Table.Thead>
                        <Table.Tr>
                          <Table.Th>Medicina</Table.Th>
                          <Table.Th w={70}>Cant.</Table.Th>
                          <Table.Th w={110}>Dosis</Table.Th>
                          <Table.Th w={120}>Frecuencia</Table.Th>
                          <Table.Th w={90}>Duración</Table.Th>
                        </Table.Tr>
                      </Table.Thead>
                      <Table.Tbody>
                        {receta.items.map((item) => (
                          <Table.Tr key={item.id}>
                            <Table.Td>
                              <Text size="xs" fw={500}>
                                {item.nombre_medicina ?? '—'}
                              </Text>
                            </Table.Td>
                            <Table.Td>
                              <Text size="xs" ta="center">
                                {item.cantidad_prescrita}
                              </Text>
                            </Table.Td>
                            <Table.Td>
                              <Text size="xs">{item.dosis}</Text>
                            </Table.Td>
                            <Table.Td>
                              <Text size="xs">{item.frecuencia}</Text>
                            </Table.Td>
                            <Table.Td>
                              <Text size="xs">{item.duracion}</Text>
                            </Table.Td>
                          </Table.Tr>
                        ))}
                      </Table.Tbody>
                    </Table>
                  )}
                </Stack>
              </Card>
            )
          })}
        </Stack>
      )}

      <RecetaModal
        opened={modalOpened}
        onClose={cerrarModal}
        turno={turno}
        consulta={consulta}
        onEmitida={() => {
          cerrarModal()
        }}
      />
    </Stack>
  )
}
