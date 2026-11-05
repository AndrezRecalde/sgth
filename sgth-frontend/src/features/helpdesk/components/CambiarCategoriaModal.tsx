'use client'

import { Select, Stack, Text, Textarea } from '@mantine/core'
import { Controller, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { useCatalogosHelpdesk } from '../hooks/useTickets'
import { useTicketTicMutations } from '../hooks/useTicketsTic'
import { cambiarCategoriaSchema, type CambiarCategoriaFormData } from '../schemas/tramite.schema'
import type { Ticket } from '../services/tipos'

/**
 * Corregir la categoría de un ticket mal clasificado (2026-11-05): quien lo
 * atiende, con un motivo que queda en la línea de tiempo. La subunidad y el
 * plazo pasan a ser los de la nueva categoría, contados desde que se abrió.
 */
export function CambiarCategoriaModal({ ticket, onClose }: { ticket: Ticket; onClose: () => void }) {
  const contained = useContainedInput()
  const { data: catalogos } = useCatalogosHelpdesk()
  const { cambiarCategoria } = useTicketTicMutations()
  const categorias = (catalogos?.categorias ?? []).filter((c) => c.id !== ticket.categoria?.id)

  const { register, control, handleSubmit, setError, formState: { errors } } = useForm<CambiarCategoriaFormData>({
    resolver: zodResolver(cambiarCategoriaSchema),
    defaultValues: { motivo: '' },
  })

  const enviar = (v: CambiarCategoriaFormData) =>
    cambiarCategoria.mutateAsync({ id: ticket.id, categoriaId: v.categoria_id, motivo: v.motivo })
      .then(onClose)
      .catch((e) => erroresAlFormulario(e, setError, ['categoria_id', 'motivo'], 'No se pudo cambiar la categoría'))

  return (
    <FormModal opened onClose={onClose} title={`Cambiar la categoría de ${ticket.folio}`} onSubmit={handleSubmit(enviar)}
      submitLabel="Cambiar categoría" submitting={cambiarCategoria.isPending}>
      <Stack gap="md">
        <Text size="sm">
          Hoy es <b>{ticket.categoria?.nombre ?? 'sin categoría'}</b>. La subunidad que atiende y el plazo pasan a ser los de la nueva
          categoría, contados desde que se abrió el ticket.
        </Text>
        <Controller name="categoria_id" control={control} render={({ field }) => (
          <Select label="Nueva categoría" placeholder="Elija la categoría" withAsterisk searchable {...contained}
            error={errors.categoria_id?.message}
            data={categorias.map((c) => ({ value: String(c.id), label: c.nombre }))}
            value={field.value ? String(field.value) : null} onChange={(v) => field.onChange(v ? Number(v) : undefined)} />
        )} />
        <Textarea label="Motivo" placeholder="Ej.: era un virus, no una falla del equipo" withAsterisk autosize minRows={2}
          description="Queda en la línea de tiempo del ticket."
          error={errors.motivo?.message} {...contained} {...register('motivo')} />
      </Stack>
    </FormModal>
  )
}
