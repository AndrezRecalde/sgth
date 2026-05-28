'use client'

import { Grid, Text, Alert } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import '@mantine/dates/styles.css'
import { Controller, useFormContext } from 'react-hook-form'
import { IconInfoCircle } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import type { ServidorLaboralFormData } from '../schemas/servidorLaboral.schema'

const toDate = (v?: string | null): Date | null => {
  if (!v) return null
  const datePart = v.split('T')[0]
  const [year, month, day] = datePart.split('-').map(Number)
  return new Date(year, month - 1, day)
}

const fromDate = (d: Date | string | null): string | null => {
  if (!d) return null
  const date = typeof d === 'string' ? toDate(d) : d
  if (!date || isNaN(date.getTime())) return null
  const year  = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day   = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

export function ServidorFormLaboral() {
  const contained = useContainedInput()
  const { control, formState: { errors } } =
    useFormContext<ServidorLaboralFormData>()

  return (
    <Grid>
      <Grid.Col span={12}>
        <Alert
          icon={<IconInfoCircle size={16} />}
          color="blue"
          variant="light"
          radius="md"
        >
          <Text size="xs">
            Estos datos provienen de la resolución oficial de nombramiento.
            La fecha de ingreso a la institución es obligatoria para
            el cálculo de antigüedad y beneficios LOSEP.
          </Text>
        </Alert>
      </Grid.Col>

      <Grid.Col span={{ base: 12, sm: 6 }}>
        <Controller
          name="fecha_ingreso_institucion"
          control={control}
          render={({ field }) => (
            <DatePickerInput
              label="Fecha de ingreso a la institución"
              placeholder="Seleccionar fecha"
              description="Fecha en que ingresó al GAD Esmeraldas"
              valueFormat="YYYY-MM-DD"
              maxDate={new Date()}
              {...contained}
              value={toDate(field.value)}
              onChange={(d) => field.onChange(fromDate(d) ?? '')}
              error={errors.fecha_ingreso_institucion?.message}
            />
          )}
        />
      </Grid.Col>

      <Grid.Col span={{ base: 12, sm: 6 }}>
        <Controller
          name="fecha_ingreso_sector_publico"
          control={control}
          render={({ field }) => (
            <DatePickerInput
              label="Fecha de ingreso al sector público"
              placeholder="Seleccionar fecha (opcional)"
              description="Puede ser en otra institución anterior al GAD"
              valueFormat="YYYY-MM-DD"
              clearable
              maxDate={new Date()}
              {...contained}
              value={toDate(field.value)}
              onChange={(d) => field.onChange(fromDate(d))}
              error={errors.fecha_ingreso_sector_publico?.message}
            />
          )}
        />
      </Grid.Col>

      <Grid.Col span={{ base: 12, sm: 6 }}>
        <Controller
          name="fecha_nombramiento"
          control={control}
          render={({ field }) => (
            <DatePickerInput
              label="Fecha de nombramiento"
              placeholder="Seleccionar fecha (opcional)"
              description="Fecha de la resolución de nombramiento oficial"
              valueFormat="YYYY-MM-DD"
              clearable
              maxDate={new Date()}
              {...contained}
              value={toDate(field.value)}
              onChange={(d) => field.onChange(fromDate(d))}
              error={errors.fecha_nombramiento?.message}
            />
          )}
        />
      </Grid.Col>
    </Grid>
  )
}
