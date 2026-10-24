'use client'

import { Alert, Select, Stack, Text, Textarea } from '@mantine/core'
import { IconInfoCircle } from '@tabler/icons-react'
import { Controller, useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { useMarcarNoEntregado } from '../hooks/useReceta'
import {
  MOTIVOS_NO_ENTREGADO, noEntregadoSchema, type MotivoNoEntregado, type NoEntregadoFormData,
} from '../schemas/noEntregado.schema'
import { nombreDeItem, type ItemReceta } from '../services/recetaService'

interface Props {
  recetaId: number
  /** El medicamento que no se entrega; sin él el modal está cerrado. */
  item:     ItemReceta | null
  /** El motivo con que abre: agotado o vencido, según lo que diga el estante. */
  motivoSugerido?: MotivoNoEntregado
  onClose:  () => void
}

/**
 * Marcar que el mostrador no va a entregar un medicamento del inventario
 * (decisión del Dispensario del 2026-10-24, opción A): el paciente lo adquiere
 * fuera y el impreso lo dice. Queda constancia de quién, cuándo y por qué.
 */
export function NoEntregadoRecetaModal({ item, ...props }: Props) {
  return item ? <Formulario item={item} {...props} /> : null
}

function Formulario({ recetaId, item, motivoSugerido = 'agotado', onClose }: Omit<Props, 'item'> & { item: ItemReceta }) {
  const contained = useContainedInput()
  const marcar = useMarcarNoEntregado()
  const entregado = item.cantidad_despachada ?? 0
  const falta = item.cantidad_prescrita - entregado

  const { control, register, handleSubmit, setError, formState: { errors } } = useForm<NoEntregadoFormData>({
    resolver: zodResolver(noEntregadoSchema),
    defaultValues: { motivo: motivoSugerido, detalle: '' },
  })
  const motivo = useWatch({ control, name: 'motivo' })

  const guardar = (v: NoEntregadoFormData) =>
    marcar.mutateAsync({ recetaId, itemId: item.id!, motivo: v.motivo, detalle: v.detalle.trim() || null })
      .then(onClose)
      .catch((e) => erroresAlFormulario(e, setError, Object.keys(noEntregadoSchema.shape), 'No se pudo marcar el medicamento'))

  return (
    <FormModal opened onClose={onClose} title={`No se entrega: ${nombreDeItem(item)}`}
      onSubmit={handleSubmit(guardar)} submitLabel="Marcar «adquirir fuera»" submitting={marcar.isPending}>
      <Stack gap="sm">
        <Alert variant="light" icon={<IconInfoCircle size={16} />}>
          <Text size="sm">
            {entregado > 0
              ? `Ya se entregaron ${entregado} de ${item.cantidad_prescrita}. Las ${falta} restantes las adquiere fuera el paciente.`
              : `El paciente adquiere fuera las ${falta} unidades.`}
            {' '}El impreso de la receta lo dirá. No se avisa al médico.
          </Text>
        </Alert>

        <Controller name="motivo" control={control} render={({ field }) => (
          <Select label="Motivo" withAsterisk allowDeselect={false} {...contained}
            data={MOTIVOS_NO_ENTREGADO.map(({ value, label }) => ({ value, label }))}
            value={field.value} onChange={(v) => v && field.onChange(v)} error={errors.motivo?.message} />
        )} />

        <Textarea label="Detalle" withAsterisk={motivo === 'otro'} autosize minRows={2} maxLength={255}
          placeholder={motivo === 'otro' ? 'Ej.: retirado por el fabricante' : 'Opcional'}
          {...contained} {...register('detalle')} error={errors.detalle?.message} />
      </Stack>
    </FormModal>
  )
}
