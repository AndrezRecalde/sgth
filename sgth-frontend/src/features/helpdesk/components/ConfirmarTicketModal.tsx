'use client'

import { Rating, Stack, Text, Textarea } from '@mantine/core'
import { Controller, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { useTicketSolicitanteMutations } from '../hooks/useTickets'
import { confirmarSchema, type ConfirmarFormData } from '../schemas/ticket.schema'
import type { Ticket } from '../services/tipos'

/** Confirmar que quedó resuelto y, si quiere, calificar la atención. Cierra el ticket. */
export function ConfirmarTicketModal({ ticket, onClose }: { ticket: Ticket | null; onClose: () => void }) {
  return ticket ? <Formulario ticket={ticket} onClose={onClose} /> : null
}

function Formulario({ ticket, onClose }: { ticket: Ticket; onClose: () => void }) {
  const contained = useContainedInput()
  const { confirmar } = useTicketSolicitanteMutations()
  const { register, control, handleSubmit, setError, formState: { errors } } = useForm<ConfirmarFormData>({
    resolver: zodResolver(confirmarSchema),
    defaultValues: { calificacion: null, comentario: '' },
  })

  const enviar = (v: ConfirmarFormData) =>
    confirmar.mutateAsync({ id: ticket.id, calificacion: v.calificacion, comentario: v.comentario || null }).then(onClose)
      .catch((e) => erroresAlFormulario(e, setError, ['calificacion', 'comentario'], null))

  return (
    <FormModal opened onClose={onClose} title={`Confirmar ${ticket.folio}`} onSubmit={handleSubmit(enviar)}
      submitLabel="Confirmar y cerrar" submitting={confirmar.isPending}>
      <Stack gap="md">
        {ticket.solucion && (
          <div>
            <Text size="xs" c="dimmed">La solución de TIC</Text>
            <Text size="sm" style={{ whiteSpace: 'pre-line' }}>{ticket.solucion}</Text>
          </div>
        )}
        <Controller name="calificacion" control={control} render={({ field }) => (
          <Stack gap={4}>
            <Text size="sm" fw={500}>¿Cómo lo atendieron? (opcional)</Text>
            <Rating size="lg" value={field.value ?? 0} onChange={(v) => field.onChange(v || null)} />
          </Stack>
        )} />
        <Textarea label="Comentario (opcional)" placeholder="Ej.: Me atendieron rápido y quedó funcionando." autosize minRows={2}
          error={errors.comentario?.message}
          {...contained} {...register('comentario')} />
      </Stack>
    </FormModal>
  )
}
