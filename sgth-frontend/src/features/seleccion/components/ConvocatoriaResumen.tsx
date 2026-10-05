'use client'

import { Grid, Stack, Text } from '@mantine/core'
import { DetailList, SectionCard, StatusBadge } from '@/components/ui'
import { formatFechaMes } from '@/lib/fecha'
import { usePostulantes } from '../hooks/useConvocatoria'
import {
  ESTADO_CONVOCATORIA_OPTIONS, TIPO_CONVOCATORIA_OPTIONS, TONO_CONVOCATORIA, type Convocatoria,
} from '../services/convocatoriaService'
import { etiquetaDe } from '../services/postulanteOptions'

/**
 * Los que superaron la evaluación, estén donde estén después. Antes se
 * contaba solo `seleccionado`, un estado al que ya no lleva ninguna acción.
 */
const ESTADOS_APROBADOS = ['aprobado', 'lista_espera', 'ganador_potencial', 'seleccionado', 'incorporado']

/** La cabecera del detalle: qué se convoca y en qué punto está el proceso. */
export function ConvocatoriaResumen({ convocatoria }: { convocatoria: Convocatoria }) {
  const { data: postulantes = [] } = usePostulantes(convocatoria.id)
  const aprobados = postulantes.filter((p) => ESTADOS_APROBADOS.includes(p.estado)).length

  return (
    <Grid>
      <Grid.Col span={{ base: 12, md: 8 }}>
        <SectionCard title="Información general">
          <Stack gap="md">
            <Text size="sm">{convocatoria.descripcion}</Text>
            <DetailList items={[
              {
                label: 'Puesto',
                value: (
                  <Stack gap={0}>
                    <Text size="sm" fw={500}>{convocatoria.puesto?.cargo?.nombre ?? '—'}</Text>
                    <Text size="xs" c="dimmed">{convocatoria.puesto?.unidad_administrativa?.nombre ?? ''}</Text>
                  </Stack>
                ),
              },
              { label: 'Modalidad', value: <StatusBadge>{etiquetaDe(TIPO_CONVOCATORIA_OPTIONS, convocatoria.tipo)}</StatusBadge> },
              {
                label: 'Período',
                value: `${formatFechaMes(convocatoria.fecha_inicio)} — ${formatFechaMes(convocatoria.fecha_fin)}`,
              },
              { label: 'Vacantes', value: `${convocatoria.vacantes} vacante${convocatoria.vacantes !== 1 ? 's' : ''}` },
            ]} />
          </Stack>
        </SectionCard>
      </Grid.Col>

      <Grid.Col span={{ base: 12, md: 4 }}>
        <SectionCard title="Estado del proceso">
          <Stack gap="md">
            <StatusBadge tone={TONO_CONVOCATORIA[convocatoria.estado] ?? 'neutral'} size="lg">
              {etiquetaDe(ESTADO_CONVOCATORIA_OPTIONS, convocatoria.estado)}
            </StatusBadge>
            {convocatoria.motivo_cierre && (
              <Text size="xs" c="dimmed">Motivo: {convocatoria.motivo_cierre}</Text>
            )}
            <DetailList columnas={2} items={[
              { label: 'Candidatos inscritos', value: <Text size="xl" fw={700}>{postulantes.length}</Text> },
              { label: 'Aprobados', value: <Text size="xl" fw={700} c="emerald">{aprobados}</Text> },
            ]} />
          </Stack>
        </SectionCard>
      </Grid.Col>
    </Grid>
  )
}
