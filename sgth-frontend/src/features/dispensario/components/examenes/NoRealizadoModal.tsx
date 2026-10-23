'use client'

import { Select, Stack, Textarea } from '@mantine/core'
import { Controller, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { useMarcarNoRealizado } from '../../hooks/useExamenes'
import { noRealizadoSchema, type NoRealizadoFormData } from '../../schemas/resultadoMedico.schema'
import type { OrdenExamen } from '../../services/examenService'

interface Props {
  /** La orden cuyo examen se marca; sin orden, el modal no se muestra. */
  orden:   OrdenExamen | null
  onClose: () => void
}

/** Un examen pendiente que no va a volver: deja de esperarse, con su motivo. */
export function NoRealizadoModal({ orden, onClose }: Props) {
  return orden ? <Formulario orden={orden} onClose={onClose} /> : null
}

function Formulario({ orden, onClose }: { orden: OrdenExamen; onClose: () => void }) {
  const contained = useContainedInput()
  const marcar = useMarcarNoRealizado()
  const pendientes = orden.items.filter((i) => i.estado === 'pendiente')
  const { control, register, handleSubmit, setError, formState: { errors } } = useForm<NoRealizadoFormData>({
    resolver: zodResolver(noRealizadoSchema),
    defaultValues: {
      item: pendientes.length === 1 ? String(pendientes[0].id) : '',
      motivo_no_realizado: '',
    },
  })

  const enviar = (v: NoRealizadoFormData) =>
    marcar.mutateAsync({ ordenId: orden.id, itemId: Number(v.item), motivo: v.motivo_no_realizado })
      .then(onClose)
      .catch((e) => erroresAlFormulario(e, setError, Object.keys(noRealizadoSchema.shape), 'No se pudo marcar el examen'))

  return (
    <FormModal opened onClose={onClose} title={`Examen no realizado · ${orden.folio}`}
      onSubmit={handleSubmit(enviar)} submitLabel="Marcar no realizado" submitting={marcar.isPending}>
      <Stack gap="sm">
        <Controller name="item" control={control} render={({ field }) => (
          <Select label="Examen" placeholder="Elija el examen" withAsterisk {...contained}
            data={pendientes.map((i) => ({ value: String(i.id), label: `${i.examen?.nombre ?? 'Examen'}${i.nota ? ` (${i.nota})` : ''}` }))}
            value={field.value || null} onChange={(v) => field.onChange(v ?? '')} error={errors.item?.message} />
        )} />
        <Textarea label="Motivo" withAsterisk autosize minRows={2} maxLength={255}
          placeholder="El paciente no se lo hizo, el laboratorio no lo realiza, muestra rechazada…"
          description="Deja de esperarse su resultado. Si al final llega, se puede subir igual."
          {...contained} {...register('motivo_no_realizado')} error={errors.motivo_no_realizado?.message} />
      </Stack>
    </FormModal>
  )
}
