'use client'

import { Stack, Group, Text, Skeleton } from '@mantine/core'
import { IconPill } from '@tabler/icons-react'
import { useQuery } from '@tanstack/react-query'
import { consultaMedicaService } from '../services/consultaMedicaService'
import { SectionHeading, SgthDrawer, SgthTable, StatusBadge } from '@/components/ui'
import { columnasItemsReceta } from './itemsReceta.columns'
import { TextoClinico } from './TextoClinico'
import { DiagnosticosCie10 } from './DiagnosticosCie10'
import { formatFechaMes } from '@/lib/fecha'

interface Props {
  opened:     boolean
  onClose:    () => void
  consultaId: number | null
}

function Campo({
  label, valor, enriquecido,
}: {
  label: string
  valor?: string | null
  enriquecido?: boolean
}) {
  if (!valor) return null
  return (
    <Stack gap={2}>
      <Text size="xs" fw={600} tt="uppercase" c="dimmed">
        {label}
      </Text>
      <TextoClinico valor={valor} enriquecido={enriquecido} />
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
      description={consulta ? formatFechaMes(consulta.fecha_consulta) : undefined}
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
          {(consulta.diagnostico_cie10_principal
            || (consulta.diagnosticos_secundarios?.length ?? 0) > 0) && (
            <Stack gap={2}>
              <Text size="xs" fw={600} tt="uppercase" c="dimmed">
                Diagnóstico (CIE-10)
              </Text>
              <DiagnosticosCie10 consulta={consulta} />
            </Stack>
          )}
          <Campo
            label="Diagnóstico detallado"
            valor={consulta.diagnostico_detallado}
            enriquecido
          />
          <Campo
            label="Plan de tratamiento"
            valor={consulta.plan_tratamiento}
            enriquecido
          />
          <Campo
            label="Notas del médico"
            valor={consulta.notas_medico}
          />

          {/* El título de «Recetas» se pintaba dos veces, una por rama de la
              misma condición. Ahora va una sola vez y solo cambia lo de
              debajo: las recetas o la frase de que no hay ninguna. */}
          <SectionHeading
            title={(consulta.recetas_medicas?.length ?? 0) > 0
              ? `Recetas (${consulta.recetas_medicas?.length})`
              : 'Recetas'}
            icon={<IconPill size={12} />}
          />

          {(consulta.recetas_medicas?.length ?? 0) > 0 ? (
            <>
              {consulta.recetas_medicas?.map((receta) => (
                <Stack key={receta.id} gap="xs">
                  <Group justify="space-between">
                    <Text size="xs" c="dimmed">
                      Emitida: {formatFechaMes(receta.fecha_emision)}
                    </Text>
                    <StatusBadge
                      tone={['despachada_completa', 'cerrada_con_faltantes'].includes(receta.estado) ? 'success' : receta.estado === 'despachada_parcial' ? 'warning' : 'neutral'}
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
          ) : (
            <Text size="xs" c="dimmed">
              Ninguna receta emitida en esta consulta.
            </Text>
          )}
        </Stack>
      ) : null}
    </SgthDrawer>
  )
}
