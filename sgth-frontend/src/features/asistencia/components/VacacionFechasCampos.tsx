'use client'

import { Alert, Grid, NumberInput, Select, Stack, Text, Textarea } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { IconInfoCircle } from '@tabler/icons-react'
import { Controller, type UseFormReturn } from 'react-hook-form'
import { useContainedInput } from '@/hooks/useContainedInput'
import { fromDateValue, toDateValue } from '@/lib/fecha'
import type { ConteoVacaciones } from '../services/asistenciaService'
import type { VacacionFormData } from './vacacion.schema'
import classes from './VacacionFechasCampos.module.css'
import { SectionHeading } from '@/components/ui'

const FECHAS = [
  { name: 'fecha_inicio',  label: 'Fecha inicio' },
  { name: 'fecha_fin',     label: 'Fecha fin' },
] as const

interface Props {
  form: UseFormReturn<VacacionFormData>
  /** Lo que el backend contó para las fechas: los días y el regreso. */
  conteo?: ConteoVacaciones | null
}

/**
 * Cuándo: las fechas, los días que salen de ellas y la observación.
 *
 * Los días, su tipo y el regreso no se escriben: los cuenta el backend a
 * partir de las fechas (`useVacacionForm`), en días calendario y, desde el
 * 2026-10-20, con el descanso que sigue a la parte hasta la víspera del
 * regreso (criterio del Ministerio del Trabajo).
 */
export function VacacionFechasCampos({ form, conteo = null }: Props) {
  const contained = useContainedInput()
  const { control, register, formState: { errors } } = form
  // Termina antes de un descanso: cuenta más días de los que pide.
  const conDescanso = !!conteo && conteo.dias > conteo.dias_del_rango

  return (
    <>
      <SectionHeading title="Fechas" />

      <Grid>
        {FECHAS.map(({ name, label }) => (
          <Grid.Col key={name} span={{ base: 12, sm: 4 }}>
            <Controller
              name={name}
              control={control}
              render={({ field }) => (
                <DatePickerInput
                  label={label}
                  placeholder="Seleccionar"
                  valueFormat="YYYY-MM-DD"
                  {...contained}
                  value={toDateValue(field.value)}
                  onChange={(d) => field.onChange(fromDateValue(d ?? null))}
                  error={errors[name]?.message}
                />
              )}
            />
          </Grid.Col>
        ))}

        <Grid.Col span={{ base: 12, sm: 4 }}>
          <Controller
            name="fecha_retorno"
            control={control}
            render={({ field }) => (
              <Stack gap={4}>
                <DatePickerInput
                  label="Fecha de retorno"
                  placeholder="Se calcula"
                  valueFormat="YYYY-MM-DD"
                  readOnly
                  className={classes.calculado}
                  {...contained}
                  value={toDateValue(field.value)}
                  error={errors.fecha_retorno?.message}
                />
                <Text size="xs" c="dimmed">El primer día laborable después del fin</Text>
              </Stack>
            )}
          />
        </Grid.Col>

        <Grid.Col span={{ base: 12, sm: 4 }}>
          <Controller
            name="dias_solicitados"
            control={control}
            render={({ field }) => (
              <Stack gap={4}>
                <NumberInput
                  label="Días solicitados"
                  min={0}
                  max={365}
                  readOnly
                  className={`${classes.calculado} ${classes.dias}`}
                  {...contained}
                  value={field.value}
                  error={errors.dias_solicitados?.message}
                />
                <Text size="xs" c="dimmed">
                  Días calendario, desde el inicio hasta la víspera del retorno
                </Text>
              </Stack>
            )}
          />
        </Grid.Col>

        <Grid.Col span={{ base: 12, sm: 4 }}>
          <Controller
            name="tipo_dias"
            control={control}
            render={({ field }) => (
              <Stack gap={4}>
                <Select
                  label="Tipo de días"
                  data={[{ value: 'calendario', label: 'Calendario' }]}
                  readOnly
                  className={classes.calculado}
                  {...contained}
                  value={field.value}
                />
                <Text size="xs" c="dimmed">
                  La LOSEP y el Código del Trabajo cuentan días calendario
                </Text>
              </Stack>
            )}
          />
        </Grid.Col>
      </Grid>

      {conDescanso && (
        <Alert variant="light" color="amber" icon={<IconInfoCircle size={16} />}>
          Termina antes de un fin de semana o un feriado: se cuentan también esos días, hasta la víspera del
          retorno ({conteo?.dias} en vez de {conteo?.dias_del_rango}), como dispone el Ministerio del Trabajo. Si es un
          día suelto, conviene pedirlo como permiso con cargo a vacaciones (Reglamento de la LOSEP, Art. 32), que
          descuenta solo ese día.
        </Alert>
      )}

      <Textarea
        label="Observación"
        placeholder="Observaciones adicionales (opcional)"
        rows={2}
        {...contained}
        {...register('observacion')}
      />
    </>
  )
}
