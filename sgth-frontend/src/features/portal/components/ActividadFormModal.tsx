'use client'

import { Grid, Select, TextInput, Textarea } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { Controller, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { fromDateValueOrNull, hoyIso, toDateValue } from '@/lib/fecha'
import { CATEGORIAS_ACTIVIDAD } from '../constants/actividades'
import { useGuardarActividad } from '../hooks/useMisActividades'
import { actividadSchema, type ActividadFormData } from '../schemas/actividad.schema'
import type { ActividadLaboral } from '../services/misActividadesService'

const CAMPOS = Object.keys(actividadSchema.shape)

interface Props {
  opened: boolean
  onClose: () => void
  /** La que se corrige; sin ella, se anota una nueva. */
  actividad?: ActividadLaboral | null
}

/**
 * Anotar o corregir una actividad. Solo del mes en curso y hasta hoy: el mes
 * cerrado queda como se informó.
 */
export function ActividadFormModal({ opened, onClose, actividad }: Props) {
  // Montado solo abierto: cada apertura arranca con los valores de la que toca.
  return opened ? <Formulario onClose={onClose} actividad={actividad ?? null} /> : null
}

function Formulario({ onClose, actividad }: { onClose: () => void; actividad: ActividadLaboral | null }) {
  const contained = useContainedInput()
  const guardar = useGuardarActividad()
  const hoy = hoyIso()

  const { control, register, handleSubmit, setError, formState: { errors } } = useForm<ActividadFormData>({
    resolver: zodResolver(actividadSchema),
    defaultValues: actividad
      ? {
          fecha: actividad.fecha,
          categoria: actividad.categoria,
          descripcion: actividad.descripcion,
          producto_entregable: actividad.producto_entregable ?? '',
        }
      : { fecha: hoy, descripcion: '', producto_entregable: '' },
  })

  const enviar = (v: ActividadFormData) =>
    guardar.mutateAsync({
      id: actividad?.id,
      data: { ...v, descripcion: v.descripcion.trim(), producto_entregable: v.producto_entregable.trim() || null },
    })
      .then(onClose)
      .catch((e) => erroresAlFormulario(e, setError, CAMPOS, 'No se pudo guardar la actividad'))

  return (
    <FormModal
      opened
      onClose={onClose}
      title={actividad ? 'Corregir actividad' : 'Anotar actividad'}
      onSubmit={handleSubmit(enviar)}
      submitLabel={actividad ? 'Guardar cambios' : 'Anotar actividad'}
      submitting={guardar.isPending}
      size="lg"
    >
      <Grid>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <Controller
            name="fecha"
            control={control}
            render={({ field }) => (
              <DatePickerInput
                label="Fecha"
                placeholder="Seleccionar fecha"
                valueFormat="DD/MM/YYYY"
                withAsterisk
                minDate={`${hoy.slice(0, 7)}-01`}
                maxDate={hoy}
                error={errors.fecha?.message}
                {...contained}
                value={toDateValue(field.value)}
                onChange={(d) => field.onChange(fromDateValueOrNull(d) ?? '')}
              />
            )}
          />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <Controller
            name="categoria"
            control={control}
            render={({ field }) => (
              <Select
                label="Categoría"
                placeholder="Seleccione la categoría"
                data={CATEGORIAS_ACTIVIDAD}
                withAsterisk
                allowDeselect={false}
                error={errors.categoria?.message}
                {...contained}
                value={field.value ?? null}
                onChange={(v) => field.onChange(v)}
              />
            )}
          />
        </Grid.Col>

        <Grid.Col span={12}>
          <Textarea
            label="Actividad realizada"
            placeholder="Ej.: Revisión de los expedientes de contratación de la obra vial Muisne–San Francisco"
            withAsterisk
            autosize
            minRows={3}
            maxRows={8}
            error={errors.descripcion?.message}
            {...contained}
            {...register('descripcion')}
          />
        </Grid.Col>

        <Grid.Col span={12}>
          <TextInput
            label="Producto o entregable"
            placeholder="Opcional. Ej.: Informe técnico n.º 45"
            error={errors.producto_entregable?.message}
            {...contained}
            {...register('producto_entregable')}
          />
        </Grid.Col>
      </Grid>
    </FormModal>
  )
}
