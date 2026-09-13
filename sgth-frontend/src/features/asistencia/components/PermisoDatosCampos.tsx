'use client'

import { Alert, Divider, Grid, Select, Text, Textarea } from '@mantine/core'
import { DatePickerInput, TimeInput } from '@mantine/dates'
import { Controller, useWatch, type UseFormReturn } from 'react-hook-form'
import { IconInfoCircle } from '@tabler/icons-react'
import { SEMANTIC_COLOR } from '@/config/design.tokens'
import { useContainedInput } from '@/hooks/useContainedInput'
import { fromDate, toDate } from '../utils/fechas'
import { duracion, minutosEntre } from '../utils/horarioPermiso'
import {
  esTipoRetroactivo,
  fechaMasAntiguaAdmitida,
  type PermisoFormData,
} from './permiso.schema'
import { TIPO_OPCIONES } from './permisos.constants'

/** El tope diario del permiso personal. Lo aplica el backend; aquí solo se avisa. */
const MAX_MINUTOS_PERSONAL = 240

interface Props {
  form: UseFormReturn<PermisoFormData>
}

/** Qué permiso es y cuándo: tipo, fecha, horario y observación. */
export function PermisoDatosCampos({ form }: Props) {
  const contained = useContainedInput()
  const { control, register, clearErrors, formState: { errors } } = form
  const [tipo, horaInicio, horaFin] = useWatch({
    control,
    name: ['tipo', 'hora_inicio', 'hora_fin'],
  })
  const esRetroactivo = esTipoRetroactivo(tipo)
  const esOficial = tipo === 'oficial'

  const minutos = horaInicio && horaFin ? minutosEntre(horaInicio, horaFin) : NaN
  const hayHorario = Number.isFinite(minutos)
  const horarioInvertido = hayHorario && minutos <= 0
  const excedeTope = tipo === 'personal' && minutos > MAX_MINUTOS_PERSONAL

  return (
    <>
      {/* Separa a quién es el permiso de qué permiso es: en gris y a 12 px no se notaba. */}
      <Divider
        label={<Text size="sm" fw={600}>Datos del permiso</Text>}
        labelPosition="left"
      />

      <Controller
        name="tipo"
        control={control}
        render={({ field }) => (
          <Select
            label="Tipo de permiso"
            data={TIPO_OPCIONES}
            {...contained}
            value={field.value}
            onChange={(v) => {
              field.onChange(v ?? 'personal')
              // La observación solo es obligatoria en el oficial: al cambiar de
              // tipo, el error que dejó ya no aplica.
              if (v !== 'oficial') clearErrors('observacion')
            }}
            error={errors.tipo?.message}
          />
        )}
      />

      {/* Una frase por aviso: el del personal eran 257 caracteres en naranja. */}
      {tipo === 'personal' && (
        <Alert icon={<IconInfoCircle size={14} />} color="blue" variant="light" py={6}>
          <Text size="xs">
            Hasta 4 horas por día, que se descuentan de las vacaciones. Solo en
            días laborables.
          </Text>
        </Alert>
      )}

      {esRetroactivo && (
        <Alert icon={<IconInfoCircle size={14} />} color="blue" variant="light" py={6}>
          <Text size="xs">
            Se registra con la fecha en que ocurrió: hasta dos días hábiles
            atrás y nunca a futuro.
          </Text>
        </Alert>
      )}

      {/*
        Fecha y horario en una sola fila: juntos dicen «cuándo». En el teléfono
        se apilan, como pide `base: 12` (regla 07).
      */}
      <Grid>
        <Grid.Col span={{ base: 12, sm: 4 }}>
          <Controller
            name="fecha"
            control={control}
            render={({ field }) => (
              <DatePickerInput
                label="Fecha del permiso"
                placeholder="Seleccionar fecha"
                valueFormat="YYYY-MM-DD"
                // Igual que en el backend: ningún tipo admite una fecha cuyo
                // plazo de respaldo ya venció (dos días hábiles atrás como
                // mucho), y enfermedad y calamidad además nunca a futuro.
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
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 4 }}>
          <TimeInput
            label="Hora inicio"
            {...contained}
            {...register('hora_inicio')}
            error={errors.hora_inicio?.message}
          />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 4 }}>
          <TimeInput
            label="Hora fin"
            {...contained}
            {...register('hora_fin')}
            error={errors.hora_fin?.message}
          />
        </Grid.Col>
      </Grid>

      {/* La duración a la vista: el tope del personal se cuenta en horas. */}
      {hayHorario && (
        <Text
          size="xs"
          c={horarioInvertido || excedeTope ? SEMANTIC_COLOR.warning : 'dimmed'}
        >
          {horarioInvertido
            ? 'La hora de fin tiene que ser posterior a la de inicio.'
            : excedeTope
              ? `Duración: ${duracion(horaInicio, horaFin)}. Supera las 4 horas del permiso personal.`
              : `Duración: ${duracion(horaInicio, horaFin)}`}
        </Text>
      )}

      <Textarea
        label="Observación"
        withAsterisk={esOficial}
        placeholder={
          esOficial
            ? 'Describa la comisión o diligencia oficial'
            : 'Motivo del permiso (opcional)'
        }
        autosize
        minRows={2}
        maxRows={6}
        {...contained}
        {...register('observacion')}
        error={errors.observacion?.message}
      />
    </>
  )
}
