'use client'

import { Grid, Stack, Textarea, TextInput } from '@mantine/core'
import { useContainedInput } from '@/hooks/useContainedInput'
import { SectionHeading } from '@/components/ui'
import type { FichaBaseForm } from '../../schemas/femo.schema'

interface Props {
  fichaData:     Partial<FichaBaseForm>
  onFichaChange: (data: Partial<FichaBaseForm>) => void
}

/**
 * El cierre de la sección C: estilo de vida, condición preexistente y la
 * «Observación» con que el impreso termina la sección.
 */
export function FemoEstiloDeVidaSection({ fichaData, onFichaChange }: Props) {
  const contained = useContainedInput()

  const set = (cambios: Partial<FichaBaseForm>) =>
    onFichaChange({ ...fichaData, ...cambios })

  return (
    <Stack gap="xs">
      <SectionHeading title="Estilo de vida y condición preexistente" />

      <Grid>
        <Grid.Col span={{ base: 12, md: 8 }}>
          <TextInput
            label="Actividad física"
            placeholder="Ej: caminata, fútbol"
            {...contained}
            value={fichaData.actividad_fisica_cual ?? ''}
            onChange={(e) => set({ actividad_fisica_cual: e.currentTarget.value })}
          />
        </Grid.Col>
        <Grid.Col span={{ base: 12, md: 4 }}>
          <TextInput
            label="Tiempo (frecuencia)"
            placeholder="Ej: 3 veces por semana"
            {...contained}
            value={fichaData.actividad_fisica_tiempo ?? ''}
            onChange={(e) => set({ actividad_fisica_tiempo: e.currentTarget.value })}
          />
        </Grid.Col>

        <Grid.Col span={{ base: 12, md: 8 }}>
          <TextInput
            label="Medicación habitual"
            placeholder="Ej: losartán"
            {...contained}
            value={fichaData.medicacion_habitual_cual ?? ''}
            onChange={(e) => set({ medicacion_habitual_cual: e.currentTarget.value })}
          />
        </Grid.Col>
        <Grid.Col span={{ base: 12, md: 4 }}>
          <TextInput
            label="Cantidad (dosis)"
            placeholder="Ej: 50 mg diarios"
            {...contained}
            value={fichaData.medicacion_habitual_cantidad ?? ''}
            onChange={(e) => set({ medicacion_habitual_cantidad: e.currentTarget.value })}
          />
        </Grid.Col>

        <Grid.Col span={12}>
          <Textarea
            label="Observación"
            description="Observación de la sección C (antecedentes personales)"
            autosize
            minRows={2}
            {...contained}
            value={fichaData.observacion_antecedentes ?? ''}
            onChange={(e) => set({ observacion_antecedentes: e.currentTarget.value })}
          />
        </Grid.Col>
      </Grid>
    </Stack>
  )
}
