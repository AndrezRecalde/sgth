'use client'

import { Grid, Select, TextInput, Textarea } from '@mantine/core'
import { Controller, useForm, useWatch, type DefaultValues } from 'react-hook-form'
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

const CAMPOS = [
  'servidor_id', 'prioridad', 'problema_frecuente_id', 'comentario', 'categoria_id', 'asunto', 'descripcion', 'bien_informatico_id',
] as const

const VALORES: DefaultValues<RegistroTicketFormData> = {
  problema_frecuente_id: null, asunto: '', descripcion: '', comentario: '', bien_informatico_id: null, archivo: null, prioridad: 'media',
}

/**
 * TIC registra el ticket de quien llamó o vino al mostrador. Con un problema
 * frecuente, lo demás sale de él y la prioridad propuesta es la suya; sin él,
 * se escribe como siempre. Puede vincular cualquier equipo del inventario,
 * no solo los del servidor.
 */
export function RegistrarTicketModal({ opened, onClose, onCreado }: { opened: boolean; onClose: () => void; onCreado: (t: Ticket) => void }) {
  return opened ? <Formulario onClose={onClose} onCreado={onCreado} /> : null
}

function Formulario({ onClose, onCreado }: { onClose: () => void; onCreado: (t: Ticket) => void }) {
  const contained = useContainedInput()
  const { registrar } = useTicketTicMutations()
  const { data: catalogos } = useCatalogosHelpdesk()
  const problemas = catalogos?.problemas ?? []
  const { register, control, handleSubmit, setError, setValue, formState: { errors } } = useForm<RegistroTicketFormData>({
    resolver: zodResolver(registroTicketSchema),
    defaultValues: VALORES,
  })
  const problemaId = useWatch({ control, name: 'problema_frecuente_id' })
  const problema = problemas.find((p) => p.id === problemaId)

  const enviar = (v: RegistroTicketFormData) => {
    const comunes = { servidor_id: v.servidor_id, prioridad: v.prioridad, bien_informatico_id: v.bien_informatico_id, archivo: v.archivo }
    const datos = v.problema_frecuente_id
      ? { ...comunes, problema_frecuente_id: v.problema_frecuente_id, comentario: v.comentario || null }
      : { ...comunes, categoria_id: v.categoria_id, asunto: v.asunto, descripcion: v.descripcion }

    return registrar.mutateAsync(datos).then((t) => { onClose(); onCreado(t) })
      .catch((e) => erroresAlFormulario(e, setError, [...CAMPOS], 'No se pudo registrar el ticket'))
  }

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
        {problemas.length > 0 && (
          <Grid.Col span={12}>
            <Controller name="problema_frecuente_id" control={control} render={({ field }) => (
              <Select label="Problema frecuente" placeholder="Otro problema: se describe abajo" clearable searchable {...contained}
                description={problema ? `${problema.categoria ?? ''}${problema.unidad_tic ? ` · ${problema.unidad_tic}` : ''}` : undefined}
                error={errors.problema_frecuente_id?.message}
                data={problemas.map((p) => ({ value: String(p.id), label: p.nombre }))}
                value={field.value ? String(field.value) : null}
                onChange={(v) => {
                  const elegido = problemas.find((p) => p.id === Number(v))
                  field.onChange(elegido?.id ?? null)
                  // La prioridad que TIC fijó en el problema; se puede cambiar.
                  if (elegido) setValue('prioridad', elegido.prioridad)
                }} />
            )} />
          </Grid.Col>
        )}
        {problema ? (
          <Grid.Col span={12}>
            <Textarea label="Lo que contó" placeholder="Opcional. Va debajo de la descripción del problema." autosize minRows={2}
              description={problema.descripcion} error={errors.comentario?.message} {...contained} {...register('comentario')} />
          </Grid.Col>
        ) : (
          <>
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
          </>
        )}
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
