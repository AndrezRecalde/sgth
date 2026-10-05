'use client'

import { Alert, Grid, NumberInput, Select, Text } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { IconInfoCircle } from '@tabler/icons-react'
import { Controller, useWatch, type Control, type FieldErrors } from 'react-hook-form'
import { SectionHeading } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { fromDateValue, toDateValue } from '@/lib/fecha'
import type { ConvocatoriaFormData } from '../schemas/convocatoria.schema'

const MODALIDADES = [
  { value: 'interna', label: 'Interna — Solo servidores del GADPE' },
  { value: 'externa', label: 'Externa — Público en general' },
  { value: 'mixta',   label: 'Mixta — Interna y externa' },
]

const QUIEN_POSTULA: Record<string, string> = {
  interna: 'Solo pueden postular servidores activos del GADPE',
  externa: 'Abierta al público en general',
  mixta:   'Abierta a servidores del GADPE y al público en general',
}

interface Props {
  control: Control<ConvocatoriaFormData>
  errors:  FieldErrors<ConvocatoriaFormData>
}

/** Modalidad, vacantes y período: la segunda sección del formulario. */
export function ConvocatoriaProcesoCampos({ control, errors }: Props) {
  const contained = useContainedInput()
  const tipo = useWatch({ control, name: 'tipo' })

  const fecha = (name: 'fecha_inicio' | 'fecha_fin', label: string, description: string) => (
    <Controller
      name={name}
      control={control}
      render={({ field }) => (
        <DatePickerInput
          label={label}
          description={description}
          valueFormat="DD/MM/YYYY"
          required
          {...contained}
          value={toDateValue(field.value)}
          onChange={(d) => field.onChange(fromDateValue(d))}
          error={errors[name]?.message}
        />
      )}
    />
  )

  return (
    <>
      <Grid>
        <Grid.Col span={{ base: 12, md: 6 }}>
          <Controller
            name="tipo"
            control={control}
            render={({ field }) => (
              <Select
                label="Modalidad de la convocatoria"
                description="Define quiénes pueden postular"
                data={MODALIDADES}
                allowDeselect={false}
                required
                {...contained}
                value={field.value}
                onChange={(v) => field.onChange(v ?? 'externa')}
                error={errors.tipo?.message}
              />
            )}
          />
          {tipo && <Text size="xs" c="dimmed" mt={4}>{QUIEN_POSTULA[tipo]}</Text>}
        </Grid.Col>

        <Grid.Col span={{ base: 12, md: 6 }}>
          <Controller
            name="vacantes"
            control={control}
            render={({ field }) => (
              <NumberInput
                label="Número de vacantes"
                description="Cuántas personas se incorporarán en este proceso"
                min={1}
                max={50}
                allowDecimal={false}
                required
                {...contained}
                value={field.value}
                onChange={(v) => field.onChange(Number(v) || 1)}
                error={errors.vacantes?.message}
              />
            )}
          />
        </Grid.Col>
      </Grid>

      <SectionHeading title="Período del proceso" />

      <Alert color="ocean" variant="light" icon={<IconInfoCircle size={16} />}>
        <Text size="xs">
          Las fechas definen el período oficial de la convocatoria. La
          inscripción de candidatos estará disponible mientras la convocatoria
          esté en estado <strong>Publicada</strong>.
        </Text>
      </Alert>

      <Grid>
        <Grid.Col span={{ base: 12, md: 6 }}>
          {fecha('fecha_inicio', 'Fecha de inicio', 'Inicio oficial del proceso de selección')}
        </Grid.Col>
        <Grid.Col span={{ base: 12, md: 6 }}>
          {fecha('fecha_fin', 'Fecha de cierre', 'Fecha límite de inscripción de candidatos')}
        </Grid.Col>
      </Grid>
    </>
  )
}
