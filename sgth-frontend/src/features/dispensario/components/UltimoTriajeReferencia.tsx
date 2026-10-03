'use client'

import { Card, Group, Text, Stack, SimpleGrid, Skeleton } from '@mantine/core'
import { IconCalendarEvent } from '@tabler/icons-react'
import { DataState, StatusBadge } from '@/components/ui'
import { useUltimoTriaje } from '../hooks/useTriaje'
import { NIVEL_ALERTA } from '../constants/signosVitales'
import { formatFechaMesHora } from '@/lib/fecha'

interface Props {
  agendaId: number
}

/**
 * El triaje de la visita anterior del paciente, para comparar.
 *
 * Se llamaba «Último triaje registrado» y usaba el mismo icono de historial
 * que las tomas de ESTE turno, justo encima: dos cajas de «historial» que no
 * se distinguían. Cuando el paciente no tiene visitas previas no pinta nada;
 * la tarjeta que lo decía era ruido entre el nombre y el primer campo.
 */
export function UltimoTriajeReferencia({ agendaId }: Props) {
  const { data: triaje, isLoading, error } = useUltimoTriaje(agendaId)

  if (isLoading) return <Skeleton height={96} radius="md" />

  if (error) {
    return (
      <DataState
        loading={false}
        error={error}
        errorTitle="No se pudo cargar la visita anterior"
        errorHint="Esto no significa que el paciente no tenga triajes previos."
      />
    )
  }

  if (!triaje) return null

  const nivel = triaje.nivel_alerta

  const datos: [string, string][] = [
    ['Peso', `${triaje.peso_kg} kg`],
    ['Talla', `${triaje.talla_cm} cm`],
    ['IMC', String(triaje.imc ?? '—')],
    ['P. arterial', `${triaje.presion_sistolica}/${triaje.presion_diastolica}`],
    ['F. cardíaca', `${triaje.frecuencia_cardiaca} lpm`],
    ['F. respiratoria', `${triaje.frecuencia_respiratoria} rpm`],
    ['Temperatura', `${triaje.temperatura_c} °C`],
    ['Sat. O2', `${triaje.saturacion_oxigeno} %`],
  ]

  return (
    <Card withBorder radius="md" p="sm">
      <Group gap="xs" mb="xs" justify="space-between">
        <Group gap={6}>
          <IconCalendarEvent size={14} />
          {/* Con hora y en la hora local: `formatFechaMes` lee en UTC y un
              triaje de la noche salía con la fecha del día siguiente. */}
          <Text size="xs" fw={600}>
            Visita anterior — {formatFechaMesHora(triaje.registrado_en)}
          </Text>
        </Group>
        {nivel && nivel !== 'normal' && (
          <StatusBadge tone={NIVEL_ALERTA[nivel].tono} size="xs">
            {NIVEL_ALERTA[nivel].etiqueta}
          </StatusBadge>
        )}
      </Group>

      <SimpleGrid cols={{ base: 2, sm: 4 }} spacing="xs">
        {datos.map(([etiqueta, valor]) => (
          <Stack key={etiqueta} gap={0}>
            <Text size="xs" c="dimmed">{etiqueta}</Text>
            <Text size="sm" fw={500}>{valor}</Text>
          </Stack>
        ))}
      </SimpleGrid>
    </Card>
  )
}
