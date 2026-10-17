'use client'

import { Grid, Select, Text, Textarea } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { Controller, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { fromDateValueOrNull, hoyIso, toDateValue } from '@/lib/fecha'
import { plural } from '@/lib/plural'
import { useCampaniaMutations } from '../hooks/useCampanias'
import { useTecnicosTic } from '../hooks/useMantenimientos'
import { realizarSchema, type RealizarFormData } from '../schemas/campania.schema'
import type { DetalleCampania, FilaCampania } from '../services/campaniasService'

interface Props {
  campania: DetalleCampania
  /** Los equipos elegidos; vacío, el modal no se abre. */
  filas: FilaCampania[]
  onClose: () => void
  onHecho: () => void
}

/**
 * Marcar como hechos los equipos elegidos: a cada uno se le registra un
 * mantenimiento preventivo en su ficha, con lo que dice la campaña. En la del
 * proveedor no hay técnico que elegir: lo hizo la empresa, contra su contrato,
 * y no después de que este venció.
 */
export function RealizarCampaniaModal({ filas, ...props }: Props) {
  return filas.length ? <Formulario filas={filas} {...props} /> : null
}

function Formulario({ campania: c, filas, onClose, onHecho }: Props) {
  const contained = useContainedInput()
  const { data: tecnicos = [] } = useTecnicosTic()
  const { realizar } = useCampaniaMutations(c.id)
  const hoy = hoyIso()
  const delProveedor = c.realizado_por === 'proveedor'
  // Lo que el proveedor haga después del fin de su contrato ya no cuenta contra él.
  const tope = c.contrato_fin && c.contrato_fin < hoy ? c.contrato_fin : hoy
  const quien = delProveedor
    ? <> del proveedor{c.proveedor ? ` (${c.proveedor})` : ''}, contado contra el contrato <b>{c.contrato}</b>,</>
    : null
  const { register, control, handleSubmit, setError, formState: { errors } } = useForm<RealizarFormData>({
    resolver: zodResolver(realizarSchema),
    defaultValues: { fecha: tope < c.fecha_inicio ? '' : tope, tecnico_id: delProveedor ? null : c.tecnico_id, observacion: '' },
  })

  const enviar = (v: RealizarFormData) =>
    realizar.mutateAsync({
      items: filas.map((f) => f.item_id), fecha: v.fecha, tecnico_id: delProveedor ? null : v.tecnico_id, observacion: v.observacion || null,
    })
      .then(() => { onClose(); onHecho() })
      .catch((e) => erroresAlFormulario(e, setError, ['fecha', 'tecnico_id', 'observacion'], 'No se pudo registrar el mantenimiento'))

  return (
    <FormModal opened onClose={onClose} title={`Marcar como ${filas.length === 1 ? 'hecho' : 'hechos'}`}
      onSubmit={handleSubmit(enviar)} submitLabel="Registrar mantenimiento" submitting={realizar.isPending}>
      <Grid>
        <Grid.Col span={12}>
          <Text size="sm">
            Se registrará un mantenimiento preventivo{quien} en la ficha de{' '}
            <b>{filas.length === 1 ? filas[0].codigo_institucional : plural(filas.length, 'equipo', 'equipos')}</b>:
            {' '}«{c.descripcion}».
          </Text>
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <Controller name="fecha" control={control} render={({ field }) => (
            <DatePickerInput label="Fecha" placeholder="Seleccionar" valueFormat="DD/MM/YYYY" withAsterisk
              minDate={c.fecha_inicio} maxDate={tope}
              error={errors.fecha?.message} {...contained}
              value={toDateValue(field.value)} onChange={(d) => field.onChange(fromDateValueOrNull(d) ?? '')} />
          )} />
        </Grid.Col>
        {!delProveedor && (
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <Controller name="tecnico_id" control={control} render={({ field }) => (
              <Select label="Lo hizo" placeholder="El técnico de la campaña" searchable {...contained}
                error={errors.tecnico_id?.message}
                data={tecnicos.map((t) => ({ value: String(t.id), label: t.nombre }))}
                value={field.value ? String(field.value) : null} onChange={(v) => field.onChange(v ? Number(v) : null)} />
            )} />
          </Grid.Col>
        )}
        <Grid.Col span={12}>
          <Textarea label="Observación" placeholder="Opcional. Ej.: Se cambió la pasta térmica." autosize minRows={2}
            description="Se agrega a la descripción del mantenimiento."
            error={errors.observacion?.message} {...contained} {...register('observacion')} />
        </Grid.Col>
      </Grid>
    </FormModal>
  )
}
