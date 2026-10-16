'use client'

import { Grid, NumberInput, Stack, Text, Textarea } from '@mantine/core'
import { Controller, useFieldArray, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { DetailList, FormModal, SectionHeading, SgthTable } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { BuscarServidorSelect } from '@/features/expediente/components/BuscarServidorSelect'
import { BuscarEquipoSelect } from '@/features/inventario-ti/components/BuscarEquipoSelect'
import { useConsumibles } from '../hooks/useConsumibles'
import { useSolicitudMutations } from '../hooks/useSolicitudes'
import { solicitudSchema, type SolicitudFormData } from '../schemas/solicitud.schema'
import type { SolicitudBodega } from '../services/solicitudesService'
import { AgregarSelect } from './AgregarSelect'
import { columnasRenglones } from './renglones.columns'

const CAMPOS = ['servidor_id', 'bien_informatico_id', 'motivo', 'items']

/** El ticket del que sale: su solicitante y su equipo ya están puestos. */
export type TicketDeLaSolicitud = {
  id: number
  folio: string
  solicitante: string | null
  equipo: { id: number; texto: string } | null
}

interface Props {
  opened: boolean
  onClose: () => void
  ticket?: TicketDeLaSolicitud
  onGenerada?: (s: SolicitudBodega) => void
}

/**
 * Generar la «Solicitud de materiales informáticos a bodega» (5.4c): de parte
 * de quién, para qué equipo, para qué es y qué se pide. Desde un ticket
 * «Consumibles», el servidor y el equipo salen de él.
 */
export function NuevaSolicitudModal({ opened, ...props }: Props) {
  return opened ? <Formulario {...props} /> : null
}

function Formulario({ onClose, ticket, onGenerada }: Omit<Props, 'opened'>) {
  const contained = useContainedInput()
  const { crear } = useSolicitudMutations()
  const { data: consumibles = [] } = useConsumibles()
  const { register, control, handleSubmit, setError, formState: { errors } } = useForm<SolicitudFormData>({
    resolver: zodResolver(solicitudSchema),
    defaultValues: { desde_ticket: !!ticket, servidor_id: null, bien_informatico_id: ticket?.equipo?.id ?? null, motivo: '', items: [] },
  })
  const renglones = useFieldArray({ control, name: 'items' })
  const enLista = new Set(renglones.fields.map((r) => r.consumible_id))
  const nombre = (id: number) => consumibles.find((c) => c.id === id)?.descripcion ?? '—'

  const enviar = (v: SolicitudFormData) =>
    crear.mutateAsync({
      ticket_id: ticket?.id ?? null,
      servidor_id: ticket ? null : v.servidor_id,
      bien_informatico_id: v.bien_informatico_id,
      motivo: v.motivo.trim(),
      items: v.items,
    }).then((s) => { onClose(); if (s) onGenerada?.(s) }).catch((e) => erroresAlFormulario(e, setError, CAMPOS, null))

  return (
    <FormModal opened onClose={onClose} title={ticket ? `Solicitud a bodega para ${ticket.folio}` : 'Nueva solicitud a bodega'}
      onSubmit={handleSubmit(enviar)} submitLabel="Generar solicitud" submitting={crear.isPending} size="lg">
      <Grid>
        {ticket ? (
          <Grid.Col span={12}>
            <DetailList items={[
              { label: 'De parte de', value: ticket.solicitante },
              { label: 'Equipo', value: ticket.equipo?.texto ?? null },
            ]} />
          </Grid.Col>
        ) : (
          <>
            <Grid.Col span={{ base: 12, sm: 6 }}>
              <Controller name="servidor_id" control={control} render={({ field }) => (
                <BuscarServidorSelect label="De parte de" required value={field.value} onChange={field.onChange} error={errors.servidor_id?.message} />
              )} />
            </Grid.Col>
            <Grid.Col span={{ base: 12, sm: 6 }}>
              <Controller name="bien_informatico_id" control={control} render={({ field }) => (
                <BuscarEquipoSelect label="Equipo" placeholder="Código, serie o modelo (lo de impresión lo pide)" soloPrincipales={false}
                  value={field.value} onChange={(id) => field.onChange(id)} error={errors.bien_informatico_id?.message} />
              )} />
            </Grid.Col>
          </>
        )}
        <Grid.Col span={12}>
          <Textarea label="Para qué es" placeholder="Ej.: La impresora de la unidad se quedó sin tóner" withAsterisk autosize minRows={2}
            error={errors.motivo?.message} {...contained} {...register('motivo')} />
        </Grid.Col>
        <Grid.Col span={12}>
          <Stack gap="xs">
            <SectionHeading title="Qué se pide" />
            <AgregarSelect label="Agregar consumible" placeholder="Buscar en el catálogo"
              data={consumibles.filter((c) => !enLista.has(c.id)).map((c) => ({
                value: String(c.id), label: c.codigo_bodega ? `${c.descripcion} (${c.codigo_bodega})` : c.descripcion,
              }))}
              nothingFoundMessage="No hay más consumibles en el catálogo"
              onElegir={(v) => renglones.append({ consumible_id: Number(v), cantidad: 1 })} />
            {renglones.fields.length > 0 && (
              <SgthTable records={renglones.fields} minHeight={0} noRecordsText=""
                columns={columnasRenglones({
                  nombre,
                  onQuitar: renglones.remove,
                  cantidad: (i) => (
                    <Controller name={`items.${i}.cantidad`} control={control} render={({ field }) => (
                      <NumberInput aria-label="Cantidad" size="xs" min={1} max={999} allowDecimal={false} hideControls
                        error={errors.items?.[i]?.cantidad?.message}
                        value={field.value} onChange={(v) => field.onChange(typeof v === 'number' ? v : Number.NaN)} />
                    )} />
                  ),
                })} />
            )}
            {(errors.items?.message ?? errors.items?.root?.message) && (
              <Text size="xs" c="red">{errors.items?.message ?? errors.items?.root?.message}</Text>
            )}
          </Stack>
        </Grid.Col>
      </Grid>
    </FormModal>
  )
}
