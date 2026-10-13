'use client'

import { Grid, Select, Text } from '@mantine/core'
import { Controller, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { useCatalogosHelpdesk } from '../hooks/useTickets'
import { useTicketTicMutations } from '../hooks/useTicketsTic'
import { asignarSchema, type AsignarFormData } from '../schemas/tramite.schema'
import type { PrioridadTicket, Ticket } from '../services/tipos'

/**
 * Repartir el ticket: el técnico, el área de TIC y la prioridad. Cambiar la
 * prioridad recalcula el plazo del SLA desde el alta.
 */
export function AsignarTicketModal({ ticket, onClose }: { ticket: Ticket; onClose: () => void }) {
  const contained = useContainedInput()
  const { asignar } = useTicketTicMutations()
  const { data: catalogos } = useCatalogosHelpdesk()
  const { control, handleSubmit, setError, formState: { errors } } = useForm<AsignarFormData>({
    resolver: zodResolver(asignarSchema),
    defaultValues: {
      ...(ticket.tecnico_id ? { tecnico_id: ticket.tecnico_id } : {}),
      unidad_tic_id: ticket.unidad_tic?.id ?? null, prioridad: ticket.prioridad,
    },
  })

  const enviar = (v: AsignarFormData) =>
    asignar.mutateAsync({ id: ticket.id, data: v }).then(onClose)
      .catch((e) => erroresAlFormulario(e, setError, ['tecnico_id', 'unidad_tic_id', 'prioridad'], 'No se pudo asignar el ticket'))

  const numero = (name: 'tecnico_id' | 'unidad_tic_id', label: string, data: { id: number; nombre: string }[]) => (
    <Controller name={name} control={control} render={({ field }) => (
      <Select label={label} withAsterisk={name === 'tecnico_id'} searchable {...contained} error={errors[name]?.message}
        data={data.map((x) => ({ value: String(x.id), label: x.nombre }))}
        value={field.value ? String(field.value) : null} onChange={(v) => field.onChange(v ? Number(v) : name === 'tecnico_id' ? undefined : null)} />
    )} />
  )

  return (
    <FormModal opened onClose={onClose} title={`Asignar ${ticket.folio}`} onSubmit={handleSubmit(enviar)}
      submitLabel="Asignar" submitting={asignar.isPending}>
      <Grid>
        <Grid.Col span={12}><Text size="sm">{ticket.asunto}</Text></Grid.Col>
        <Grid.Col span={12}>{numero('tecnico_id', 'Técnico', catalogos?.tecnicos ?? [])}</Grid.Col>
        <Grid.Col span={{ base: 12, sm: 7 }}>{numero('unidad_tic_id', 'Área de TIC', catalogos?.subunidades ?? [])}</Grid.Col>
        <Grid.Col span={{ base: 12, sm: 5 }}>
          <Controller name="prioridad" control={control} render={({ field }) => (
            <Select label="Prioridad" allowDeselect={false} {...contained}
              data={(catalogos?.prioridades ?? []).map((p) => ({ value: p.valor, label: p.etiqueta }))}
              value={field.value} onChange={(v) => field.onChange((v as PrioridadTicket | null) ?? field.value)} />
          )} />
        </Grid.Col>
      </Grid>
    </FormModal>
  )
}
