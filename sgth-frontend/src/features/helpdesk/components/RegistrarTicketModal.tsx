'use client'

import { Grid, Select, TextInput, Textarea } from '@mantine/core'
import { Controller, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { BuscarServidorSelect } from '@/features/expediente/components/BuscarServidorSelect'
import { BuscarEquipoSelect } from '@/features/inventario-ti/components/BuscarEquipoSelect'
import { useCatalogosHelpdesk } from '../hooks/useTickets'
import { useTicketTicMutations } from '../hooks/useTicketsTic'
import { registroTicketSchema, type RegistroTicketFormData } from '../schemas/ticket.schema'
import type { Ticket } from '../services/tipos'

const CAMPOS = ['servidor_id', 'prioridad', 'categoria_id', 'asunto', 'descripcion', 'bien_informatico_id'] as const

/**
 * TIC registra el ticket de quien llamó o vino al mostrador. Puede vincular
 * cualquier equipo del inventario, no solo los del servidor.
 */
export function RegistrarTicketModal({ opened, onClose, onCreado }: { opened: boolean; onClose: () => void; onCreado: (t: Ticket) => void }) {
  return opened ? <Formulario onClose={onClose} onCreado={onCreado} /> : null
}

function Formulario({ onClose, onCreado }: { onClose: () => void; onCreado: (t: Ticket) => void }) {
  const contained = useContainedInput()
  const { registrar } = useTicketTicMutations()
  const { data: catalogos } = useCatalogosHelpdesk()
  const { register, control, handleSubmit, setError, formState: { errors } } = useForm<RegistroTicketFormData>({
    resolver: zodResolver(registroTicketSchema),
    defaultValues: { asunto: '', descripcion: '', bien_informatico_id: null, archivo: null, prioridad: 'media' },
  })

  const enviar = (v: RegistroTicketFormData) =>
    registrar.mutateAsync(v).then((t) => { onClose(); onCreado(t) })
      .catch((e) => erroresAlFormulario(e, setError, [...CAMPOS], 'No se pudo registrar el ticket'))

  return (
    <FormModal opened onClose={onClose} title="Registrar un ticket" onSubmit={handleSubmit(enviar)}
      submitLabel="Registrar" submitting={registrar.isPending} size="lg">
      <Grid>
        <Grid.Col span={{ base: 12, sm: 8 }}>
          <Controller name="servidor_id" control={control} render={({ field }) => (
            <BuscarServidorSelect label="Quién pide la ayuda" required value={field.value ?? null}
              onChange={(id) => field.onChange(id ?? undefined)} error={errors.servidor_id?.message} />
          )} />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 4 }}>
          <Controller name="prioridad" control={control} render={({ field }) => (
            <Select label="Prioridad" placeholder="Elija la prioridad" allowDeselect={false} {...contained}
              data={(catalogos?.prioridades ?? []).map((p) => ({ value: p.valor, label: p.etiqueta }))}
              value={field.value} onChange={(v) => field.onChange(catalogos?.prioridades.find((p) => p.valor === v)?.valor ?? field.value)} />
          )} />
        </Grid.Col>
        <Grid.Col span={12}>
          <Controller name="categoria_id" control={control} render={({ field }) => (
            <Select label="Categoría" placeholder="Elija la categoría" withAsterisk searchable {...contained} error={errors.categoria_id?.message}
              data={(catalogos?.categorias ?? []).map((c) => ({ value: String(c.id), label: c.nombre }))}
              value={field.value ? String(field.value) : null} onChange={(v) => field.onChange(v ? Number(v) : undefined)} />
          )} />
        </Grid.Col>
        <Grid.Col span={12}>
          <TextInput label="Asunto" placeholder="Ej.: No tiene internet en su computador" withAsterisk
            error={errors.asunto?.message} {...contained} {...register('asunto')} />
        </Grid.Col>
        <Grid.Col span={12}>
          <Textarea label="¿Qué pasa?" placeholder="Lo que contó: desde cuándo, qué mensaje sale, qué ya intentó…" withAsterisk
            autosize minRows={3} error={errors.descripcion?.message} {...contained} {...register('descripcion')} />
        </Grid.Col>
        <Grid.Col span={12}>
          <Controller name="bien_informatico_id" control={control} render={({ field }) => (
            <BuscarEquipoSelect label="Equipo" soloPrincipales={false} placeholder="Código, serie o modelo"
              value={field.value} onChange={field.onChange} error={errors.bien_informatico_id?.message} />
          )} />
        </Grid.Col>
      </Grid>
    </FormModal>
  )
}
