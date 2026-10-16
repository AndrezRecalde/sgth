'use client'

import { Grid, NumberInput, SegmentedControl, Select, Stack, Text, Textarea } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { Controller, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { fromDateValueOrNull, hoyIso, toDateValue } from '@/lib/fecha'
import { MOTIVOS_AJUSTE } from '../constants/consumibles'
import { useMovimientoMutations } from '../hooks/useConsumibles'
import { ajusteSchema, type AjusteFormData } from '../schemas/movimiento.schema'
import type { DetalleConsumible } from '../services/consumiblesService'

const CAMPOS = Object.keys(ajusteSchema.shape)

interface Props {
  consumible: DetalleConsumible | null
  onClose: () => void
}

/**
 * Ajustar el saldo: suma o resta, con su motivo (daño, caducidad, diferencia
 * en el conteo u otro) y el detalle. Restar no deja el saldo en negativo.
 */
export function AjusteModal({ consumible, onClose }: Props) {
  return consumible ? <Formulario consumible={consumible} onClose={onClose} /> : null
}

function Formulario({ consumible, onClose }: { consumible: DetalleConsumible; onClose: () => void }) {
  const contained = useContainedInput()
  const { ajuste } = useMovimientoMutations(consumible.id)
  const { register, control, handleSubmit, setError, formState: { errors } } = useForm<AjusteFormData>({
    resolver: zodResolver(ajusteSchema),
    defaultValues: { fecha: hoyIso(), sentido: 'restar', observacion: '' },
  })

  const enviar = ({ sentido, cantidad, ...v }: AjusteFormData) =>
    ajuste.mutateAsync({ ...v, cantidad: sentido === 'restar' ? -cantidad : cantidad })
      .then(onClose).catch((e) => erroresAlFormulario(e, setError, CAMPOS, null))

  return (
    <FormModal opened onClose={onClose} title="Registrar ajuste" onSubmit={handleSubmit(enviar)}
      submitLabel="Registrar ajuste" submitting={ajuste.isPending}>
      <Grid>
        <Grid.Col span={12}>
          <Stack gap={4}>
            <Text size="sm">{consumible.descripcion} · saldo actual: <b>{consumible.saldo ?? 0}</b></Text>
            <Controller name="sentido" control={control} render={({ field }) => (
              <SegmentedControl fullWidth value={field.value} onChange={field.onChange}
                data={[{ value: 'restar', label: 'Restar del saldo' }, { value: 'sumar', label: 'Sumar al saldo' }]} />
            )} />
          </Stack>
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <Controller name="cantidad" control={control} render={({ field }) => (
            <NumberInput label="Cantidad" placeholder="Ej.: 1" withAsterisk min={1} allowDecimal={false} hideControls
              error={errors.cantidad?.message} {...contained}
              value={field.value ?? ''} onChange={(v) => field.onChange(typeof v === 'number' ? v : Number.NaN)} />
          )} />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <Controller name="fecha" control={control} render={({ field }) => (
            <DatePickerInput label="Fecha" valueFormat="DD/MM/YYYY" withAsterisk maxDate={hoyIso()}
              error={errors.fecha?.message} {...contained}
              value={toDateValue(field.value)} onChange={(d) => field.onChange(fromDateValueOrNull(d) ?? '')} />
          )} />
        </Grid.Col>
        <Grid.Col span={12}>
          <Controller name="motivo_ajuste" control={control} render={({ field }) => (
            <Select label="Motivo" placeholder="Seleccionar" withAsterisk data={MOTIVOS_AJUSTE} {...contained}
              error={errors.motivo_ajuste?.message} value={field.value ?? null} onChange={(v) => field.onChange(v ?? undefined)} />
          )} />
        </Grid.Col>
        <Grid.Col span={12}>
          <Textarea label="Detalle" placeholder="Ej.: Llegó con la carcasa rota; se devolvió al proveedor" withAsterisk autosize minRows={2}
            error={errors.observacion?.message} {...contained} {...register('observacion')} />
        </Grid.Col>
      </Grid>
    </FormModal>
  )
}
