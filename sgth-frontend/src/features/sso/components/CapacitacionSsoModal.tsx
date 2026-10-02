'use client'

import { useEffect } from 'react'
import { Group, NumberInput, Stack, Switch, TextInput } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { useForm, Controller, type Resolver } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useCapacitacionMutations } from '../hooks/useCapacitaciones'
import { capacitacionSchema, type CapacitacionFormData } from '../schemas/capacitacion.schema'
import { toDateValue, fromDateValue } from '@/lib/fecha'
import { erroresDeCampo } from '@/lib/erroresDeCampo'
import type { CapacitacionSso } from '../services/tipos'

interface Props {
  opened: boolean
  onClose: () => void
  capacitacion?: CapacitacionSso | null
}

export function CapacitacionSsoModal({ opened, onClose, capacitacion }: Props) {
  const contained = useContainedInput()
  const { crear, editar } = useCapacitacionMutations()
  const editando = !!capacitacion

  const valoresDe = (registro?: CapacitacionSso | null): CapacitacionFormData => ({
    tema: registro?.tema ?? '',
    fecha: registro?.fecha ?? '',
    // El recurso devuelve la duración como cadena —columna `decimal` sin cast
    // en el modelo, lo arregla #248—, y el formulario trabaja con el número
    // que la petición espera.
    duracion_horas: registro ? Number(registro.duracion_horas) : 1,
    instructor: registro?.instructor ?? '',
    lugar: registro?.lugar ?? '',
    estado: registro?.estado ?? true,
  })

  const {
    register, control, handleSubmit, reset, setError,
    formState: { errors },
  } = useForm<CapacitacionFormData>({
    resolver: zodResolver(capacitacionSchema) as Resolver<CapacitacionFormData>,
    defaultValues: valoresDe(capacitacion),
  })

  useEffect(() => {
    reset(valoresDe(capacitacion))
  }, [capacitacion, reset])

  const cerrar = () => {
    reset()
    onClose()
  }

  const guardar = (valores: CapacitacionFormData) => {
    const mutacion = editando
      ? editar.mutateAsync({ id: capacitacion!.id, data: valores })
      : crear.mutateAsync(valores)

    mutacion.then(cerrar).catch((error) => {
      const campos = erroresDeCampo(error)
      if (!campos) return // el hook ya lo notificó
      for (const [campo, mensaje] of Object.entries(campos)) {
        setError(campo as keyof CapacitacionFormData, { message: mensaje })
      }
    })
  }

  return (
    <FormModal
      opened={opened}
      onClose={cerrar}
      title={editando ? 'Editar capacitación' : 'Nueva capacitación en SSO'}
      size="lg"
      onSubmit={handleSubmit(guardar)}
      submitLabel={editando ? 'Actualizar' : 'Registrar capacitación'}
      submitting={crear.isPending || editar.isPending}
    >
      <Stack gap="sm">
        <TextInput
          label="Tema"
          placeholder="Ej: Uso correcto del equipo de protección personal"
          required
          {...contained}
          {...register('tema')}
          error={errors.tema?.message}
        />

        <Group grow>
          <Controller
            name="fecha"
            control={control}
            render={({ field }) => (
              <DatePickerInput
                label="Fecha"
                placeholder="Seleccionar"
                valueFormat="DD/MM/YYYY"
                required
                {...contained}
                value={toDateValue(field.value)}
                onChange={(d) => field.onChange(fromDateValue(d ?? null))}
                error={errors.fecha?.message}
              />
            )}
          />
          <Controller
            name="duracion_horas"
            control={control}
            render={({ field }) => (
              <NumberInput
                label="Duración (horas)"
                placeholder="1.5"
                required
                // Medias horas, no enteros: una charla de hora y media es lo
                // normal y el backend valida `min:0.5`. De esta columna sale
                // el total de horas del período.
                min={0.5}
                step={0.5}
                decimalScale={2}
                {...contained}
                value={field.value}
                onChange={(v) => field.onChange(typeof v === 'number' ? v : Number(v) || 0)}
                error={errors.duracion_horas?.message}
              />
            )}
          />
        </Group>

        <Group grow>
          <TextInput
            label="Instructor"
            placeholder="Quién la dictó"
            required
            {...contained}
            {...register('instructor')}
            error={errors.instructor?.message}
          />
          <TextInput
            label="Lugar"
            placeholder="Dónde se dictó (opcional)"
            {...contained}
            {...register('lugar')}
            error={errors.lugar?.message}
          />
        </Group>

        <Controller
          name="estado"
          control={control}
          render={({ field }) => (
            // `estado` no cambia ningún cálculo: `calcularIndicadoresProactivos`
            // cuenta todas las capacitaciones del período sin mirarlo. Lo único
            // que hace hoy es filtrar el listado, y eso es lo que dice.
            <Switch
              label="Activa"
              description="Solo filtra este listado: los índices del período cuentan todas las registradas."
              checked={field.value}
              onChange={(e) => field.onChange(e.currentTarget.checked)}
            />
          )}
        />
      </Stack>
    </FormModal>
  )
}
