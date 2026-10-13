'use client'

import { Grid, Stack, Text, TextInput, Textarea } from '@mantine/core'
import { Controller, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { usePrestamoTicMutations } from '../hooks/usePrestamosTic'
import { entregaSchema, recepcionSchema, type EntregaFormData, type RecepcionFormData } from '../schemas/tramite.schema'
import type { ItemPrestamo, PrestamoEquipo } from '../services/tipos'
import { momentoCorto } from '../utils/agenda'
import { CondicionEquipo, MomentoReal, ahoraLocal } from './CondicionEquipo'

interface Props {
  prestamo: PrestamoEquipo
  onClose: () => void
}

const nombreItem = (i: ItemPrestamo) =>
  `${i.bien?.codigo_institucional ?? ''} · ${[i.bien?.tipo, i.bien?.marca, i.bien?.modelo].filter(Boolean).join(' ')}`

/**
 * La entrega: la hora real, y por cada equipo los accesorios y en qué
 * condición sale. El préstamo queda en curso.
 */
export function EntregarPrestamoModal({ prestamo, onClose }: Props) {
  const contained = useContainedInput()
  const { entregar } = usePrestamoTicMutations()
  const items = prestamo.items ?? []
  const { register, control, handleSubmit, setError, formState: { errors } } = useForm<EntregaFormData>({
    resolver: zodResolver(entregaSchema),
    defaultValues: {
      entregado_en: ahoraLocal(), observaciones: prestamo.observaciones ?? '',
      items: items.map((i) => ({ id: i.id, accesorios: i.accesorios ?? '', condicion_entrega: 'bueno' })),
    },
  })

  const enviar = (v: EntregaFormData) =>
    entregar.mutateAsync({ id: prestamo.id, data: {
      ...v, observaciones: v.observaciones || null,
      items: v.items.map((i) => ({ ...i, accesorios: i.accesorios || null })),
    } })
      .then(onClose)
      .catch((e) => erroresAlFormulario(e, setError, ['entregado_en', 'observaciones'], 'No se pudo registrar la entrega'))

  return (
    <FormModal opened onClose={onClose} title={`Entregar ${prestamo.folio}`} onSubmit={handleSubmit(enviar)}
      submitLabel="Registrar la entrega" submitting={entregar.isPending} size="lg">
      <Stack gap="md">
        <Text size="sm">A <b>{prestamo.servidor?.nombre}</b>, para devolver el {momentoCorto(prestamo.fin_previsto)}.</Text>
        <Controller name="entregado_en" control={control} render={({ field }) => (
          <MomentoReal label="Se entregó" value={field.value} onChange={field.onChange} error={errors.entregado_en?.message} />
        )} />
        {items.map((i, n) => (
          <Grid key={i.id} align="flex-end">
            <Grid.Col span={12}><Text size="sm" fw={600}>{nombreItem(i)}</Text></Grid.Col>
            <Grid.Col span={{ base: 12, sm: 7 }}>
              <TextInput label="Accesorios" placeholder="Ej.: Cable HDMI, control y estuche"
                error={errors.items?.[n]?.accesorios?.message} {...contained} {...register(`items.${n}.accesorios`)} />
            </Grid.Col>
            <Grid.Col span={{ base: 12, sm: 5 }}>
              <Controller name={`items.${n}.condicion_entrega`} control={control} render={({ field }) => (
                <CondicionEquipo value={field.value} onChange={field.onChange} error={errors.items?.[n]?.condicion_entrega?.message} />
              )} />
            </Grid.Col>
          </Grid>
        ))}
        <Textarea label="Observaciones" autosize minRows={2} error={errors.observaciones?.message} {...contained} {...register('observaciones')} />
      </Stack>
    </FormModal>
  )
}

/**
 * La devolución: la hora real, y por cada equipo en qué condición vuelve y
 * la novedad, si la hay. La condición pasa a la ficha del equipo.
 */
export function RecibirPrestamoModal({ prestamo, onClose }: Props) {
  const contained = useContainedInput()
  const { recibir } = usePrestamoTicMutations()
  const items = prestamo.items ?? []
  const { register, control, handleSubmit, setError, formState: { errors } } = useForm<RecepcionFormData>({
    resolver: zodResolver(recepcionSchema),
    defaultValues: {
      devuelto_en: ahoraLocal(), observaciones: prestamo.observaciones ?? '',
      items: items.map((i) => ({ id: i.id, condicion_devolucion: i.condicion_entrega ?? 'bueno', novedad: '' })),
    },
  })

  const enviar = (v: RecepcionFormData) =>
    recibir.mutateAsync({ id: prestamo.id, data: {
      ...v, observaciones: v.observaciones || null,
      items: v.items.map((i) => ({ ...i, novedad: i.novedad || null })),
    } })
      .then(onClose)
      .catch((e) => erroresAlFormulario(e, setError, ['devuelto_en', 'observaciones'], 'No se pudo registrar la devolución'))

  return (
    <FormModal opened onClose={onClose} title={`Recibir ${prestamo.folio}`} onSubmit={handleSubmit(enviar)}
      submitLabel="Registrar la devolución" submitting={recibir.isPending} size="lg">
      <Stack gap="md">
        <Text size="sm">De <b>{prestamo.servidor?.nombre}</b>{prestamo.vencido ? ', con la devolución vencida' : ''}.</Text>
        <Controller name="devuelto_en" control={control} render={({ field }) => (
          <MomentoReal label="Se devolvió" value={field.value} onChange={field.onChange} error={errors.devuelto_en?.message} />
        )} />
        {items.map((i, n) => (
          <Grid key={i.id} align="flex-end">
            <Grid.Col span={12}>
              <Text size="sm" fw={600}>{nombreItem(i)}</Text>
              {i.accesorios && <Text size="xs" c="dimmed">Salió con: {i.accesorios}</Text>}
            </Grid.Col>
            <Grid.Col span={{ base: 12, sm: 5 }}>
              <Controller name={`items.${n}.condicion_devolucion`} control={control} render={({ field }) => (
                <CondicionEquipo value={field.value} onChange={field.onChange} error={errors.items?.[n]?.condicion_devolucion?.message} />
              )} />
            </Grid.Col>
            <Grid.Col span={{ base: 12, sm: 7 }}>
              <TextInput label="Novedad" placeholder="Ej.: Falta el control remoto"
                error={errors.items?.[n]?.novedad?.message} {...contained} {...register(`items.${n}.novedad`)} />
            </Grid.Col>
          </Grid>
        ))}
        <Textarea label="Observaciones" autosize minRows={2} error={errors.observaciones?.message} {...contained} {...register('observaciones')} />
      </Stack>
    </FormModal>
  )
}
