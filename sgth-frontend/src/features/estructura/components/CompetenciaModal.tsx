'use client'

import { useEffect } from 'react'
import { Grid, Select, Textarea, TextInput } from '@mantine/core'
import { Controller, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresDeCampo } from '@/lib/erroresDeCampo'
import type { Competencia } from '@/types/api'
import { TIPOS_COMPETENCIA } from '../constants/perfilPuesto'
import { useCompetenciaMutations } from '../hooks/useCompetencias'
import { competenciaSchema, type CompetenciaFormData } from '../schemas/perfilPuesto.schema'

interface Props {
  opened:       boolean
  onClose:      () => void
  /** Sin competencia, se agrega una al diccionario. */
  competencia?: Competencia | null
}

function valoresDe(c?: Competencia | null): CompetenciaFormData {
  return {
    tipo:                 c?.tipo ?? 'tecnica',
    nombre:               c?.nombre ?? '',
    definicion:           c?.definicion ?? '',
    comportamiento_alto:  c?.comportamiento_alto ?? '',
    comportamiento_medio: c?.comportamiento_medio ?? '',
    comportamiento_bajo:  c?.comportamiento_bajo ?? '',
  }
}

/**
 * Una competencia del diccionario: su definición y el comportamiento
 * observable de cada nivel. El número no se elige: es el siguiente de su
 * tipo. El tipo tampoco cambia después, porque movería sus números del FOR-02.
 */
export function CompetenciaModal({ opened, onClose, competencia }: Props) {
  const contained = useContainedInput()
  const { crear, editar } = useCompetenciaMutations()
  const editando = !!competencia

  const { register, control, handleSubmit, reset, setError, formState: { errors } } =
    useForm<CompetenciaFormData>({
      resolver: zodResolver(competenciaSchema),
      defaultValues: valoresDe(competencia),
    })

  useEffect(() => {
    if (opened) reset(valoresDe(competencia))
  }, [opened, competencia, reset])

  const guardar = async (v: CompetenciaFormData) => {
    const textos = {
      nombre:               v.nombre,
      definicion:           v.definicion || null,
      comportamiento_alto:  v.comportamiento_alto,
      comportamiento_medio: v.comportamiento_medio,
      comportamiento_bajo:  v.comportamiento_bajo,
    }
    try {
      // El tipo solo se envía al crearla.
      if (competencia) await editar.mutateAsync({ id: competencia.id, data: textos })
      else await crear.mutateAsync({ ...textos, tipo: v.tipo })
      onClose()
    } catch (error) {
      for (const [campo, mensaje] of Object.entries(erroresDeCampo(error) ?? {})) {
        if (campo in competenciaSchema.shape) {
          setError(campo as keyof CompetenciaFormData, { type: 'server', message: mensaje })
        }
      }
    }
  }

  const nivel = (campo: 'comportamiento_alto' | 'comportamiento_medio' | 'comportamiento_bajo', etiqueta: string) => (
    <Grid.Col span={12}>
      <Textarea
        label={`Comportamiento observable, nivel ${etiqueta}`}
        autosize
        minRows={2}
        withAsterisk
        {...contained}
        {...register(campo)}
        error={errors[campo]?.message}
      />
    </Grid.Col>
  )

  return (
    <FormModal
      opened={opened}
      onClose={onClose}
      title={editando ? `Editar competencia n.º ${competencia.numero}` : 'Nueva competencia'}
      size="lg"
      onSubmit={handleSubmit(guardar)}
      submitLabel={editando ? 'Guardar cambios' : 'Agregar al diccionario'}
      submitting={crear.isPending || editar.isPending}
    >
      <Grid gap="sm">
        <Grid.Col span={{ base: 12, sm: 4 }}>
          <Controller
            name="tipo"
            control={control}
            render={({ field }) => (
              <Select
                label="Tipo"
                data={TIPOS_COMPETENCIA.map(({ value, label }) => ({ value, label }))}
                allowDeselect={false}
                disabled={editando}
                withAsterisk
                {...contained}
                value={field.value}
                onChange={(v) => field.onChange(TIPOS_COMPETENCIA.find(t => t.value === v)?.value ?? field.value)}
                error={errors.tipo?.message}
              />
            )}
          />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 8 }}>
          <TextInput
            label="Denominación"
            placeholder="Ej: Liderazgo"
            withAsterisk
            {...contained}
            {...register('nombre')}
            error={errors.nombre?.message}
          />
        </Grid.Col>
        <Grid.Col span={12}>
          <Textarea
            label="Definición"
            autosize
            minRows={2}
            {...contained}
            {...register('definicion')}
            error={errors.definicion?.message}
          />
        </Grid.Col>
        {nivel('comportamiento_alto', 'alto')}
        {nivel('comportamiento_medio', 'medio')}
        {nivel('comportamiento_bajo', 'bajo')}
      </Grid>
    </FormModal>
  )
}
