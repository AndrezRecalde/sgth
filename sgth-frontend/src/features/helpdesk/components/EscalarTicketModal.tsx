'use client'

import { SegmentedControl, Select, Stack, Text, Textarea } from '@mantine/core'
import { Controller, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { useCatalogosHelpdesk } from '../hooks/useTickets'
import { useTicketTicMutations } from '../hooks/useTicketsTic'
import { escalarSchema, type EscalarFormData } from '../schemas/tramite.schema'
import type { Ticket } from '../services/tipos'
import { tecnicosPorSubunidad } from '../utils/tecnicos'

/**
 * Escalar a especialista (nivel 2) o al proveedor (nivel 3, el contrato de
 * vigencia del equipo). Con otro técnico queda asignado a él; sin técnico,
 * vuelve a la cola.
 */
export function EscalarTicketModal({ ticket, onClose }: { ticket: Ticket; onClose: () => void }) {
  const contained = useContainedInput()
  const { escalar } = useTicketTicMutations()
  const { data: catalogos } = useCatalogosHelpdesk()
  const { register, control, handleSubmit, setError, formState: { errors } } = useForm<EscalarFormData>({
    resolver: zodResolver(escalarSchema),
    defaultValues: { nivel: Math.min(ticket.nivel + 1, 3), motivo: '', tecnico_id: null },
  })

  const enviar = (v: EscalarFormData) =>
    escalar.mutateAsync({ id: ticket.id, nivel: v.nivel, motivo: v.motivo, tecnicoId: v.tecnico_id }).then(onClose)
      .catch((e) => erroresAlFormulario(e, setError, ['nivel', 'motivo', 'tecnico_id'], 'No se pudo escalar el ticket'))

  return (
    <FormModal opened onClose={onClose} title={`Escalar ${ticket.folio}`} onSubmit={handleSubmit(enviar)}
      submitLabel="Escalar" submitting={escalar.isPending}>
      <Stack gap="md">
        <Controller name="nivel" control={control} render={({ field }) => (
          <Stack gap={4}>
            <Text size="sm" fw={500}>Nivel</Text>
            <SegmentedControl value={String(field.value)} onChange={(v) => field.onChange(Number(v))}
              data={[
                { value: '2', label: '2 · Especialista', disabled: ticket.nivel >= 2 },
                { value: '3', label: '3 · Proveedor' },
              ]} />
            {errors.nivel && <Text size="xs" c="red">{errors.nivel.message}</Text>}
          </Stack>
        )} />
        <Textarea label="Por qué" withAsterisk autosize minRows={2} placeholder="Ej.: Parece falla del switch del piso; requiere a Infraestructura."
          error={errors.motivo?.message} {...contained}
          {...register('motivo')} />
        <Controller name="tecnico_id" control={control} render={({ field }) => (
          <Select label="A quién" placeholder="De vuelta a la cola" clearable searchable {...contained} error={errors.tecnico_id?.message}
            data={tecnicosPorSubunidad(catalogos)}
            value={field.value ? String(field.value) : null} onChange={(v) => field.onChange(v ? Number(v) : null)} />
        )} />
      </Stack>
    </FormModal>
  )
}
