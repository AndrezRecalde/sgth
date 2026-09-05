'use client'

import {
  Drawer, Stack, Group, Text, Badge,
  ThemeIcon, Divider, Table, Skeleton,
  ScrollArea,
} from '@mantine/core'
import {
  IconStethoscope, IconPill,
} from '@tabler/icons-react'
import { useQuery } from '@tanstack/react-query'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { consultaMedicaService } from '../services/consultaMedicaService'

interface Props {
  opened:     boolean
  onClose:    () => void
  consultaId: number | null
}

function formatFecha(fecha?: string | null): string {
  if (!fecha) return '—'
  return new Date(fecha).toLocaleDateString('es-EC', {
    day: '2-digit', month: 'short', year: 'numeric',
  })
}

function Campo({
  label, valor,
}: {
  label: string
  valor?: string | null
}) {
  if (!valor) return null
  // El HTML llega saneado del servidor: se limpia al guardar con lista blanca
  // de las etiquetas que produce el editor, y lo que ya estaba guardado se
  // saneó en una migración. Ver App\Support\HtmlClinico.
  const esHtml = valor.startsWith('<')
  return (
    <Stack gap={2}>
      <Text size="xs" fw={600} tt="uppercase" c="dimmed">
        {label}
      </Text>
      {esHtml ? (
        <div
          style={{ fontSize: 'var(--mantine-font-size-sm)' }}
          dangerouslySetInnerHTML={{ __html: valor }}
        />
      ) : (
        <Text size="sm">{valor}</Text>
      )}
    </Stack>
  )
}

export function DetalleConsultaDrawer({
  opened, onClose, consultaId,
}: Props) {
  const { isMobile } = useMobileBreakpoint()

  const { data: consulta, isLoading } = useQuery({
    queryKey: ['consulta-detalle', consultaId],
    queryFn:  () => consultaMedicaService.obtener(consultaId!),
    enabled:  !!consultaId && opened,
    staleTime: 1000 * 60,
  })

  return (
    <Drawer
      opened={opened}
      onClose={onClose}
      title={
        <Group gap="xs">
          <ThemeIcon color="blue" variant="light" size="md" radius="md">
            <IconStethoscope size={16} />
          </ThemeIcon>
          <Stack gap={0}>
            <Text fw={700} size="sm">Detalle de consulta</Text>
            <Text size="xs" c="dimmed">
              {consulta
                ? formatFecha(consulta.fecha_consulta)
                : '—'}
            </Text>
          </Stack>
        </Group>
      }
      position="right"
      size={isMobile ? '100%' : 560}
      padding="lg"
    >
      <ScrollArea h="calc(100vh - 120px)">
        {isLoading ? (
          <Stack gap="sm">
            <Skeleton height={100} radius="md" />
            <Skeleton height={100} radius="md" />
          </Stack>
        ) : consulta ? (
          <Stack gap="md">
            <Group gap="xs">
              {consulta.tipo_atencion && (
                <Badge size="sm" variant="light" color="blue">
                  {consulta.tipo_atencion.replace('_', ' ')}
                </Badge>
              )}
              {consulta.tipo_diagnostico && (
                <Badge
                  size="sm"
                  variant="light"
                  color={consulta.tipo_diagnostico === 'definitivo'
                    ? 'emerald' : 'orange'}
                >
                  {consulta.tipo_diagnostico}
                </Badge>
              )}
              <Text size="xs" c="dimmed">
                Dr. {consulta.medico?.nombre_completo ?? '—'}
              </Text>
            </Group>

            <Campo
              label="Motivo de consulta"
              valor={consulta.motivo_consulta}
            />
            <Campo
              label="Enfermedad actual"
              valor={consulta.enfermedad_actual}
            />
            <Campo
              label="Examen físico"
              valor={consulta.examen_fisico}
            />
            <Campo
              label="Diagnóstico detallado"
              valor={consulta.diagnostico_detallado}
            />
            <Campo
              label="Plan de tratamiento"
              valor={consulta.plan_tratamiento}
            />
            <Campo
              label="Notas del médico"
              valor={consulta.notas_medico}
            />

            {(consulta.recetas_medicas?.length ?? 0) > 0 && (
              <>
                <Divider
                  label={
                    <Group gap={4}>
                      <IconPill size={12} />
                      <Text size="xs" fw={600} tt="uppercase" c="dimmed">
                        Recetas ({consulta.recetas_medicas?.length})
                      </Text>
                    </Group>
                  }
                  labelPosition="left"
                />
                {consulta.recetas_medicas?.map((receta) => (
                  <Stack key={receta.id} gap="xs">
                    <Group justify="space-between">
                      <Text size="xs" c="dimmed">
                        Emitida: {formatFecha(receta.fecha_emision)}
                      </Text>
                      <Badge
                        size="xs"
                        variant="light"
                        color={receta.estado === 'despachada_completa'
                          ? 'emerald'
                          : receta.estado === 'despachada_parcial'
                            ? 'orange'
                            : 'gray'}
                      >
                        {receta.estado.replace(/_/g, ' ')}
                      </Badge>
                    </Group>

                    {receta.indicaciones_generales && (
                      <Text size="xs" c="dimmed">
                        {receta.indicaciones_generales}
                      </Text>
                    )}

                    <Table withTableBorder withColumnBorders>
                      <Table.Thead>
                        <Table.Tr>
                          <Table.Th>Medicina</Table.Th>
                          <Table.Th w={70}>Cant.</Table.Th>
                          <Table.Th w={100}>Dosis</Table.Th>
                          <Table.Th w={110}>Frecuencia</Table.Th>
                        </Table.Tr>
                      </Table.Thead>
                      <Table.Tbody>
                        {receta.items.map((item) => (
                          <Table.Tr key={item.id}>
                            <Table.Td>
                              <Text size="xs" fw={500}>
                                {item.inventario?.nombre ?? '—'}
                              </Text>
                              <Text size="xs" c="dimmed">
                                {item.inventario?.concentracion ?? ''}
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
                          </Table.Tr>
                        ))}
                      </Table.Tbody>
                    </Table>
                  </Stack>
                ))}
              </>
            )}

            {(consulta.recetas_medicas?.length ?? 0) === 0 && (
              <>
                <Divider
                  label={
                    <Text size="xs" fw={600} tt="uppercase" c="dimmed">
                      Recetas
                    </Text>
                  }
                  labelPosition="left"
                />
                <Text size="xs" c="dimmed">
                  Ninguna receta emitida en esta consulta.
                </Text>
              </>
            )}
          </Stack>
        ) : null}
      </ScrollArea>
    </Drawer>
  )
}
