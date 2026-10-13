'use client'

import { Grid, TextInput, Textarea } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { Controller, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { fromDateValueOrNull, hoyIso, toDateValue } from '@/lib/fecha'
import { useCatalogosFormulario } from '../hooks/useCatalogosInventario'
import { useCustodiaMutations } from '../hooks/useCustodia'
import { devolucionSchema, type DevolucionFormData } from '../schemas/custodia.schema'
import type { Asignacion } from '../services/tipos'

interface Props {
  asignacion: Asignacion | null
  onClose: () => void
}

/** La devolución a TIC: el equipo queda sin custodio. */
export function DevolucionModal({ asignacion, onClose }: Props) {
  return asignacion ? <Formulario asignacion={asignacion} onClose={onClose} /> : null
}

function Formulario({ asignacion, onClose }: { asignacion: Asignacion; onClose: () => void }) {
  const contained = useContainedInput()
  const { devolver } = useCustodiaMutations()
  const { data: catalogos } = useCatalogosFormulario()

  const { register, control, handleSubmit, setError, formState: { errors } } = useForm<DevolucionFormData>({
    resolver: zodResolver(devolucionSchema),
    defaultValues: { fecha_devolucion: hoyIso(), soporte_anterior: '', observaciones: '' },
  })

  const enviar = (v: DevolucionFormData) =>
    devolver.mutateAsync({
      id: asignacion.id,
      data: {
        fecha_devolucion: v.fecha_devolucion,
        observaciones: v.observaciones.trim() || null,
        soporte_anterior: v.soporte_anterior.trim() || null,
      },
    })
      .then(onClose)
      .catch((e) => erroresAlFormulario(e, setError, Object.keys(devolucionSchema.shape), null))

  return (
    <FormModal
      opened
      onClose={onClose}
      title={`Devolución de ${asignacion.custodio?.nombre ?? 'la custodia'}`}
      onSubmit={handleSubmit(enviar)}
      submitLabel="Registrar devolución"
      submitting={devolver.isPending}
    >
      <Grid>
        <Grid.Col span={{ base: 12, sm: catalogos?.acepta_soporte_anterior ? 6 : 12 }}>
          <Controller name="fecha_devolucion" control={control} render={({ field }) => (
            <DatePickerInput label="Fecha de devolución" valueFormat="DD/MM/YYYY" withAsterisk
              minDate={asignacion.fecha_asignacion ?? undefined} maxDate={hoyIso()}
              error={errors.fecha_devolucion?.message} {...contained}
              value={toDateValue(field.value)} onChange={(d) => field.onChange(fromDateValueOrNull(d) ?? '')} />
          )} />
        </Grid.Col>
        {catalogos?.acepta_soporte_anterior && (
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <TextInput label="Soporte (sistema anterior)" placeholder="Ej.: 48618"
              error={errors.soporte_anterior?.message} {...contained} {...register('soporte_anterior')} />
          </Grid.Col>
        )}
        <Grid.Col span={12}>
          <Textarea label="Observaciones" placeholder="Opcional. Ej.: Se devuelve sin cargador."
            autosize minRows={2} error={errors.observaciones?.message} {...contained} {...register('observaciones')} />
        </Grid.Col>
      </Grid>
    </FormModal>
  )
}
