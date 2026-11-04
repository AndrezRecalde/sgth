'use client'

import { useEffect } from 'react'
import { Select, Stack, Textarea, TextInput } from '@mantine/core'
import { Controller, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresDeCampo } from '@/lib/erroresDeCampo'
import type { PuestoActividad } from '@/types/api'
import { FRECUENCIAS } from '../constants/perfilPuesto'
import { useCrearActividad, useEditarActividad } from '../hooks/usePuestoActividad'
import { puestoActividadSchema, type PuestoActividadFormData } from '../schemas/perfilPuesto.schema'

interface Props {
  opened:     boolean
  onClose:    () => void
  puestoId:   number
  /** Sin actividad, se registra una nueva. */
  actividad?: PuestoActividad | null
}

function valoresDe(actividad?: PuestoActividad | null): PuestoActividadFormData {
  return {
    descripcion:   actividad?.descripcion ?? '',
    producto:      actividad?.producto ?? '',
    conocimientos: actividad?.conocimientos ?? '',
    frecuencia:    actividad?.frecuencia ?? null,
  }
}

/**
 * Una actividad esencial del puesto. La descripción la lee la matriz de
 * riesgos del FEMO; el producto, los conocimientos y la frecuencia son las
 * columnas del formato de asignación de responsabilidades (FOR-02).
 */
export function PuestoActividadModal({ opened, onClose, puestoId, actividad }: Props) {
  const contained = useContainedInput()
  const crear     = useCrearActividad(puestoId)
  const editar    = useEditarActividad(puestoId)
  const editando  = !!actividad

  const { register, control, handleSubmit, reset, setError, formState: { errors } } =
    useForm<PuestoActividadFormData>({
      resolver: zodResolver(puestoActividadSchema),
      defaultValues: valoresDe(actividad),
    })

  useEffect(() => {
    if (opened) reset(valoresDe(actividad))
  }, [opened, actividad, reset])

  const guardar = async (v: PuestoActividadFormData) => {
    const data = {
      descripcion:   v.descripcion,
      producto:      v.producto || null,
      conocimientos: v.conocimientos || null,
      frecuencia:    v.frecuencia,
    }
    try {
      if (actividad) await editar.mutateAsync({ id: actividad.id, data })
      else await crear.mutateAsync(data)
      onClose()
    } catch (error) {
      for (const [campo, mensaje] of Object.entries(erroresDeCampo(error) ?? {})) {
        if (campo in puestoActividadSchema.shape) {
          setError(campo as keyof PuestoActividadFormData, { type: 'server', message: mensaje })
        }
      }
    }
  }

  return (
    <FormModal
      opened={opened}
      onClose={onClose}
      title={editando ? 'Editar actividad' : 'Nueva actividad'}
      size="md"
      onSubmit={handleSubmit(guardar)}
      submitLabel={editando ? 'Guardar cambios' : 'Agregar actividad'}
      submitting={crear.isPending || editar.isPending}
    >
      <Stack gap="sm">
        <TextInput
          label="Actividad esencial"
          placeholder="Ej: Mantenimiento preventivo de servidores"
          description="La matriz de riesgos del FEMO la muestra con este texto."
          withAsterisk
          {...contained}
          {...register('descripcion')}
          error={errors.descripcion?.message}
        />
        <TextInput
          label="Producto o servicio que entrega"
          placeholder="Ej: Informe de mantenimiento"
          {...contained}
          {...register('producto')}
          error={errors.producto?.message}
        />
        <Textarea
          label="Conocimientos que pide"
          placeholder="Ej: Normas de Control Interno 410-09, mantenimiento de la infraestructura tecnológica"
          autosize
          minRows={2}
          {...contained}
          {...register('conocimientos')}
          error={errors.conocimientos?.message}
        />
        <Controller
          name="frecuencia"
          control={control}
          render={({ field }) => (
            <Select
              label="Frecuencia"
              placeholder="Cada cuánto se entrega"
              data={FRECUENCIAS}
              clearable
              {...contained}
              value={field.value ?? null}
              onChange={(v) => field.onChange(FRECUENCIAS.find(f => f.value === v)?.value ?? null)}
              error={errors.frecuencia?.message}
            />
          )}
        />
      </Stack>
    </FormModal>
  )
}
