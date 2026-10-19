'use client'

import { FileInput, Select, SimpleGrid, Stack, TextInput } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { Controller, useWatch, type UseFormReturn } from 'react-hook-form'
import { useContainedInput } from '@/hooks/useContainedInput'
import { fromDateValue, toDateValue } from '@/lib/fecha'
import { PARENTESCO_OPTIONS, SEXO_OPTIONS } from '@/features/expediente/constants/cargaFamiliar'
import type { MiCargaFamiliar } from '@/types/api'
import type { SolicitudCargaFormData } from '../schemas/solicitudCarga.schema'

interface Props {
  form: UseFormReturn<SolicitudCargaFormData>
  /** La carga que se corrige; sin ella, es un registro. */
  original?: MiCargaFamiliar | null
}

/**
 * Los datos del familiar en una solicitud de «Mi perfil». Son los del modal
 * del expediente (`CargaFamiliarModal`) más el documento, cuya etiqueta dice
 * cuándo es obligatorio: para un hijo sí, para el cónyuge o conviviente no
 * (D11). La cédula solo se escribe al registrar: una corrección no la cambia.
 */
export function CamposDelFamiliar({ form, original }: Props) {
  const contained = useContainedInput()
  const { register, control, formState: { errors } } = form
  const [parentesco, fecha] = useWatch({ control, name: ['parentesco', 'fecha_nacimiento'] })

  const esHijo = parentesco === 'hijo' || original?.parentesco === 'hijo'
  const documentoObligatorio = original
    ? esHijo && (fecha !== original.fecha_nacimiento || parentesco !== original.parentesco)
    : parentesco === 'hijo'

  const etiquetaDocumento = !original
    ? (parentesco === 'hijo'
        ? 'Partida de nacimiento o cédula (PDF)'
        : 'Acta de matrimonio o de unión de hecho (PDF, opcional)')
    : (documentoObligatorio
        ? 'Partida de nacimiento o cédula (PDF)'
        : 'Documento que respalde la corrección (PDF, opcional)')

  return (
    <Stack gap="sm">
      <TextInput
        label="Cédula"
        placeholder="Ej: 0801234567"
        maxLength={10}
        disabled={!!original}
        {...contained}
        {...register('cedula')}
        error={errors.cedula?.message}
      />
      <TextInput label="Nombres" placeholder="Nombres del familiar"
        {...contained} {...register('nombres')} error={errors.nombres?.message} />
      <TextInput label="Apellidos" placeholder="Apellidos del familiar"
        {...contained} {...register('apellidos')} error={errors.apellidos?.message} />

      <SimpleGrid cols={{ base: 1, xs: 2 }} spacing="sm">
        <Controller name="parentesco" control={control}
          render={({ field }) => (
            <Select label="Parentesco" placeholder="Seleccione" data={PARENTESCO_OPTIONS}
              allowDeselect={false} {...contained}
              value={field.value ?? null} onChange={field.onChange}
              error={errors.parentesco?.message} />
          )} />
        <Controller name="genero" control={control}
          render={({ field }) => (
            <Select label="Sexo" placeholder="Seleccione" data={SEXO_OPTIONS}
              allowDeselect={false} {...contained}
              value={field.value ?? null} onChange={field.onChange}
              error={errors.genero?.message} />
          )} />
      </SimpleGrid>

      {/* Hasta hoy: un hijo se registra desde el día en que nace (D8). */}
      <Controller name="fecha_nacimiento" control={control}
        render={({ field }) => (
          <DatePickerInput label="Fecha de nacimiento" placeholder="Seleccionar fecha"
            valueFormat="DD/MM/YYYY" maxDate={new Date()} {...contained}
            value={toDateValue(field.value)}
            onChange={(d) => field.onChange(fromDateValue(d))}
            error={errors.fecha_nacimiento?.message} />
        )} />

      <Controller name="documento" control={control}
        render={({ field }) => (
          <FileInput label={etiquetaDocumento} placeholder="Seleccione el PDF"
            accept="application/pdf" withAsterisk={documentoObligatorio} clearable
            {...contained}
            value={field.value ?? null} onChange={field.onChange}
            error={errors.documento?.message} />
        )} />
    </Stack>
  )
}
