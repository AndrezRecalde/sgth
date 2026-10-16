'use client'

import { Grid, NumberInput, Textarea } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { Controller, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { fromDateValueOrNull, hoyIso, toDateValue } from '@/lib/fecha'
import { useMovimientoMutations } from '../hooks/useConsumibles'
import { aperturaSchema, type AperturaFormData } from '../schemas/movimiento.schema'
import type { DetalleConsumible } from '../services/consumiblesService'

const CAMPOS = Object.keys(aperturaSchema.shape)

interface Props {
  consumible: DetalleConsumible | null
  onClose: () => void
}

/**
 * El saldo contado en la bodega de TIC el día de arranque [P7]: una sola
 * apertura viva por consumible, y va antes que cualquier otro movimiento.
 */
export function AperturaModal({ consumible, onClose }: Props) {
  return consumible ? <Formulario consumible={consumible} onClose={onClose} /> : null
}

function Formulario({ consumible, onClose }: { consumible: DetalleConsumible; onClose: () => void }) {
  const contained = useContainedInput()
  const { apertura } = useMovimientoMutations(consumible.id)
  const { register, control, handleSubmit, setError, formState: { errors } } = useForm<AperturaFormData>({
    resolver: zodResolver(aperturaSchema),
    defaultValues: { fecha: hoyIso(), observacion: '' },
  })

  const enviar = (v: AperturaFormData) =>
    apertura.mutateAsync({ ...v, observacion: v.observacion.trim() || null })
      .then(onClose).catch((e) => erroresAlFormulario(e, setError, CAMPOS, null))

  return (
    <FormModal opened onClose={onClose} title="Registrar apertura" onSubmit={handleSubmit(enviar)}
      submitLabel="Registrar apertura" submitting={apertura.isPending}>
      <Grid>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <Controller name="fecha" control={control} render={({ field }) => (
            <DatePickerInput label="Fecha del conteo" valueFormat="DD/MM/YYYY" withAsterisk maxDate={hoyIso()}
              error={errors.fecha?.message} {...contained}
              value={toDateValue(field.value)} onChange={(d) => field.onChange(fromDateValueOrNull(d) ?? '')} />
          )} />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <Controller name="cantidad" control={control} render={({ field }) => (
            <NumberInput label="Cantidad contada" placeholder="Ej.: 4" withAsterisk min={1} allowDecimal={false} hideControls
              error={errors.cantidad?.message} {...contained}
              value={field.value ?? ''} onChange={(v) => field.onChange(typeof v === 'number' ? v : Number.NaN)} />
          )} />
        </Grid.Col>
        <Grid.Col span={12}>
          <Textarea label="Observación" placeholder={`Opcional. Ej.: Conteo de la bodega de TIC, ${consumible.descripcion}`} autosize minRows={2}
            error={errors.observacion?.message} {...contained} {...register('observacion')} />
        </Grid.Col>
      </Grid>
    </FormModal>
  )
}
