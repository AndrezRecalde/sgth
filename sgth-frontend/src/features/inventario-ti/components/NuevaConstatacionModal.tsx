'use client'

import { Grid, Select, Switch, TextInput, Textarea } from '@mantine/core'
import { Controller, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { useTodasUnidades } from '@/features/estructura/hooks/useUnidades'
import { useUbicaciones } from '../hooks/useCatalogosInventario'
import { useConstatacionMutations } from '../hooks/useConstataciones'
import { constatacionSchema, type ConstatacionFormData } from '../schemas/constatacion.schema'
import type { Constatacion } from '../services/constatacionesService'

const CAMPOS = ['nombre', 'ubicacion_id', 'unidad_administrativa_id', 'participa_activos_fijos', 'observaciones']

interface Props {
  opened: boolean
  onClose: () => void
  onCreada: (c: Constatacion) => void
}

/**
 * Abrir una constatación: qué lugar se recorre, si se quiere de qué unidad se
 * esperan los equipos, y si Activos Fijos y Bodega va en el recorrido (su
 * responsable firma el acta).
 */
export function NuevaConstatacionModal({ opened, ...props }: Props) {
  return opened ? <Formulario {...props} /> : null
}

function Formulario({ onClose, onCreada }: Omit<Props, 'opened'>) {
  const contained = useContainedInput()
  const { data: ubicaciones = [] } = useUbicaciones()
  const { data: unidades = [] } = useTodasUnidades()
  const { crear } = useConstatacionMutations()
  const { register, control, handleSubmit, setError, formState: { errors } } = useForm<ConstatacionFormData>({
    resolver: zodResolver(constatacionSchema),
    defaultValues: { nombre: '', unidad_administrativa_id: null, participa_activos_fijos: false, observaciones: '' },
  })

  const enviar = (v: ConstatacionFormData) =>
    crear.mutateAsync({ ...v, observaciones: v.observaciones || null })
      .then((c) => { onClose(); onCreada(c) })
      .catch((e) => erroresAlFormulario(e, setError, CAMPOS, 'No se pudo abrir la constatación'))

  return (
    <FormModal opened onClose={onClose} title="Nueva constatación física" onSubmit={handleSubmit(enviar)}
      submitLabel="Abrir constatación" submitting={crear.isPending}>
      <Grid>
        <Grid.Col span={12}>
          <TextInput label="Nombre" placeholder="Ej.: Piso 3, octubre de 2026" withAsterisk
            error={errors.nombre?.message} {...contained} {...register('nombre')} />
        </Grid.Col>
        <Grid.Col span={12}>
          <Controller name="ubicacion_id" control={control} render={({ field }) => (
            <Select label="Ubicación que se recorre" placeholder="Sede y piso" withAsterisk searchable {...contained}
              description="Se esperan los equipos que el sistema tiene en ella."
              error={errors.ubicacion_id?.message}
              data={ubicaciones.map((u) => ({ value: String(u.id), label: u.nombre }))}
              value={field.value ? String(field.value) : null} onChange={(v) => field.onChange(v ? Number(v) : undefined)} />
          )} />
        </Grid.Col>
        <Grid.Col span={12}>
          <Controller name="unidad_administrativa_id" control={control} render={({ field }) => (
            <Select label="Unidad" placeholder="Todas las de la ubicación" clearable searchable {...contained}
              description="Opcional: solo los equipos de esa unidad."
              error={errors.unidad_administrativa_id?.message}
              data={unidades.map((u) => ({ value: String(u.id), label: u.nombre ?? '' }))}
              value={field.value ? String(field.value) : null} onChange={(v) => field.onChange(v ? Number(v) : null)} />
          )} />
        </Grid.Col>
        <Grid.Col span={12}>
          <Controller name="participa_activos_fijos" control={control} render={({ field }) => (
            <Switch label="Participa Activos Fijos y Bodega" checked={field.value} onChange={(e) => field.onChange(e.currentTarget.checked)}
              description={field.value
                ? 'Su responsable firma el acta junto a TIC. Se puede cambiar mientras la constatación siga en curso.'
                : 'El acta lleva solo las firmas de TIC. Se puede cambiar mientras la constatación siga en curso.'} />
          )} />
        </Grid.Col>
        <Grid.Col span={12}>
          <Textarea label="Observaciones" placeholder="Opcional" autosize minRows={2}
            error={errors.observaciones?.message} {...contained} {...register('observaciones')} />
        </Grid.Col>
      </Grid>
    </FormModal>
  )
}
