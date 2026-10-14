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
 * mantenimiento preventivo en su ficha, con lo que dice la campaña.
 */
export function RealizarCampaniaModal({ filas, ...props }: Props) {
  return filas.length ? <Formulario filas={filas} {...props} /> : null
}

function Formulario({ campania: c, filas, onClose, onHecho }: Props) {
  const contained = useContainedInput()
  const { data: tecnicos = [] } = useTecnicosTic()
  const { realizar } = useCampaniaMutations(c.id)
  const hoy = hoyIso()
  const { register, control, handleSubmit, setError, formState: { errors } } = useForm<RealizarFormData>({
    resolver: zodResolver(realizarSchema),
    defaultValues: { fecha: hoy < c.fecha_inicio ? '' : hoy, tecnico_id: c.tecnico_id, observacion: '' },
  })

  const enviar = (v: RealizarFormData) =>
    realizar.mutateAsync({ items: filas.map((f) => f.item_id), fecha: v.fecha, tecnico_id: v.tecnico_id, observacion: v.observacion || null })
      .then(() => { onClose(); onHecho() })
      .catch((e) => erroresAlFormulario(e, setError, ['fecha', 'tecnico_id', 'observacion'], 'No se pudo registrar el mantenimiento'))

  return (
    <FormModal opened onClose={onClose} title={`Marcar como ${filas.length === 1 ? 'hecho' : 'hechos'}`}
      onSubmit={handleSubmit(enviar)} submitLabel="Registrar mantenimiento" submitting={realizar.isPending}>
      <Grid>
        <Grid.Col span={12}>
          <Text size="sm">
            Se registrará un mantenimiento preventivo en la ficha de{' '}
            <b>{filas.length === 1 ? filas[0].codigo_institucional : plural(filas.length, 'equipo', 'equipos')}</b>:
            {' '}«{c.descripcion}».
          </Text>
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <Controller name="fecha" control={control} render={({ field }) => (
            <DatePickerInput label="Fecha" placeholder="Seleccionar" valueFormat="DD/MM/YYYY" withAsterisk
              minDate={c.fecha_inicio} maxDate={hoy}
              error={errors.fecha?.message} {...contained}
              value={toDateValue(field.value)} onChange={(d) => field.onChange(fromDateValueOrNull(d) ?? '')} />
          )} />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <Controller name="tecnico_id" control={control} render={({ field }) => (
            <Select label="Lo hizo" placeholder="El técnico de la campaña" searchable {...contained}
              error={errors.tecnico_id?.message}
              data={tecnicos.map((t) => ({ value: String(t.id), label: t.nombre }))}
              value={field.value ? String(field.value) : null} onChange={(v) => field.onChange(v ? Number(v) : null)} />
          )} />
        </Grid.Col>
        <Grid.Col span={12}>
          <Textarea label="Observación" placeholder="Opcional. Ej.: Se cambió la pasta térmica." autosize minRows={2}
            description="Se agrega a la descripción del mantenimiento."
            error={errors.observacion?.message} {...contained} {...register('observacion')} />
        </Grid.Col>
      </Grid>
    </FormModal>
  )
}
