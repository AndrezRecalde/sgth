'use client'

import { Grid, NumberInput, Select, Stack, Text, TextInput, Textarea } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { Controller, useFieldArray, useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal, SectionHeading, SgthTable } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { fromDateValueOrNull, hoyIso, toDateValue } from '@/lib/fecha'
import { DOCUMENTOS_INGRESO } from '../constants/consumibles'
import { useConsumibles, useMovimientoMutations } from '../hooks/useConsumibles'
import { ingresoSchema, type IngresoFormData } from '../schemas/movimiento.schema'
import { AgregarSelect } from './AgregarSelect'
import { columnasRenglones } from './renglones.columns'

const CAMPOS = ['fecha', 'tipo_documento', 'numero_documento', 'fecha_compra', 'observacion', 'items']

interface Props {
  opened: boolean
  onClose: () => void
  /** Desde el detalle de un consumible, ya va en la lista. */
  consumibleId?: number
}

/**
 * Registrar lo que TIC recibe de bodega: un documento (egreso de bodega, acta
 * o memorando) con uno o varios consumibles [P8]. Solo lo que guarda TIC: lo
 * que despacha Bodega no lleva saldo aquí [P4].
 */
export function IngresoModal({ opened, ...props }: Props) {
  return opened ? <Formulario {...props} /> : null
}

function Formulario({ onClose, consumibleId }: Omit<Props, 'opened'>) {
  const contained = useContainedInput()
  const { ingreso } = useMovimientoMutations()
  const { data: consumibles = [] } = useConsumibles({ guarda_tic: true })
  const { register, control, handleSubmit, setError, formState: { errors } } = useForm<IngresoFormData>({
    resolver: zodResolver(ingresoSchema),
    defaultValues: {
      fecha: hoyIso(), numero_documento: '', fecha_compra: '', observacion: '',
      items: consumibleId ? [{ consumible_id: consumibleId, cantidad: 1 }] : [],
    },
  })
  const renglones = useFieldArray({ control, name: 'items' })
  const fecha = useWatch({ control, name: 'fecha' })
  const enLista = new Set(renglones.fields.map((r) => r.consumible_id))
  const nombre = (id: number) => consumibles.find((c) => c.id === id)?.descripcion ?? '—'

  const agregar = (v: string) => renglones.append({ consumible_id: Number(v), cantidad: 1 })

  const enviar = (v: IngresoFormData) =>
    ingreso.mutateAsync({
      ...v,
      numero_documento: v.numero_documento.trim(),
      fecha_compra: v.fecha_compra || null,
      observacion: v.observacion.trim() || null,
    }).then(onClose).catch((e) => erroresAlFormulario(e, setError, CAMPOS, null))

  return (
    <FormModal opened onClose={onClose} title="Registrar ingreso" onSubmit={handleSubmit(enviar)}
      submitLabel="Registrar ingreso" submitting={ingreso.isPending} size="lg">
      <Grid>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <Controller name="tipo_documento" control={control} render={({ field }) => (
            <Select label="Documento" placeholder="Seleccionar" withAsterisk data={DOCUMENTOS_INGRESO} {...contained}
              error={errors.tipo_documento?.message} value={field.value ?? null} onChange={(v) => field.onChange(v ?? undefined)} />
          )} />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <TextInput label="Número del documento" placeholder="Ej.: EB-2026-0412" withAsterisk
            error={errors.numero_documento?.message} {...contained} {...register('numero_documento')} />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <Controller name="fecha" control={control} render={({ field }) => (
            <DatePickerInput label="Fecha del ingreso" valueFormat="DD/MM/YYYY" withAsterisk maxDate={hoyIso()}
              error={errors.fecha?.message} {...contained}
              value={toDateValue(field.value)} onChange={(d) => field.onChange(fromDateValueOrNull(d) ?? '')} />
          )} />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <Controller name="fecha_compra" control={control} render={({ field }) => (
            <DatePickerInput label="Fecha de compra" placeholder="Opcional" valueFormat="DD/MM/YYYY" clearable maxDate={fecha || hoyIso()}
              error={errors.fecha_compra?.message} {...contained}
              value={toDateValue(field.value)} onChange={(d) => field.onChange(fromDateValueOrNull(d) ?? '')} />
          )} />
        </Grid.Col>
        <Grid.Col span={12}>
          <Stack gap="xs">
            <SectionHeading title="Consumibles" />
            <AgregarSelect label="Agregar consumible" placeholder="Buscar en lo que guarda TIC"
              data={consumibles.filter((c) => !enLista.has(c.id)).map((c) => ({ value: String(c.id), label: c.descripcion }))}
              nothingFoundMessage="No hay más consumibles que guarde TIC" onElegir={agregar} />
            {renglones.fields.length > 0 && (
              <SgthTable records={renglones.fields} minHeight={0} noRecordsText=""
                columns={columnasRenglones({
                  nombre,
                  onQuitar: renglones.remove,
                  cantidad: (i) => (
                    <Controller name={`items.${i}.cantidad`} control={control} render={({ field }) => (
                      <NumberInput aria-label="Cantidad" size="xs" min={1} max={9999} allowDecimal={false} hideControls
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
        <Grid.Col span={12}>
          <Textarea label="Observación" placeholder="Opcional" autosize minRows={2}
            error={errors.observacion?.message} {...contained} {...register('observacion')} />
        </Grid.Col>
      </Grid>
    </FormModal>
  )
}
