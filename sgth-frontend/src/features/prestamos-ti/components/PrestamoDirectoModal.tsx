'use client'

import { Checkbox, Grid, MultiSelect, Stack, Text, TextInput, Textarea } from '@mantine/core'
import { DateTimePicker } from '@mantine/dates'
import { Controller, useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { fromDateTimeValue } from '@/lib/fecha'
import { BuscarServidorSelect } from '@/features/expediente/components/BuscarServidorSelect'
import { useAgendaTic, usePrestamoTicMutations } from '../hooks/usePrestamosTic'
import { directoSchema, type DirectoFormData } from '../schemas/tramite.schema'
import { isoDia } from '../utils/agenda'
import { CondicionEquipo } from './CondicionEquipo'

const SELECTOR_HORA = { withDropdown: true, popoverProps: { withinPortal: false }, format: '24h' as const }
const CAMPOS = ['servidor_id', 'items', 'fin_previsto', 'motivo', 'lugar_uso', 'fuera_del_edificio', 'observaciones'] as const

/**
 * El préstamo directo: el servidor llega al mostrador y se lleva el equipo.
 * Se registra y se entrega en el mismo paso, desde ahora.
 */
export function PrestamoDirectoModal({ opened, onClose }: { opened: boolean; onClose: () => void }) {
  return opened ? <Formulario onClose={onClose} /> : null
}

function Formulario({ onClose }: { onClose: () => void }) {
  const contained = useContainedInput()
  const { directo } = usePrestamoTicMutations()
  const hoy = isoDia(new Date())
  const { data: equipos = [] } = useAgendaTic(hoy, hoy)
  const disponibles = equipos.filter((e) => e.ahora.estado === 'disponible')

  const { register, control, handleSubmit, setError, setValue, formState: { errors } } = useForm<DirectoFormData>({
    resolver: zodResolver(directoSchema),
    defaultValues: { items: [], fin_previsto: '', motivo: '', lugar_uso: '', fuera_del_edificio: false, observaciones: '' },
  })
  const items = useWatch({ control, name: 'items' })

  const elegir = (ids: string[]) => setValue('items', ids.map(Number).map((id) =>
    items.find((i) => i.bien_informatico_id === id) ?? { bien_informatico_id: id, accesorios: '', condicion_entrega: 'bueno' }), { shouldValidate: true })

  const enviar = (v: DirectoFormData) =>
    directo.mutateAsync({
      ...v, observaciones: v.observaciones || null,
      items: v.items.map((i) => ({ ...i, accesorios: i.accesorios || null })),
    })
      .then(onClose)
      .catch((e) => erroresAlFormulario(e, setError, [...CAMPOS], 'No se pudo registrar el préstamo directo'))

  return (
    <FormModal opened onClose={onClose} title="Préstamo directo" onSubmit={handleSubmit(enviar)}
      submitLabel="Entregar ahora" submitting={directo.isPending} size="lg">
      <Stack gap="md">
        <Controller name="servidor_id" control={control} render={({ field }) => (
          <BuscarServidorSelect label="A quién" required value={field.value ?? null}
            onChange={(id) => field.onChange(id ?? undefined)} error={errors.servidor_id?.message} />
        )} />
        <MultiSelect label="Equipos" placeholder="Los que están en TIC ahora" searchable withAsterisk {...contained}
          data={disponibles.map((e) => ({ value: String(e.id), label: `${e.codigo_institucional} · ${[e.tipo, e.marca, e.modelo].filter(Boolean).join(' ')}` }))}
          value={items.map((i) => String(i.bien_informatico_id))} onChange={elegir} error={errors.items?.message} />
        {items.map((i, n) => (
          <Grid key={i.bien_informatico_id} align="flex-end">
            <Grid.Col span={12}>
              <Text size="sm" fw={600}>{disponibles.find((e) => e.id === i.bien_informatico_id)?.codigo_institucional}</Text>
            </Grid.Col>
            <Grid.Col span={{ base: 12, sm: 7 }}>
              <TextInput label="Accesorios" placeholder="Ej.: Cable HDMI y control" {...contained} {...register(`items.${n}.accesorios`)} />
            </Grid.Col>
            <Grid.Col span={{ base: 12, sm: 5 }}>
              <Controller name={`items.${n}.condicion_entrega`} control={control} render={({ field }) => (
                <CondicionEquipo value={field.value} onChange={field.onChange} />
              )} />
            </Grid.Col>
          </Grid>
        ))}
        <Controller name="fin_previsto" control={control} render={({ field }) => (
          <DateTimePicker label="Lo devuelve" valueFormat="DD/MM/YYYY HH:mm" withAsterisk timePickerProps={SELECTOR_HORA}
            minDate={new Date()} error={errors.fin_previsto?.message} {...contained}
            value={field.value ? new Date(field.value) : null} onChange={(d) => field.onChange(fromDateTimeValue(d))} />
        )} />
        <Textarea label="Para qué" withAsterisk autosize minRows={2} error={errors.motivo?.message} {...contained} {...register('motivo')} />
        <TextInput label="Dónde se usa" withAsterisk error={errors.lugar_uso?.message} {...contained} {...register('lugar_uso')} />
        <Checkbox label="Se usa fuera del edificio" {...register('fuera_del_edificio')} />
        <Textarea label="Observaciones" autosize minRows={2} error={errors.observaciones?.message} {...contained} {...register('observaciones')} />
      </Stack>
    </FormModal>
  )
}
