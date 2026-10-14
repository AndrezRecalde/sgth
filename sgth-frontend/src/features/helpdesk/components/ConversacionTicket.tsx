'use client'

import { Anchor, Button, FileInput, Grid, Group, Paper, Stack, Switch, Text, Textarea } from '@mantine/core'
import { IconPaperclip, IconSend } from '@tabler/icons-react'
import { Controller, useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { StatusBadge } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { formatFechaHora } from '@/lib/fecha'
import { ADJUNTOS_TICKET } from '../constants/estados'
import { useArchivoTicket, useTicketSolicitanteMutations } from '../hooks/useTickets'
import { mensajeSchema, type MensajeFormData } from '../schemas/ticket.schema'
import type { Ticket } from '../services/tipos'

interface Props {
  ticket: Ticket
  /** Quién mira, para distinguir sus mensajes. */
  usuarioId: number | null
  /** Cerrado o anulado ya no recibe mensajes. */
  puedeEscribir: boolean
  /** Quien atiende puede dejar notas internas, que el solicitante no ve. */
  conNotasInternas?: boolean
}

/**
 * La conversación de un ticket: los mensajes con su archivo y, abajo, para
 * escribir. Si el ticket esperaba al solicitante, su respuesta lo devuelve a
 * TIC sola.
 */
export function ConversacionTicket({ ticket, usuarioId, puedeEscribir, conNotasInternas = false }: Props) {
  const contained = useContainedInput()
  const { comentar } = useTicketSolicitanteMutations()
  const archivo = useArchivoTicket()
  const { register, control, handleSubmit, reset, setError, setValue, formState: { errors } } =
    useForm<MensajeFormData>({
      resolver: zodResolver(mensajeSchema),
      defaultValues: { comentario: '', archivo: null, interno: false },
    })
  const interno = useWatch({ control, name: 'interno' })

  const enviar = (v: MensajeFormData) =>
    comentar.mutateAsync({ id: ticket.id, comentario: v.comentario, archivo: v.archivo, interno: v.interno })
      .then(() => reset({ comentario: '', archivo: null, interno: false }))
      .catch((e) => erroresAlFormulario(e, setError, ['comentario', 'archivo'], 'No se pudo enviar el mensaje'))

  const mensajes = ticket.comentarios ?? []

  return (
    <Stack gap="sm">
      {mensajes.length === 0 && <Text size="sm" c="dimmed">Todavía no hay mensajes.</Text>}
      {mensajes.map((m) => {
        const propio = m.autor_id === usuarioId
        return (
          <Paper key={m.id} withBorder p="sm" radius="md" ml={propio ? 'xl' : 0} mr={propio ? 0 : 'xl'}
            style={{ background: m.es_interno ? 'var(--mantine-color-amber-light)' : propio ? 'var(--sgth-accent-light)' : 'var(--sgth-surface)' }}>
            <Group justify="space-between" gap="xs" mb={4} wrap="nowrap">
              <Group gap={6}>
                <Text size="xs" fw={600}>{propio ? 'Usted' : m.autor ?? 'TIC'}</Text>
                {m.es_interno && <StatusBadge tone="warning" size="xs">Nota interna</StatusBadge>}
              </Group>
              <Text size="xs" c="dimmed">{formatFechaHora(m.created_at)}</Text>
            </Group>
            <Text size="sm" style={{ whiteSpace: 'pre-line' }}>{m.comentario}</Text>
            {m.tiene_archivo && (
              <Anchor component="button" type="button" size="xs" mt={4}
                onClick={() => archivo.mutate({ id: ticket.id, comentarioId: m.id, nombre: m.archivo_nombre ?? 'archivo' })}>
                <Group gap={4}><IconPaperclip size={12} />{m.archivo_nombre ?? 'Archivo adjunto'}</Group>
              </Anchor>
            )}
          </Paper>
        )
      })}

      {puedeEscribir && (
        <form noValidate onSubmit={handleSubmit(enviar)}>
          <Grid align="flex-end">
            <Grid.Col span={12}>
              <Textarea label={interno ? 'Nota interna' : 'Mensaje'} autosize minRows={2}
                placeholder={interno ? 'Solo la ve el equipo de TIC' : 'Escriba su mensaje'}
                error={errors.comentario?.message} {...contained} {...register('comentario')} />
            </Grid.Col>
            <Grid.Col span={{ base: 12, sm: 6 }}>
              <Controller name="archivo" control={control} render={({ field }) => (
                <FileInput label="Adjunto" placeholder={ADJUNTOS_TICKET.formatos} clearable {...contained}
                  accept={ADJUNTOS_TICKET.mimes.join(',')} error={errors.archivo?.message}
                  value={field.value} onChange={field.onChange} />
              )} />
            </Grid.Col>
            <Grid.Col span={{ base: 12, sm: 6 }}>
              <Group justify="flex-end" gap="md" wrap="wrap">
                {conNotasInternas && (
                  <Switch label="Nota interna" checked={interno} onChange={(e) => setValue('interno', e.currentTarget.checked)} />
                )}
                <Button type="submit" leftSection={<IconSend size={14} />} loading={comentar.isPending}>Enviar</Button>
              </Group>
            </Grid.Col>
          </Grid>
        </form>
      )}
    </Stack>
  )
}
