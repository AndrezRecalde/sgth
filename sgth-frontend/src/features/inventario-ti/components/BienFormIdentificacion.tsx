'use client'

import { Grid, Select, TextInput } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { Controller, useWatch, type UseFormReturn } from 'react-hook-form'
import { SectionCard } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { fromDateValueOrNull, hoyIso, toDateValue } from '@/lib/fecha'
import type { BienFormData } from '../schemas/bien.schema'
import type { CatalogosInventario } from '../services/tipos'

interface Props {
  form: UseFormReturn<BienFormData>
  catalogos: CatalogosInventario
}

const aNumero = (v: string | null) => (v ? Number(v) : null)

/** Qué equipo es: sus códigos, su tipo, marca y modelo, y cuándo se compró. */
export function BienFormIdentificacion({ form, catalogos }: Props) {
  const contained = useContainedInput()
  const { register, control, setValue, formState: { errors } } = form
  const tipoId = useWatch({ control, name: 'tipo_bien_id' })
  const subtipos = catalogos.tipos.find((t) => t.id === tipoId)?.subtipos ?? []

  return (
    <SectionCard title="Identificación">
      <Grid>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <TextInput
            label="Código institucional"
            placeholder="El que asignó Activos Fijos. Ej.: 001774"
            withAsterisk
            error={errors.codigo_institucional?.message}
            {...contained}
            {...register('codigo_institucional')}
          />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <TextInput
            label="Código anterior"
            placeholder="Ej.: 007-01-010-04047"
            error={errors.codigo_anterior?.message}
            {...contained}
            {...register('codigo_anterior')}
          />
        </Grid.Col>

        <Grid.Col span={{ base: 12, sm: 6 }}>
          <Controller name="tipo_bien_id" control={control} render={({ field }) => (
            <Select
              label="Tipo de equipo"
              placeholder="Seleccione el tipo"
              data={catalogos.tipos.map((t) => ({ value: String(t.id), label: t.nombre }))}
              withAsterisk
              searchable
              allowDeselect={false}
              error={errors.tipo_bien_id?.message}
              {...contained}
              value={field.value ? String(field.value) : null}
              onChange={(v) => {
                field.onChange(Number(v))
                // El subtipo es de un tipo: si cambia el tipo, deja de servir.
                setValue('subtipo_bien_id', null)
              }}
            />
          )} />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <Controller name="subtipo_bien_id" control={control} render={({ field }) => (
            <Select
              label="Subtipo"
              placeholder={subtipos.length ? 'Opcional' : 'Este tipo no tiene subtipos'}
              data={subtipos.map((s) => ({ value: String(s.id), label: s.nombre }))}
              disabled={!subtipos.length}
              clearable
              error={errors.subtipo_bien_id?.message}
              {...contained}
              value={field.value ? String(field.value) : null}
              onChange={(v) => field.onChange(aNumero(v))}
            />
          )} />
        </Grid.Col>

        <Grid.Col span={{ base: 12, sm: 6, lg: 4 }}>
          <Controller name="marca_id" control={control} render={({ field }) => (
            <Select
              label="Marca"
              placeholder="Seleccione la marca"
              data={catalogos.marcas.map((m) => ({ value: String(m.id), label: m.nombre }))}
              withAsterisk
              searchable
              allowDeselect={false}
              nothingFoundMessage="No está: regístrela en Configuración"
              error={errors.marca_id?.message}
              {...contained}
              value={field.value ? String(field.value) : null}
              onChange={(v) => field.onChange(Number(v))}
            />
          )} />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6, lg: 4 }}>
          <TextInput
            label="Modelo"
            placeholder="Ej.: OptiPlex 7480"
            error={errors.modelo?.message}
            {...contained}
            {...register('modelo')}
          />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6, lg: 4 }}>
          <TextInput
            label="Número de serie"
            placeholder="Vacío si no tiene (S/N)"
            error={errors.numero_serie?.message}
            {...contained}
            {...register('numero_serie')}
          />
        </Grid.Col>

        <Grid.Col span={{ base: 12, sm: 6, lg: 4 }}>
          <Controller name="fecha_compra" control={control} render={({ field }) => (
            <DatePickerInput
              label="Fecha de compra"
              placeholder="Seleccionar fecha"
              valueFormat="DD/MM/YYYY"
              clearable
              maxDate={hoyIso()}
              error={errors.fecha_compra?.message}
              {...contained}
              value={toDateValue(field.value)}
              onChange={(d) => field.onChange(fromDateValueOrNull(d) ?? '')}
            />
          )} />
        </Grid.Col>
      </Grid>
    </SectionCard>
  )
}
