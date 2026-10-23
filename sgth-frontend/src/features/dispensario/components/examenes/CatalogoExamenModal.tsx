'use client'

import { Grid, Select, Switch, TextInput, Textarea } from '@mantine/core'
import { Controller, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { useGuardarExamenCatalogo } from '../../hooks/useExamenesAdmin'
import { examenCatalogoSchema, type ExamenCatalogoFormData } from '../../schemas/examenAdmin.schema'
import type { AreaExamen, CatalogoExamen } from '../../services/examenService'

interface Props {
  opened:  boolean
  onClose: () => void
  examen:  CatalogoExamen | null
  areas:   AreaExamen[]
}

/** Un examen del catálogo: en qué área de la hoja va y qué dato pide. */
export function CatalogoExamenModal({ opened, ...props }: Props) {
  return opened ? <Formulario {...props} /> : null
}

function Formulario({ onClose, examen, areas }: Omit<Props, 'opened'>) {
  const contained = useContainedInput()
  const guardar = useGuardarExamenCatalogo()
  const { register, control, handleSubmit, setError, formState: { errors } } = useForm<ExamenCatalogoFormData>({
    resolver: zodResolver(examenCatalogoSchema),
    defaultValues: {
      area:          examen?.area ?? '',
      nombre:        examen?.nombre ?? '',
      etiqueta_nota: examen?.etiqueta_nota ?? '',
      indicaciones:  examen?.indicaciones ?? '',
      sensible:      examen?.sensible ?? false,
      activo:        examen?.activo ?? true,
    },
  })

  const enviar = (v: ExamenCatalogoFormData) =>
    guardar.mutateAsync({
      id: examen?.id,
      data: {
        ...v,
        etiqueta_nota: v.etiqueta_nota.trim() || null,
        indicaciones:  v.indicaciones.trim() || null,
      },
    })
      .then(onClose)
      .catch((e) => erroresAlFormulario(e, setError, Object.keys(examenCatalogoSchema.shape), 'No se pudo guardar el examen'))

  return (
    <FormModal
      opened
      onClose={onClose}
      title={examen ? 'Editar examen' : 'Nuevo examen'}
      onSubmit={handleSubmit(enviar)}
      submitLabel={examen ? 'Guardar cambios' : 'Agregar al catálogo'}
      submitting={guardar.isPending}
    >
      <Grid>
        <Grid.Col span={{ base: 12, sm: 5 }}>
          <Controller name="area" control={control} render={({ field }) => (
            <Select label="Área" placeholder="Elija el área" withAsterisk {...contained}
              description={examen ? 'Si cambia de área, pasa al final de la nueva.' : 'Se agrega al final del área.'}
              data={areas.map((a) => ({ value: a.value, label: a.etiqueta }))}
              value={field.value || null} onChange={(v) => field.onChange(v ?? '')} error={errors.area?.message} />
          )} />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 7 }}>
          <TextInput label="Nombre" placeholder="Como en la hoja del laboratorio" withAsterisk maxLength={150}
            {...contained} {...register('nombre')} error={errors.nombre?.message} />
        </Grid.Col>
        <Grid.Col span={12}>
          <TextInput label="Dato que pide al médico" placeholder="Opcional. Ej.: Gramos de glucosa, Región" maxLength={40}
            description="Si lo lleva, al marcar el examen se pide este dato y sale impreso junto a él."
            {...contained} {...register('etiqueta_nota')} error={errors.etiqueta_nota?.message} />
        </Grid.Col>
        <Grid.Col span={12}>
          <Textarea label="Indicaciones" placeholder="Opcional. Preparación del paciente" autosize minRows={2}
            maxLength={1000} {...contained} {...register('indicaciones')} error={errors.indicaciones?.message} />
        </Grid.Col>
        <Grid.Col span={12}>
          <Controller name="sensible" control={control} render={({ field }) => (
            <Switch label="Sensible" checked={field.value} onChange={(e) => field.onChange(e.currentTarget.checked)}
              description="Pide constancia del consentimiento firmado y no entra en perfiles ocupacionales." />
          )} />
        </Grid.Col>
        {examen && (
          <Grid.Col span={12}>
            <Controller name="activo" control={control} render={({ field }) => (
              <Switch label="Activo" checked={field.value} onChange={(e) => field.onChange(e.currentTarget.checked)}
                description="Inactivo, deja de ofrecerse al pedir exámenes; las órdenes que ya lo tienen no cambian." />
            )} />
          </Grid.Col>
        )}
      </Grid>
    </FormModal>
  )
}
