'use client'

import { FileInput, Select, Stack, TextInput } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { Controller, useWatch, type UseFormReturn } from 'react-hook-form'
import { useContainedInput } from '@/hooks/useContainedInput'
import { fromDateValue, fromDateValueOrNull, toDateValue } from '@/lib/fecha'
import type { HistorialAcademicoFormData } from '../schemas/historialAcademico.schema'

const TIPO_OPTIONS = [
  { value: 'estudio',      label: 'Título Académico' },
  { value: 'capacitacion', label: 'Capacitación / Curso' },
]

const NIVEL_OPTIONS = [
  { value: 'primaria',     label: 'Primaria' },
  { value: 'secundaria',   label: 'Secundaria' },
  { value: 'tercer_nivel', label: 'Tercer nivel (Pregrado)' },
  { value: 'cuarto_nivel', label: 'Cuarto nivel (Posgrado)' },
]

const NACIONALIDAD_OPTIONS = [
  { value: 'nacional',      label: 'Nacional' },
  { value: 'internacional', label: 'Internacional' },
]

interface Props {
  form: UseFormReturn<HistorialAcademicoFormData>
  /**
   * El PDF del título o del certificado. Lo pide el portal, donde lo que
   * registra el servidor lo valida Talento Humano con ese documento.
   */
  conDocumento?: boolean
}

/**
 * Los campos de un título o curso. Los comparten el modal del expediente
 * (Talento Humano) y el de «Mi perfil» del portal (el servidor).
 */
export function HistorialAcademicoCampos({ form, conDocumento = false }: Props) {
  const contained = useContainedInput()
  const { register, control, formState: { errors } } = form
  const tipoEstudio = useWatch({ control, name: 'tipo_estudio' })

  return (
    <Stack gap="sm">
      <Controller name="tipo_estudio" control={control}
        render={({ field }) => (
          <Select label="Tipo de registro"
            data={TIPO_OPTIONS} {...contained} allowDeselect={false}
            value={field.value} onChange={field.onChange}
            error={errors.tipo_estudio?.message} />
        )} />

      {tipoEstudio === 'estudio' && (
        <Controller name="nivel_estudio" control={control}
          render={({ field }) => (
            <Select label="Nivel de instrucción"
              data={NIVEL_OPTIONS} {...contained}
              value={field.value ?? ''} onChange={field.onChange}
              error={errors.nivel_estudio?.message} />
          )} />
      )}

      <Controller name="nacionalidad_estudio" control={control}
        render={({ field }) => (
          <Select label="Nacionalidad de la institución"
            data={NACIONALIDAD_OPTIONS} {...contained} allowDeselect={false}
            value={field.value} onChange={field.onChange}
            error={errors.nacionalidad_estudio?.message} />
        )} />

      <TextInput label="Título / Capacitación obtenida"
        placeholder="Ej: Ingeniero en Sistemas, Certificado Scrum Master"
        {...contained} {...register('titulo_capacitacion')}
        error={errors.titulo_capacitacion?.message} />

      <TextInput label="Institución educativa"
        placeholder="Nombre de la institución o centro de estudios"
        {...contained} {...register('institucion')}
        error={errors.institucion?.message} />

      {/* El selector de fechas del resto del sistema: el `type="date"`
          nativo cambiaba de aspecto y de formato según el navegador, y su
          placeholder no se veía nunca. */}
      <Controller name="fecha_inicio" control={control}
        render={({ field }) => (
          <DatePickerInput label="Fecha de inicio"
            placeholder="Seleccionar fecha"
            valueFormat="DD/MM/YYYY"
            maxDate={new Date()}
            {...contained}
            value={toDateValue(field.value)}
            onChange={(d) => field.onChange(fromDateValue(d))}
            error={errors.fecha_inicio?.message} />
        )} />

      <Controller name="fecha_fin" control={control}
        render={({ field }) => (
          <DatePickerInput label="Fecha de fin (opcional)"
            placeholder="Vacío si sigue cursando"
            valueFormat="DD/MM/YYYY"
            clearable
            {...contained}
            value={toDateValue(field.value)}
            onChange={(d) => field.onChange(fromDateValueOrNull(d))}
            error={errors.fecha_fin?.message} />
        )} />

      {tipoEstudio === 'estudio' && (
        <TextInput label="Código de registro SENESCYT (opcional)"
          placeholder="Ej: 1005-2021-2245367"
          {...contained} {...register('codigo_senescyt')}
          error={errors.codigo_senescyt?.message} />
      )}

      {conDocumento && (
        <Controller name="documento" control={control}
          render={({ field }) => (
            <FileInput
              label="Documento (PDF)"
              placeholder="El título, el certificado o el registro de la SENESCYT"
              accept="application/pdf"
              withAsterisk
              clearable
              {...contained}
              value={field.value ?? null}
              onChange={field.onChange}
              error={errors.documento?.message} />
          )} />
      )}
    </Stack>
  )
}
