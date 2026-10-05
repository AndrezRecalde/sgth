'use client'

import { Grid, Select, Stack, TextInput } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { Controller, type Control, type FieldErrors, type UseFormRegister } from 'react-hook-form'
import { SectionHeading } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { fromDateValueOrNull, toDateValue } from '@/lib/fecha'
import type { InscripcionFormData } from '../../schemas/postulante.schema'
import { ESTADO_CIVIL_OPTIONS, GENERO_OPTIONS, TIPO_SANGRE_OPTIONS } from '../../constants/postulante'

interface Props {
  control:  Control<InscripcionFormData>
  register: UseFormRegister<InscripcionFormData>
  errors:   FieldErrors<InscripcionFormData>
}

const Col = ({ md, children }: { md: number; children: React.ReactNode }) => (
  <Grid.Col span={{ base: 12, md }}>{children}</Grid.Col>
)

/** Identificación, nombres y datos demográficos del candidato. */
export function DatosPostulanteCampos({ control, register, errors }: Props) {
  const contained = useContainedInput()
  const texto = (campo: keyof InscripcionFormData, label: string, placeholder: string, extra: object = {}) => (
    <TextInput label={label} placeholder={placeholder} {...extra} {...contained}
      {...register(campo)} error={errors[campo]?.message} />
  )
  const opcion = (campo: 'genero' | 'estado_civil' | 'tipo_sangre', label: string,
    data: { value: string; label: string }[], requerido = false) => (
    <Controller name={campo} control={control} render={({ field }) => (
      <Select label={label} data={data} required={requerido} clearable={!requerido} {...contained}
        value={field.value ?? null} onChange={(v) => field.onChange(v)} error={errors[campo]?.message} />
    )} />
  )

  return (
    <Stack gap="md">
      <SectionHeading title="Identificación" />
      <Grid>
        <Col md={4}>{texto('cedula', 'Cédula de ciudadanía', '0802704171', { required: true })}</Col>
        <Col md={4}>{texto('correo', 'Correo electrónico', 'candidato@correo.com',
          { required: true, description: 'Para notificaciones del proceso' })}</Col>
        <Col md={4}>{texto('telefono', 'Teléfono', '0991234567')}</Col>
      </Grid>

      <SectionHeading title="Nombres y apellidos" />
      <Grid>
        <Col md={6}>{texto('nombres', 'Primer nombre', 'Primer nombre', { required: true })}</Col>
        <Col md={6}>{texto('segundo_nombre', 'Segundo nombre', 'Opcional')}</Col>
        <Col md={6}>{texto('apellidos', 'Primer apellido', 'Primer apellido', { required: true })}</Col>
        <Col md={6}>{texto('segundo_apellido', 'Segundo apellido', 'Opcional')}</Col>
      </Grid>

      <SectionHeading title="Datos demográficos" />
      <Grid>
        <Col md={4}>{opcion('genero', 'Género', GENERO_OPTIONS, true)}</Col>
        <Col md={4}>{opcion('estado_civil', 'Estado civil', ESTADO_CIVIL_OPTIONS)}</Col>
        <Col md={4}>{opcion('tipo_sangre', 'Tipo de sangre', TIPO_SANGRE_OPTIONS)}</Col>
        <Col md={4}>
          <Controller name="fecha_nacimiento" control={control} render={({ field }) => (
            <DatePickerInput label="Fecha de nacimiento" valueFormat="DD/MM/YYYY" clearable maxDate={new Date()}
              {...contained} value={toDateValue(field.value)} onChange={(d) => field.onChange(fromDateValueOrNull(d))}
              error={errors.fecha_nacimiento?.message} />
          )} />
        </Col>
      </Grid>
    </Stack>
  )
}
