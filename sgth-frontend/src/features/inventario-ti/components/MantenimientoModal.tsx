'use client'

import { Grid, NumberInput, SegmentedControl, Select, Stack, Text, TextInput, Textarea } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { Controller, useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal } from '@/components/ui'
import { useAuth } from '@/hooks/useAuth'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { fromDateValueOrNull, hoyIso, toDateValue } from '@/lib/fecha'
import { useCatalogosFormulario } from '../hooks/useCatalogosInventario'
import { useMantenimientoMutations, useTecnicosTic } from '../hooks/useMantenimientos'
import { mantenimientoSchema, type MantenimientoFormData } from '../schemas/mantenimiento.schema'
import type { BienInformatico } from '../services/tipos'
import { BuscarEquipoSelect } from './BuscarEquipoSelect'

const TIPOS = [
  { value: 'correctivo', label: 'Correctivo (reparación)' },
  { value: 'preventivo', label: 'Preventivo' },
  { value: 'repotenciacion', label: 'Repotenciación (mejora)' },
]

const ESTADOS = [
  { value: 'disponible', label: 'Vuelve al servicio' },
  { value: 'en_mantenimiento', label: 'Sigue en mantenimiento' },
  { value: 'en_garantia', label: 'Queda con el proveedor' },
]

interface Props {
  opened: boolean
  onClose: () => void
  /** El equipo de la ficha; sin él, se busca. */
  bien?: BienInformatico | null
}

/** Registrar lo que se le hizo a un equipo, y en qué estado queda. */
export function MantenimientoModal({ opened, ...props }: Props) {
  return opened ? <Formulario {...props} /> : null
}

function Formulario({ onClose, bien }: Omit<Props, 'opened'>) {
  const contained = useContainedInput()
  const { usuario } = useAuth()
  const { registrar } = useMantenimientoMutations()
  const { data: catalogos } = useCatalogosFormulario()
  const { data: tecnicos = [] } = useTecnicosTic()

  const { register, control, handleSubmit, setError, formState: { errors } } = useForm<MantenimientoFormData>({
    resolver: zodResolver(mantenimientoSchema),
    defaultValues: {
      bien_informatico_id: bien?.id ?? Number.NaN, tipo_mantenimiento: 'correctivo', realizado_por: 'tic',
      contrato_vigencia_id: bien?.contrato_vigencia_id ?? null, fecha_mantenimiento: hoyIso(),
      tecnico_id: usuario?.id ?? null, descripcion: '', costo: 0, soporte_anterior: '', estado_equipo: null,
    },
  })
  const porProveedor = useWatch({ control, name: 'realizado_por' }) === 'proveedor'

  const enviar = (v: MantenimientoFormData) =>
    registrar.mutateAsync({ ...v, soporte_anterior: v.soporte_anterior.trim() || null })
      .then(onClose)
      .catch((e) => erroresAlFormulario(e, setError, Object.keys(mantenimientoSchema.shape), null))

  const opcionNumero = (lista: { id: number; nombre?: string; proceso?: string }[]) =>
    lista.map((x) => ({ value: String(x.id), label: x.nombre ?? x.proceso ?? '' }))

  return (
    <FormModal opened onClose={onClose} title="Registrar mantenimiento" onSubmit={handleSubmit(enviar)}
      submitLabel="Registrar mantenimiento" submitting={registrar.isPending} size="lg">
      <Stack gap="md">
        {bien ? (
          <Text size="sm">Equipo <b>{bien.codigo_institucional}</b> · {[bien.tipo?.nombre, bien.marca?.nombre, bien.modelo].filter(Boolean).join(' ')}</Text>
        ) : (
          <Controller name="bien_informatico_id" control={control} render={({ field }) => (
            <BuscarEquipoSelect label="Equipo" soloPrincipales={false} placeholder="Código, serie o modelo"
              value={Number.isNaN(field.value) ? null : field.value} onChange={(id) => field.onChange(id ?? Number.NaN)}
              error={errors.bien_informatico_id?.message} />
          )} />
        )}
        <Controller name="realizado_por" control={control} render={({ field }) => (
          <SegmentedControl fullWidth value={field.value} onChange={field.onChange}
            data={[{ value: 'tic', label: 'Lo hizo TIC' }, { value: 'proveedor', label: 'Lo hizo el proveedor' }]} />
        )} />
        <Grid>
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <Controller name="tipo_mantenimiento" control={control} render={({ field }) => (
              <Select label="Tipo" data={TIPOS} allowDeselect={false} withAsterisk error={errors.tipo_mantenimiento?.message}
                {...contained} value={field.value} onChange={(v) => v && field.onChange(v)} />
            )} />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <Controller name="fecha_mantenimiento" control={control} render={({ field }) => (
              <DatePickerInput label="Fecha" valueFormat="DD/MM/YYYY" withAsterisk maxDate={hoyIso()}
                error={errors.fecha_mantenimiento?.message} {...contained}
                value={toDateValue(field.value)} onChange={(d) => field.onChange(fromDateValueOrNull(d) ?? '')} />
            )} />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 6 }}>
            {porProveedor ? (
              <Controller name="contrato_vigencia_id" control={control} render={({ field }) => (
                <Select label="Contrato de vigencia" placeholder="El del equipo" data={opcionNumero(catalogos?.contratos ?? [])}
                  clearable searchable error={errors.contrato_vigencia_id?.message} {...contained}
                  value={field.value ? String(field.value) : null} onChange={(v) => field.onChange(v ? Number(v) : null)} />
              )} />
            ) : (
              <Controller name="tecnico_id" control={control} render={({ field }) => (
                <Select label="Técnico" data={opcionNumero(tecnicos)} searchable error={errors.tecnico_id?.message}
                  {...contained} value={field.value ? String(field.value) : null} onChange={(v) => field.onChange(v ? Number(v) : null)} />
              )} />
            )}
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <Controller name="costo" control={control} render={({ field }) => (
              <NumberInput label="Costo (USD)" min={0} decimalScale={2} hideControls error={errors.costo?.message}
                {...contained} value={field.value} onChange={(v) => field.onChange(typeof v === 'number' ? v : 0)} />
            )} />
          </Grid.Col>
          <Grid.Col span={12}>
            <Textarea label="Qué se hizo" placeholder="Ej.: Cambio de la fuente de poder y limpieza interna." withAsterisk
              autosize minRows={2} error={errors.descripcion?.message} {...contained} {...register('descripcion')} />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: catalogos?.acepta_soporte_anterior ? 6 : 12 }}>
            <Controller name="estado_equipo" control={control} render={({ field }) => (
              <Select label="El equipo queda" placeholder="Sin cambios en su estado" data={ESTADOS} clearable
                error={errors.estado_equipo?.message} {...contained} value={field.value} onChange={(v) => field.onChange(v)} />
            )} />
          </Grid.Col>
          {catalogos?.acepta_soporte_anterior && (
            <Grid.Col span={{ base: 12, sm: 6 }}>
              <TextInput label="Soporte (sistema anterior)" placeholder="Ej.: 48700"
                error={errors.soporte_anterior?.message} {...contained} {...register('soporte_anterior')} />
            </Grid.Col>
          )}
        </Grid>
      </Stack>
    </FormModal>
  )
}
