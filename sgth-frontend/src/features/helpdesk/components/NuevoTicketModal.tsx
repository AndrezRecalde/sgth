'use client'

import { Select, Stack, Text, TextInput, Textarea } from '@mantine/core'
import { Controller, useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal, notificar } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { ZonaArchivo } from '@/features/expediente/components/ZonaArchivo'
import { useMisEquipos } from '@/features/inventario-ti/hooks/useCustodia'
import { ADJUNTOS_TICKET } from '../constants/estados'
import { useCatalogosHelpdesk, useTicketSolicitanteMutations } from '../hooks/useTickets'
import { nuevoTicketSchema, type NuevoTicketFormData } from '../schemas/ticket.schema'
import type { Ticket } from '../services/tipos'

const CAMPOS = ['categoria_id', 'asunto', 'descripcion', 'bien_informatico_id', 'archivo'] as const

/** Al abrirse: vacío, o desde «Mis equipos» con el equipo ya elegido. */
export type PropuestaTicket = { bienId?: number }

interface Props {
  propuesta: PropuestaTicket | null
  onClose: () => void
  onCreado?: (ticket: Ticket) => void
}

/**
 * Pedir ayuda a TIC: de qué se trata, qué pasa, el equipo si es uno de los
 * suyos y una captura si ayuda. La prioridad la pone TIC.
 */
export function NuevoTicketModal({ propuesta, ...props }: Props) {
  return propuesta ? <Formulario propuesta={propuesta} {...props} /> : null
}

function Formulario({ propuesta, onClose, onCreado }: Props & { propuesta: PropuestaTicket }) {
  const contained = useContainedInput()
  const { crear } = useTicketSolicitanteMutations()
  const { data: catalogos } = useCatalogosHelpdesk()
  const { data: equipos = [] } = useMisEquipos()
  const categorias = catalogos?.categorias ?? []
  const hardware = categorias.find((c) => c.es_hardware)

  const { register, control, handleSubmit, setError, formState: { errors } } = useForm<NuevoTicketFormData>({
    resolver: zodResolver(nuevoTicketSchema),
    defaultValues: {
      asunto: '', descripcion: '', archivo: null, bien_informatico_id: propuesta.bienId ?? null,
      ...(propuesta.bienId && hardware ? { categoria_id: hardware.id } : {}),
    },
  })
  const categoriaId = useWatch({ control, name: 'categoria_id' })
  const categoria = categorias.find((c) => c.id === categoriaId)

  const enviar = (v: NuevoTicketFormData) =>
    crear.mutateAsync(v)
      .then((t) => { onClose(); onCreado?.(t) })
      .catch((e) => erroresAlFormulario(e, setError, [...CAMPOS], 'No se pudo registrar el ticket'))

  return (
    <FormModal opened onClose={onClose} title="Pedir ayuda a TIC" onSubmit={handleSubmit(enviar)}
      submitLabel="Enviar" submitting={crear.isPending} size="lg">
      <Stack gap="md">
        <Controller name="categoria_id" control={control} render={({ field }) => (
          <Select label="¿De qué se trata?" placeholder="Elija la categoría" withAsterisk searchable {...contained}
            data={categorias.map((c) => ({ value: String(c.id), label: c.nombre }))}
            description={categoria?.descripcion ?? undefined} error={errors.categoria_id?.message}
            value={field.value ? String(field.value) : null} onChange={(v) => field.onChange(v ? Number(v) : undefined)} />
        )} />
        <TextInput label="Asunto" placeholder="Ej.: La impresora del piso 3 no imprime" withAsterisk
          error={errors.asunto?.message} {...contained} {...register('asunto')} />
        <Textarea label="¿Qué pasa?" placeholder="Desde cuándo, qué mensaje sale, qué ya intentó…" withAsterisk autosize minRows={3}
          error={errors.descripcion?.message} {...contained} {...register('descripcion')} />
        {equipos.length > 0 && (
          <Controller name="bien_informatico_id" control={control} render={({ field }) => (
            <Select label="Equipo" placeholder="Ninguno en particular" clearable {...contained}
              data={equipos.filter((a) => a.bien).map((a) => ({
                value: String(a.bien?.id), label: `${a.bien?.codigo_institucional} · ${[a.bien?.tipo, a.bien?.marca, a.bien?.modelo].filter(Boolean).join(' ')}`,
              }))}
              error={errors.bien_informatico_id?.message}
              value={field.value ? String(field.value) : null} onChange={(v) => field.onChange(v ? Number(v) : null)} />
          )} />
        )}
        <Controller name="archivo" control={control} render={({ field }) => (
          <ZonaArchivo value={field.value} onChange={field.onChange} error={errors.archivo?.message}
            onRechazo={(m) => notificar.error('El archivo no sirve', m)}
            accept={ADJUNTOS_TICKET.mimes} maxMb={ADJUNTOS_TICKET.maxMb} formatos={ADJUNTOS_TICKET.formatos} />
        )} />
        <Text size="xs" c="dimmed">Una captura de pantalla del error ayuda a resolverlo más rápido.</Text>
      </Stack>
    </FormModal>
  )
}
