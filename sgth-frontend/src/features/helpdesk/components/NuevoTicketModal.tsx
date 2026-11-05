'use client'

import { useState } from 'react'
import { Button, Paper, Select, Stack, Text, TextInput, Textarea } from '@mantine/core'
import { IconArrowLeft } from '@tabler/icons-react'
import { Controller, useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { DataState, ModalFooter, SgthModal, notificar } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { ZonaArchivo } from '@/features/expediente/components/ZonaArchivo'
import { useMisEquipos } from '@/features/inventario-ti/hooks/useCustodia'
import { ADJUNTOS_TICKET, ETIQUETA_PRIORIDAD } from '../constants/estados'
import { useCatalogosHelpdesk, useTicketSolicitanteMutations } from '../hooks/useTickets'
import {
  conProblemaSchema, nuevoTicketSchema, type ConProblemaFormData, type NuevoTicketFormData,
} from '../schemas/ticket.schema'
import type { ProblemaOfrecido, Ticket } from '../services/tipos'
import { ProblemasFrecuentesGrid } from './ProblemasFrecuentesGrid'

const FORM_PROBLEMA = 'ticket-con-problema'
const FORM_LIBRE = 'ticket-libre'
const CAMPOS_LIBRE = ['categoria_id', 'asunto', 'descripcion', 'bien_informatico_id', 'archivo'] as const

/** Al abrirse: vacío, o desde «Mis equipos» con el equipo ya elegido. */
export type PropuestaTicket = { bienId?: number }

interface Props {
  propuesta: PropuestaTicket | null
  onClose: () => void
  onCreado?: (ticket: Ticket) => void
}

type Paso = { tipo: 'elegir' } | { tipo: 'problema'; problema: ProblemaOfrecido } | { tipo: 'libre' }

/**
 * Pedir ayuda a TIC (2026-11-05): el servidor toca uno de los problemas
 * frecuentes, confirma y envía, sin escribir nada. Si su problema no está,
 * «Otro problema» abre el formulario libre. La prioridad la pone TIC.
 */
export function NuevoTicketModal({ propuesta, ...props }: Props) {
  return propuesta ? <Asistente propuesta={propuesta} {...props} /> : null
}

function Asistente({ propuesta, onClose, onCreado }: Props & { propuesta: PropuestaTicket }) {
  const { data: catalogos, isLoading, error } = useCatalogosHelpdesk()
  const { crear } = useTicketSolicitanteMutations()
  const [elegido, setElegido] = useState<Paso | null>(null)
  const problemas = catalogos?.problemas ?? []
  // Sin problemas frecuentes cargados, directo al formulario libre.
  const paso: Paso = elegido ?? (catalogos && !problemas.length ? { tipo: 'libre' } : { tipo: 'elegir' })
  const volver = problemas.length ? () => setElegido({ tipo: 'elegir' }) : undefined
  const creado = (t: Ticket) => { onClose(); onCreado?.(t) }

  return (
    <SgthModal opened onClose={onClose} title="Pedir ayuda a TIC" size="lg"
      closeOnClickOutside={!crear.isPending} closeOnEscape={!crear.isPending}>
      {paso.tipo === 'elegir' && (
        <>
          <DataState loading={isLoading} error={error}>
            <Stack gap="sm">
              <Text size="sm">¿Qué necesita? Elija una opción y TIC recibirá su pedido.</Text>
              <ProblemasFrecuentesGrid problemas={problemas}
                onElegir={(problema) => setElegido({ tipo: 'problema', problema })} onOtro={() => setElegido({ tipo: 'libre' })} />
            </Stack>
          </DataState>
          <ModalFooter onCancel={onClose} sinPrincipal />
        </>
      )}
      {paso.tipo === 'problema' && (
        <ConProblema problema={paso.problema} bienId={propuesta.bienId} crear={crear} onVolver={volver} onClose={onClose} onCreado={creado} />
      )}
      {paso.tipo === 'libre' && (
        <Libre bienId={propuesta.bienId} crear={crear} onVolver={volver} onClose={onClose} onCreado={creado} />
      )}
    </SgthModal>
  )
}

interface PasoProps {
  bienId?: number
  /** Una sola mutación para el modal: mientras envía, no se cierra. */
  crear: ReturnType<typeof useTicketSolicitanteMutations>['crear']
  onVolver?: () => void
  onClose: () => void
  onCreado: (ticket: Ticket) => void
}

const Atras = ({ onVolver }: { onVolver?: () => void }) =>
  onVolver ? <Button variant="default" leftSection={<IconArrowLeft size={16} />} onClick={onVolver}>Atrás</Button> : null

/** El equipo, si es uno de los suyos. Sin equipos a su cargo, no se pregunta. */
function SelectorEquipo({ value, onChange, error }: { value: number | null; onChange: (id: number | null) => void; error?: string }) {
  const contained = useContainedInput()
  const { data: equipos = [] } = useMisEquipos()
  if (!equipos.length) return null

  return (
    <Select label="Equipo" placeholder="Ninguno en particular" clearable {...contained}
      description="Opcional. Si no es uno de los suyos, cuéntelo en el comentario."
      data={equipos.filter((a) => a.bien).map((a) => ({
        value: String(a.bien?.id), label: `${a.bien?.codigo_institucional} · ${[a.bien?.tipo, a.bien?.marca, a.bien?.modelo].filter(Boolean).join(' ')}`,
      }))}
      error={error} value={value ? String(value) : null} onChange={(v) => onChange(v ? Number(v) : null)} />
  )
}

/** Confirmar el problema elegido: lo que se enviará, y un comentario y el equipo si quiere. */
function ConProblema({ problema, bienId, crear, onVolver, onClose, onCreado }: PasoProps & { problema: ProblemaOfrecido }) {
  const contained = useContainedInput()
  const { register, control, handleSubmit, setError, formState: { errors } } = useForm<ConProblemaFormData>({
    resolver: zodResolver(conProblemaSchema),
    defaultValues: { comentario: '', bien_informatico_id: bienId ?? null },
  })

  const enviar = (v: ConProblemaFormData) =>
    crear.mutateAsync({ problema_frecuente_id: problema.id, comentario: v.comentario || null, bien_informatico_id: v.bien_informatico_id })
      .then(onCreado)
      .catch((e) => erroresAlFormulario(e, setError, ['comentario', 'bien_informatico_id'], 'No se pudo enviar su pedido'))

  return (
    <form id={FORM_PROBLEMA} onSubmit={handleSubmit(enviar)} noValidate>
      <Stack gap="md">
        <Paper withBorder p="md" radius="md">
          <Stack gap={4}>
            <Text fw={600}>{problema.nombre}</Text>
            <Text size="sm" c="dimmed">{problema.descripcion}</Text>
            <Text size="sm" mt={4}>
              Se enviará a {problema.unidad_tic ?? 'la Unidad de TIC'} con prioridad {ETIQUETA_PRIORIDAD[problema.prioridad].toLowerCase()}.
            </Text>
          </Stack>
        </Paper>
        <Textarea label="¿Algo más que contar?" placeholder="Opcional. Ej.: es la impresora del piso 3; sale un mensaje de error…"
          autosize minRows={2} error={errors.comentario?.message} {...contained} {...register('comentario')} />
        <Controller name="bien_informatico_id" control={control} render={({ field }) => (
          <SelectorEquipo value={field.value} onChange={field.onChange} error={errors.bien_informatico_id?.message} />
        )} />
      </Stack>
      <ModalFooter onCancel={onClose} form={FORM_PROBLEMA} submitLabel="Enviar" submitting={crear.isPending}
        leftSection={<Atras onVolver={onVolver} />} />
    </form>
  )
}

/** «Otro problema»: el formulario libre de siempre. */
function Libre({ bienId, crear, onVolver, onClose, onCreado }: PasoProps) {
  const contained = useContainedInput()
  const { data: catalogos } = useCatalogosHelpdesk()
  const categorias = catalogos?.categorias ?? []
  const hardware = categorias.find((c) => c.es_hardware)

  const { register, control, handleSubmit, setError, formState: { errors } } = useForm<NuevoTicketFormData>({
    resolver: zodResolver(nuevoTicketSchema),
    defaultValues: {
      asunto: '', descripcion: '', archivo: null, bien_informatico_id: bienId ?? null,
      ...(bienId && hardware ? { categoria_id: hardware.id } : {}),
    },
  })
  const categoriaId = useWatch({ control, name: 'categoria_id' })
  const categoria = categorias.find((c) => c.id === categoriaId)

  const enviar = (v: NuevoTicketFormData) =>
    crear.mutateAsync(v)
      .then(onCreado)
      .catch((e) => erroresAlFormulario(e, setError, [...CAMPOS_LIBRE], 'No se pudo registrar el ticket'))

  return (
    <form id={FORM_LIBRE} onSubmit={handleSubmit(enviar)} noValidate>
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
        <Controller name="bien_informatico_id" control={control} render={({ field }) => (
          <SelectorEquipo value={field.value} onChange={field.onChange} error={errors.bien_informatico_id?.message} />
        )} />
        <Controller name="archivo" control={control} render={({ field }) => (
          <ZonaArchivo value={field.value} onChange={field.onChange} error={errors.archivo?.message}
            onRechazo={(m) => notificar.error('El archivo no sirve', m)}
            accept={ADJUNTOS_TICKET.mimes} maxMb={ADJUNTOS_TICKET.maxMb} formatos={ADJUNTOS_TICKET.formatos} />
        )} />
        <Text size="xs" c="dimmed">Una captura de pantalla del error ayuda a resolverlo más rápido.</Text>
      </Stack>
      <ModalFooter onCancel={onClose} form={FORM_LIBRE} submitLabel="Enviar" submitting={crear.isPending}
        leftSection={<Atras onVolver={onVolver} />} />
    </form>
  )
}
