'use client'

import { useEffect } from 'react'
import {
  Stack,
  TextInput, Textarea, Anchor,
} from '@mantine/core'
import { FormModal } from '@/components/ui'
import { useForm, useWatch, type Resolver } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresDeCampo } from '@/lib/erroresDeCampo'
import { useCargoMutations } from '../hooks/useCargoMutations'
import { cargoSchema, type CargoFormData } from '../schemas/cargo.schema'
import type { Cargo } from '@/types/api'

interface Props {
  opened:  boolean
  onClose: () => void
  cargo?:  Cargo | null
}

export function CargoModal({ opened, onClose, cargo }: Props) {
  const contained         = useContainedInput()
  const { crear, editar } = useCargoMutations()
  const editando          = !!cargo

  const {
    register,
    control,
    handleSubmit,
    reset,
    setError,
    formState: { errors },
  } = useForm<CargoFormData>({
    resolver: zodResolver(cargoSchema) as Resolver<CargoFormData>,
    defaultValues: {
      nombre:                 cargo?.nombre                ?? '',
      denominacion_generica:  cargo?.denominacion_generica ?? '',
      codigo_ciuo:            cargo?.codigo_ciuo            ?? '',
      mision:                 cargo?.mision                ?? '',
    },
  })

  useEffect(() => {
    reset({
      nombre:                cargo?.nombre                ?? '',
      denominacion_generica: cargo?.denominacion_generica ?? '',
      codigo_ciuo:           cargo?.codigo_ciuo            ?? '',
      mision:                cargo?.mision                ?? '',
    })
  }, [cargo, reset])

  const cerrar = () => {
    reset()
    onClose()
  }

  // Renombrar un cargo con puestos ocupados cambia la denominación de sus
  // ocupantes: solo se corrige cómo está escrito, y se dice por qué (7.1b).
  const nombre = useWatch({ control, name: 'nombre' })
  const pideMotivo = editando && !!cargo?.con_puestos_ocupados && (nombre ?? '').trim() !== cargo?.nombre

  const guardar = async (valores: CargoFormData) => {
    if (pideMotivo && (valores.motivo_correccion?.trim().length ?? 0) < 5) {
      setError('motivo_correccion', { type: 'manual', message: 'Diga por qué se corrige el nombre' })
      return
    }
    if (!pideMotivo) delete valores.motivo_correccion

    try {
      if (cargo) await editar.mutateAsync({ id: cargo.id, data: valores })
      else await crear.mutateAsync(valores)
      cerrar()
    } catch (error) {
      // El 422 va a su campo (regla 07): el nombre repetido o el motivo que
      // falta. Lo demás ya lo notificó la mutación.
      for (const [campo, mensaje] of Object.entries(erroresDeCampo(error) ?? {})) {
        if (campo in cargoSchema.shape) setError(campo as keyof CargoFormData, { type: 'server', message: mensaje })
      }
    }
  }

  return (
    <FormModal
      opened={opened}
      onClose={cerrar}
      title={editando ? 'Editar cargo' : 'Nuevo cargo'}
      size="md"
      onSubmit={handleSubmit(guardar)}
      submitLabel={editando ? 'Guardar cambios' : 'Crear cargo'}
      submitting={crear.isPending || editar.isPending}
    >
      <Stack gap="sm">
        <TextInput
          label="Nombre del cargo"
          placeholder="Ej: Analista de Seguridad Informática"
          {...contained}
          {...register('nombre')}
          error={errors.nombre?.message}
        />
        {pideMotivo && (
          <TextInput
            label="Motivo de la corrección"
            description="El cargo tiene puestos ocupados. Si no corrige cómo está escrito sino que cambia la denominación, es una reclasificación: use «Revisar la clasificación» en el puesto."
            withAsterisk
            {...contained}
            {...register('motivo_correccion')}
            error={errors.motivo_correccion?.message}
          />
        )}
        <TextInput
          label="Denominación genérica"
          placeholder="Ej: Analista"
          {...contained}
          {...register('denominacion_generica')}
          error={errors.denominacion_generica?.message}
        />
        <TextInput
          label="Código CIUO"
          placeholder="Ej: 2519"
          description={
            <>
              Clasificación Internacional Uniforme de Ocupaciones. Lo heredan
              las fichas médicas ocupacionales de este cargo.{' '}
              <Anchor
                href="https://aplicaciones2.ecuadorencifras.gob.ec/SIN/ciuo08.php"
                target="_blank"
                rel="noopener noreferrer"
                size="xs"
              >
                Buscar en el INEC
              </Anchor>
            </>
          }
          {...contained}
          {...register('codigo_ciuo')}
          error={errors.codigo_ciuo?.message}
        />
        <Textarea
          label="Misión del cargo"
          placeholder="Describa la misión del cargo (opcional)"
          autosize
          minRows={3}
          {...contained}
          {...register('mision')}
          error={errors.mision?.message}
        />
      </Stack>
    </FormModal>
  )
}
