'use client'

import { Grid, NumberInput, Stack, Text } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { Controller, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal, SgthTable } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { fromDateValueOrNull, hoyIso, toDateValue } from '@/lib/fecha'
import { useSolicitudMutations } from '../hooks/useSolicitudes'
import { despachoSchema, type DespachoFormData } from '../schemas/solicitud.schema'
import type { SolicitudBodega } from '../services/solicitudesService'
import { columnasDespacho } from './solicitudes.columns'

const CAMPOS = ['fecha', 'items']

interface Props {
  solicitud: SolicitudBodega | null
  onClose: () => void
}

/**
 * Anotar lo que despachó Bodega (5.4c): cada cantidad es la entrega al
 * servidor y a su equipo; con ticket, el ticket se cierra. Lo que no despachó
 * va en cero.
 */
export function DespacharSolicitudModal({ solicitud, onClose }: Props) {
  return solicitud ? <Formulario solicitud={solicitud} onClose={onClose} /> : null
}

function Formulario({ solicitud: s, onClose }: { solicitud: SolicitudBodega; onClose: () => void }) {
  const contained = useContainedInput()
  const { despachar } = useSolicitudMutations()
  const { control, handleSubmit, setError, formState: { errors } } = useForm<DespachoFormData>({
    resolver: zodResolver(despachoSchema),
    defaultValues: { fecha: hoyIso(), items: s.items.map((i) => ({ item_id: i.id, cantidad_despachada: i.cantidad_solicitada })) },
  })

  const enviar = (v: DespachoFormData) =>
    despachar.mutateAsync({ id: s.id, data: v }).then(onClose).catch((e) => erroresAlFormulario(e, setError, CAMPOS, null))

  return (
    <FormModal opened onClose={onClose} title={`Despacho de ${s.codigo}`} onSubmit={handleSubmit(enviar)}
      submitLabel="Anotar despacho" submitting={despachar.isPending} size="lg">
      <Grid>
        <Grid.Col span={12}>
          <Text size="sm">
            Lo despachado se entrega a <b>{s.servidor}</b>{s.equipo ? <> para el equipo <b>{s.equipo.codigo_institucional}</b></> : null}
            {s.ticket ? <> y cierra el ticket <b>{s.ticket}</b></> : null}. Lo que Bodega no despachó va en cero.
          </Text>
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <Controller name="fecha" control={control} render={({ field }) => (
            <DatePickerInput label="Fecha del despacho" valueFormat="DD/MM/YYYY" withAsterisk minDate={s.fecha} maxDate={hoyIso()}
              error={errors.fecha?.message} {...contained}
              value={toDateValue(field.value)} onChange={(d) => field.onChange(fromDateValueOrNull(d) ?? '')} />
          )} />
        </Grid.Col>
        <Grid.Col span={12}>
          <Stack gap="xs">
            <SgthTable records={s.items} minHeight={0} noRecordsText=""
              columns={columnasDespacho((i, maximo) => (
                <Controller name={`items.${i}.cantidad_despachada`} control={control} render={({ field }) => (
                  <NumberInput aria-label="Cantidad despachada" size="xs" min={0} max={maximo} allowDecimal={false} clampBehavior="strict"
                    error={errors.items?.[i]?.cantidad_despachada?.message}
                    value={field.value} onChange={(v) => field.onChange(typeof v === 'number' ? v : 0)} />
                )} />
              ))} />
            {(errors.items?.message ?? errors.items?.root?.message) && (
              <Text size="xs" c="red">{errors.items?.message ?? errors.items?.root?.message}</Text>
            )}
          </Stack>
        </Grid.Col>
      </Grid>
    </FormModal>
  )
}
