'use client'

import { Grid, Text, Alert } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { Controller, useFormContext } from 'react-hook-form'
import { IconInfoCircle } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import type { ServidorLaboralFormData } from '../schemas/servidorLaboral.schema'
import { toDateValue, fromDateValueOrNull } from '@/lib/fecha'

interface Props {
  /** Tipo de nombramiento del contrato vigente del servidor, si existe. */
  tipoNombramiento?: string | null
  /** Del servidor; se muestra sin poder editarla. */
  fechaIngresoInstitucion?: string | null
}

export function ServidorFormLaboral({ tipoNombramiento, fechaIngresoInstitucion }: Props) {
  const contained = useContainedInput()
  const { control, formState: { errors } } =
    useFormContext<ServidorLaboralFormData>()

  const esPermanente = tipoNombramiento === 'nombramiento_permanente'

  return (
    <Grid>
      <Grid.Col span={12}>
        <Alert
          icon={<IconInfoCircle size={16} />}
          color="ocean"
          variant="light"
          radius="md"
          mb="xs"
        >
          <Text size="xs">
            La fecha de ingreso al GAD se sincroniza automáticamente con la
            fecha de inicio del contrato vigente. La fecha de nombramiento
            oficial solo aplica a Nombramiento Permanente.
          </Text>
        </Alert>
      </Grid.Col>

      <Grid.Col span={{ base: 12, sm: 6 }}>
        {/* Solo lectura: la escribe el contrato vigente, no este formulario. */}
        <DatePickerInput
          label="Fecha de ingreso al GAD"
          placeholder="Se sincroniza con el contrato vigente"
          valueFormat="YYYY-MM-DD"
          disabled
          {...contained}
          value={toDateValue(fechaIngresoInstitucion)}
        />
      </Grid.Col>

      <Grid.Col span={{ base: 12, sm: 6 }}>
        <Controller
          name="fecha_ingreso_sector_publico"
          control={control}
          render={({ field }) => (
            <DatePickerInput
              label="Fecha ingreso sector público"
              placeholder="Seleccionar fecha (opcional)"
              valueFormat="YYYY-MM-DD"
              clearable
              maxDate={new Date()}
              {...contained}
              value={toDateValue(field.value)}
              onChange={(d) => field.onChange(fromDateValueOrNull(d))}
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
              label="Fecha de nombramiento oficial"
              placeholder={
                esPermanente
                  ? 'Se sincroniza con el contrato vigente'
                  : 'Solo aplica a Nombramiento Permanente'
              }
              valueFormat="YYYY-MM-DD"
              clearable
              maxDate={new Date()}
              disabled={!esPermanente}
              {...contained}
              value={toDateValue(field.value)}
              onChange={(d) => field.onChange(fromDateValueOrNull(d))}
              error={errors.fecha_nombramiento?.message}
            />
          )}
        />
      </Grid.Col>
    </Grid>
  )
}
