'use client'

import { Grid, Select, Switch, TextInput, Textarea } from '@mantine/core'
import { Controller, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { useConfiguracionMutations } from '../hooks/useConfiguracionHelpdesk'
import { useCatalogosHelpdesk } from '../hooks/useTickets'
import { categoriaSchema, type CategoriaFormData } from '../schemas/configuracion.schema'
import { configuracionHelpdeskService } from '../services/configuracionService'
import type { CategoriaTicketConfig } from '../services/tipos'

const CAMPOS = ['nombre', 'descripcion', 'es_hardware', 'unidad_tic_id', 'activa']

interface Props {
  opened: boolean
  onClose: () => void
  categoria: CategoriaTicketConfig | null
}

/** Una categoría de tickets y la subunidad de TIC que la atiende por defecto. */
export function CategoriaTicketModal({ opened, ...props }: Props) {
  return opened ? <Formulario {...props} /> : null
}

function Formulario({ onClose, categoria }: Omit<Props, 'opened'>) {
  const contained = useContainedInput()
  const { data: catalogos } = useCatalogosHelpdesk()
  const { guardar } = useConfiguracionMutations('categoria', configuracionHelpdeskService.categoria)
  const { register, control, handleSubmit, setError, formState: { errors } } = useForm<CategoriaFormData>({
    resolver: zodResolver(categoriaSchema),
    defaultValues: {
      nombre: categoria?.nombre ?? '',
      descripcion: categoria?.descripcion ?? '',
      es_hardware: categoria?.es_hardware ?? false,
      unidad_tic_id: categoria?.unidad_tic_id ?? null,
      activa: categoria?.activa ?? true,
    },
  })

  const enviar = (v: CategoriaFormData) =>
    guardar.mutateAsync({ id: categoria?.id, data: { ...v, descripcion: v.descripcion.trim() || null } })
      .then(onClose)
      .catch((e) => erroresAlFormulario(e, setError, CAMPOS, 'No se pudo guardar la categoría'))

  return (
    <FormModal
      opened
      onClose={onClose}
      title={categoria ? 'Editar categoría' : 'Nueva categoría'}
      onSubmit={handleSubmit(enviar)}
      submitLabel={categoria ? 'Guardar cambios' : 'Registrar categoría'}
      submitting={guardar.isPending}
    >
      <Grid>
        <Grid.Col span={12}>
          <TextInput label="Nombre" placeholder="Ej.: Telefonía IP" withAsterisk
            error={errors.nombre?.message} {...contained} {...register('nombre')} />
        </Grid.Col>
        <Grid.Col span={12}>
          <Textarea label="Descripción" placeholder="Opcional. Ayuda al usuario a elegirla" autosize minRows={2}
            error={errors.descripcion?.message} {...contained} {...register('descripcion')} />
        </Grid.Col>
        <Grid.Col span={12}>
          <Controller name="unidad_tic_id" control={control} render={({ field }) => (
            <Select label="Subunidad que la atiende" placeholder="Sin definir: la reparte la jefatura" clearable {...contained}
              description="Se propone al asignar; se puede cambiar en cada ticket."
              error={errors.unidad_tic_id?.message}
              data={(catalogos?.subunidades ?? []).map((u) => ({ value: String(u.id), label: u.nombre }))}
              value={field.value ? String(field.value) : null} onChange={(v) => field.onChange(v ? Number(v) : null)} />
          )} />
        </Grid.Col>
        <Grid.Col span={12}>
          <Controller name="es_hardware" control={control} render={({ field }) => (
            <Switch label="Se trata de un equipo" description="El usuario indica cuál de sus equipos falla."
              checked={field.value} onChange={(e) => field.onChange(e.currentTarget.checked)} />
          )} />
        </Grid.Col>
        {categoria && (
          <Grid.Col span={12}>
            <Controller name="activa" control={control} render={({ field }) => (
              <Switch label="Activa" description="Inactiva, deja de ofrecerse al abrir un ticket."
                checked={field.value} onChange={(e) => field.onChange(e.currentTarget.checked)} />
            )} />
          </Grid.Col>
        )}
      </Grid>
    </FormModal>
  )
}
