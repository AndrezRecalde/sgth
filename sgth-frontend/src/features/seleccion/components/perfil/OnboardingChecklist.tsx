'use client'

import { useEffect } from 'react'
import { Button, Checkbox, Group, Stack, Textarea } from '@mantine/core'
import { IconDeviceFloppy } from '@tabler/icons-react'
import { Controller, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod/v4'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { useActualizarOnboarding } from '../../hooks/usePostulanteMutations'
import type { Onboarding } from '../../services/convocatoriaService'

const schema = z.object({
  documentacion_entregada: z.boolean(),
  induccion_completada:    z.boolean(),
  contrato_firmado:        z.boolean(),
  observaciones:           z.string().max(1000, 'Máximo 1000 caracteres').nullable(),
})
type Datos = z.infer<typeof schema>

const PASOS: { campo: 'documentacion_entregada' | 'contrato_firmado' | 'induccion_completada'; label: string }[] = [
  { campo: 'documentacion_entregada', label: 'Entregó la documentación de ingreso' },
  { campo: 'contrato_firmado',        label: 'Firmó el contrato o la acción de personal' },
  { campo: 'induccion_completada',    label: 'Recibió la inducción' },
]

const valores = (o: Onboarding): Datos => ({
  documentacion_entregada: o.documentacion_entregada, induccion_completada: o.induccion_completada,
  contrato_firmado: o.contrato_firmado, observaciones: o.observaciones ?? '',
})

interface Props {
  convocatoriaId: number
  onboarding:     Onboarding
  /** Marcarlo es de quien gestiona la incorporación. */
  editable:       boolean
}

/**
 * La inducción de quien se incorporó (decisión de TH, 2026-10-05). Se creaba
 * al incorporar y ninguna pantalla la mostraba.
 */
export function OnboardingChecklist({ convocatoriaId, onboarding, editable }: Props) {
  const contained = useContainedInput()
  const guardar = useActualizarOnboarding(convocatoriaId)
  const { control, register, handleSubmit, reset, setError, formState: { errors, isDirty } } = useForm<Datos>({
    resolver: zodResolver(schema),
    defaultValues: valores(onboarding),
  })

  useEffect(() => { reset(valores(onboarding)) }, [onboarding, reset])

  // Lo guardado pasa a ser el punto de partida: el botón vuelve a apagarse.
  const enviar = (d: Datos) => guardar.mutateAsync({ id: onboarding.id, ...d })
    .then(() => reset(d))
    .catch((e) => erroresAlFormulario(e, setError, Object.keys(schema.shape), 'No se pudo guardar la inducción'))

  return (
    <form onSubmit={handleSubmit(enviar)} noValidate>
      <Stack gap="xs">
        {PASOS.map(({ campo, label }) => (
          <Controller key={campo} name={campo} control={control} render={({ field }) => (
            <Checkbox label={label} checked={field.value} disabled={!editable}
              onChange={(e) => field.onChange(e.currentTarget.checked)} />
          )} />
        ))}
        <Textarea label="Observaciones" autosize minRows={2} disabled={!editable} {...contained}
          {...register('observaciones')} error={errors.observaciones?.message} />
        {editable && (
          <Group justify="flex-end">
            <Button type="submit" size="xs" leftSection={<IconDeviceFloppy size={14} />}
              loading={guardar.isPending} disabled={!isDirty}>
              Guardar inducción
            </Button>
          </Group>
        )}
      </Stack>
    </form>
  )
}
