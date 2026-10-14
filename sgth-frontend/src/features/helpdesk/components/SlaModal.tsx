'use client'

import { Grid, NumberInput, Select, Text } from '@mantine/core'
import { Controller, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { ETIQUETA_PRIORIDAD, PRIORIDADES } from '../constants/estados'
import { useCategoriasTicket, useConfiguracionMutations } from '../hooks/useConfiguracionHelpdesk'
import { slaSchema, type SlaFormData } from '../schemas/configuracion.schema'
import { configuracionHelpdeskService } from '../services/configuracionService'
import type { GuardarSlaData, PrioridadTicket } from '../services/tipos'

const CAMPOS = ['prioridad', 'categoria_id', 'tiempo_respuesta_horas', 'tiempo_resolucion_horas']

interface Props {
  opened: boolean
  onClose: () => void
  /** La regla que se edita; sin ella se crea una. */
  id?: number
  /** Lo que rige hoy en esa fila. Sin valores, es un plazo nuevo para una categoría. */
  valores?: GuardarSlaData
}

/**
 * Un plazo del SLA. Desde una fila de la tabla solo cambian las horas; un
 * plazo nuevo es siempre para una categoría, porque cada prioridad ya tiene
 * su fila general.
 */
export function SlaModal({ opened, ...props }: Props) {
  return opened ? <Formulario {...props} /> : null
}

function Formulario({ onClose, id, valores }: Omit<Props, 'opened'>) {
  const contained = useContainedInput()
  const porCategoria = !valores
  const { data: categorias = [] } = useCategoriasTicket()
  const { guardar } = useConfiguracionMutations('sla', configuracionHelpdeskService.sla)
  const { control, handleSubmit, setError, formState: { errors } } = useForm<SlaFormData>({
    resolver: zodResolver(slaSchema),
    defaultValues: valores ?? { prioridad: 'media', categoria_id: null },
  })

  const enviar = (v: SlaFormData) => {
    if (porCategoria && !v.categoria_id) {
      setError('categoria_id', { message: 'Elija la categoría' })
      return
    }
    return guardar.mutateAsync({ id, data: v }).then(onClose)
      .catch((e) => erroresAlFormulario(e, setError, CAMPOS, 'No se pudo guardar el plazo'))
  }

  const horas = (name: 'tiempo_respuesta_horas' | 'tiempo_resolucion_horas', label: string) => (
    <Controller name={name} control={control} render={({ field }) => (
      <NumberInput label={label} withAsterisk min={0.1} max={2000} decimalScale={1} hideControls placeholder="Horas hábiles"
        decimalSeparator="," suffix=" h" error={errors[name]?.message} {...contained}
        value={field.value ?? ''} onChange={(v) => field.onChange(typeof v === 'number' ? v : Number.NaN)} />
    )} />
  )

  const titulo = id ? 'Editar plazo' : porCategoria ? 'Plazo para una categoría' : 'Fijar el plazo general'

  return (
    <FormModal opened onClose={onClose} title={titulo} onSubmit={handleSubmit(enviar)}
      submitLabel={id ? 'Guardar cambios' : 'Guardar plazo'} submitting={guardar.isPending}>
      <Grid>
        <Grid.Col span={{ base: 12, sm: 5 }}>
          <Controller name="prioridad" control={control} render={({ field }) => (
            <Select label="Prioridad" withAsterisk allowDeselect={false} disabled={!porCategoria} {...contained}
              error={errors.prioridad?.message}
              data={PRIORIDADES.map((p) => ({ value: p, label: ETIQUETA_PRIORIDAD[p] }))}
              value={field.value} onChange={(v) => field.onChange((v as PrioridadTicket | null) ?? field.value)} />
          )} />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 7 }}>
          <Controller name="categoria_id" control={control} render={({ field }) => (
            <Select label="Categoría" withAsterisk={porCategoria} disabled={!porCategoria} searchable {...contained}
              placeholder={porCategoria ? 'Elija la categoría' : 'Todas las categorías'}
              error={errors.categoria_id?.message}
              data={categorias.map((c) => ({ value: String(c.id), label: c.activa ? c.nombre : `${c.nombre} (inactiva)` }))}
              value={field.value ? String(field.value) : null} onChange={(v) => field.onChange(v ? Number(v) : null)} />
          )} />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          {horas('tiempo_respuesta_horas', 'Primera respuesta')}
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          {horas('tiempo_resolucion_horas', 'Resolución')}
        </Grid.Col>
        <Grid.Col span={12}>
          <Text size="xs" c="dimmed">
            En horas hábiles. La primera respuesta corre hasta que TIC toma o responde el ticket; la resolución, hasta darlo por resuelto.
          </Text>
        </Grid.Col>
      </Grid>
    </FormModal>
  )
}
