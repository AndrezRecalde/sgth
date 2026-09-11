'use client'

import { Alert, Divider, Grid, Select, Text, Textarea } from '@mantine/core'
import { DatePickerInput, TimeInput } from '@mantine/dates'
import { Controller, useWatch, type UseFormReturn } from 'react-hook-form'
import { IconInfoCircle } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { fromDate, toDate } from '../utils/fechas'
import {
  esTipoRetroactivo,
  fechaMasAntiguaAdmitida,
  type PermisoFormData,
} from './permiso.schema'
import { TIPO_OPCIONES } from './permisos.constants'

interface Props {
  form: UseFormReturn<PermisoFormData>
}

/** Qué permiso es y cuándo: tipo, fecha, horario y observación. */
export function PermisoDatosCampos({ form }: Props) {
  const contained = useContainedInput()
  const { control, register, formState: { errors } } = form
  const tipo = useWatch({ control, name: 'tipo' })
  const esRetroactivo = esTipoRetroactivo(tipo)

  return (
    <>
      <Divider label="Datos del permiso" labelPosition="left" />

      <Controller
        name="tipo"
        control={control}
        render={({ field }) => (
          <Select
            label="Tipo de permiso"
            data={TIPO_OPCIONES}
            {...contained}
            value={field.value}
            onChange={(v) => field.onChange(v ?? 'personal')}
            error={errors.tipo?.message}
          />
        )}
      />

      {tipo === 'personal' && (
        <Alert icon={<IconInfoCircle size={14} />} color="orange" variant="light" py={6}>
          <Text size="xs">
            Máximo 4 horas <b>por día</b> — se suman los permisos personales
            que el servidor ya tenga esa fecha. Se descuentan del saldo de
            vacaciones, así que hace falta un período abierto con saldo
            suficiente, y solo en días laborables: ni sábados, ni domingos, ni
            feriados.
          </Text>
        </Alert>
      )}

      {esRetroactivo && (
        <Alert icon={<IconInfoCircle size={14} />} color="blue" variant="light" py={6}>
          <Text size="xs">
            Se registra con la fecha en que ocurrió, nunca a futuro y como
            mucho dos días hábiles atrás: el respaldo tiene 72 horas laborables
            desde esa fecha para llegar a Recepción.
          </Text>
        </Alert>
      )}

      <Controller
        name="fecha"
        control={control}
        render={({ field }) => (
          <DatePickerInput
            label="Fecha del permiso"
            placeholder="Seleccionar fecha"
            valueFormat="YYYY-MM-DD"
            // Igual que en el backend: ningún tipo admite una fecha cuyo plazo
            // de respaldo ya venció (dos días hábiles atrás como mucho), y
            // enfermedad y calamidad además nunca a futuro.
            minDate={fechaMasAntiguaAdmitida()}
            maxDate={esRetroactivo ? new Date() : undefined}
            // El personal no se admite en sábado ni domingo: descontaría
            // vacaciones por un día sin jornada. Los feriados también se
            // rechazan, pero los decide el backend: este calendario no los
            // conoce.
            excludeDate={
              tipo === 'personal'
                ? (d) => [0, 6].includes(toDate(d)?.getDay() ?? -1)
                : undefined
            }
            {...contained}
            value={toDate(field.value)}
            onChange={(d) => field.onChange(fromDate(d ?? null) ?? '')}
            error={errors.fecha?.message}
          />
        )}
      />

      <Grid>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <TimeInput
            label="Hora inicio"
            {...contained}
            {...register('hora_inicio')}
            error={errors.hora_inicio?.message}
          />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <TimeInput
            label="Hora fin"
            {...contained}
            {...register('hora_fin')}
            error={errors.hora_fin?.message}
          />
        </Grid.Col>
      </Grid>

      <Textarea
        label="Observación"
        placeholder={
          tipo === 'oficial'
            ? 'Requerido para permisos oficiales'
            : 'Motivo del permiso (opcional)'
        }
        autosize
        minRows={4}
        maxRows={6}
        {...contained}
        {...register('observacion')}
        error={errors.observacion?.message}
      />
    </>
  )
}
