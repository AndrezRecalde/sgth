'use client'

import { Stack, Group, Text, Divider, Skeleton } from '@mantine/core'
import { IconPill } from '@tabler/icons-react'
import { useQuery } from '@tanstack/react-query'
import { consultaMedicaService } from '../services/consultaMedicaService'
import { SgthDrawer, SgthTable, StatusBadge } from '@/components/ui'
import { columnasItemsReceta } from './itemsReceta.columns'

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

  const { data: consulta, isLoading } = useQuery({
    queryKey: ['consulta-detalle', consultaId],
    queryFn:  () => consultaMedicaService.obtener(consultaId!),
    enabled:  !!consultaId && opened,
    staleTime: 1000 * 60,
  })

  return (
    <SgthDrawer
      opened={opened}
      onClose={onClose}
      title="Detalle de consulta"
      description={consulta ? formatFecha(consulta.fecha_consulta) : undefined}
    >
      {isLoading ? (
        <Stack gap="sm">
          <Skeleton height={100} radius="md" />
          <Skeleton height={100} radius="md" />
        </Stack>
      ) : consulta ? (
        <Stack gap="md">
          <Group gap="xs">
            {consulta.tipo_atencion && (
              <StatusBadge>
                {consulta.tipo_atencion.replace('_', ' ')}
              </StatusBadge>
            )}
            {consulta.tipo_diagnostico && (
              <StatusBadge
                tone={consulta.tipo_diagnostico === 'definitivo' ? 'success' : 'warning'}
              >
                {consulta.tipo_diagnostico}
              </StatusBadge>
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
                    <StatusBadge
                      tone={receta.estado === 'despachada_completa' ? 'success' : receta.estado === 'despachada_parcial' ? 'warning' : 'neutral'}
                      size="xs"
                    >
                      {receta.estado.replace(/_/g, ' ')}
                    </StatusBadge>
                  </Group>

                  {receta.indicaciones_generales && (
                    <Text size="xs" c="dimmed">
                      {receta.indicaciones_generales}
                    </Text>
                  )}

                  <SgthTable
                    records={receta.items}
                    columns={columnasItemsReceta}
                    minHeight={80}
                    noRecordsText="La receta no tiene medicamentos"
                  />
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
    </SgthDrawer>
  )
}
