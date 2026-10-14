'use client'

import { Grid, NumberInput, Select, TextInput } from '@mantine/core'
import { Controller, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { useCatalogoMutations } from '../hooks/useCatalogosInventario'
import { FICHAS } from '../constants/fichas'
import { tipoSchema, type TipoFormData } from '../schemas/catalogo.schema'
import { catalogosService, type GuardarTipoData } from '../services/catalogosService'
import type { TipoBien } from '../services/tipos'

interface Props {
  opened: boolean
  onClose: () => void
  tipo: TipoBien | null
}

/** Un tipo de equipo y sus años de vida útil, de donde sale el fin de la vida útil de cada equipo. */
export function TipoModal({ opened, onClose, tipo }: Props) {
  return opened ? <Formulario onClose={onClose} tipo={tipo} /> : null
}

function Formulario({ onClose, tipo }: Omit<Props, 'opened'>) {
  const contained = useContainedInput()
  const { guardar } = useCatalogoMutations<GuardarTipoData>('tipo', catalogosService.tipo)
  const { register, control, handleSubmit, setError, formState: { errors } } = useForm<TipoFormData>({
    resolver: zodResolver(tipoSchema),
    defaultValues: { nombre: tipo?.nombre ?? '', anios_vida_util: tipo?.anios_vida_util ?? 0, ficha: tipo?.ficha ?? 'general' },
  })

  const enviar = (v: TipoFormData) =>
    guardar.mutateAsync({ id: tipo?.id, data: v })
      .then(onClose)
      .catch((e) => erroresAlFormulario(e, setError, Object.keys(tipoSchema.shape), 'No se pudo guardar el tipo'))

  return (
    <FormModal
      opened
      onClose={onClose}
      title={tipo ? 'Editar tipo de equipo' : 'Nuevo tipo de equipo'}
      onSubmit={handleSubmit(enviar)}
      submitLabel={tipo ? 'Guardar cambios' : 'Registrar tipo'}
      submitting={guardar.isPending}
    >
      <Grid>
        <Grid.Col span={{ base: 12, sm: 7 }}>
          <TextInput label="Nombre" placeholder="Ej.: Proyector" withAsterisk
            error={errors.nombre?.message} {...contained} {...register('nombre')} />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 5 }}>
          <Controller name="anios_vida_util" control={control} render={({ field }) => (
            <NumberInput label="Vida útil (años)" min={0} max={30} hideControls
              error={errors.anios_vida_util?.message} {...contained}
              value={field.value} onChange={(v) => field.onChange(typeof v === 'number' ? v : Number.NaN)} />
          )} />
        </Grid.Col>
        <Grid.Col span={12}>
          <Controller name="ficha" control={control} render={({ field }) => (
            <Select label="Ficha de características" allowDeselect={false} {...contained}
              description="Qué datos pide el formulario del equipo según su tipo."
              error={errors.ficha?.message}
              data={FICHAS.map((f) => ({ value: f.value, label: `${f.label} · ${f.descripcion}` }))}
              value={field.value} onChange={(v) => field.onChange(FICHAS.find((f) => f.value === v)?.value ?? field.value)} />
          )} />
        </Grid.Col>
      </Grid>
    </FormModal>
  )
}
