'use client'

import { useEffect }           from 'react'
import {
  Stack, Button, Alert, Text, } from '@mantine/core'
import { FormModal } from '@/components/ui'
import {
  IconPlus, IconAlertCircle, } from '@tabler/icons-react'
import { useForm, useFieldArray } from 'react-hook-form'
import { zodResolver }           from '@hookform/resolvers/zod'
import { z }                     from 'zod/v4'
import { useViaticoMutations }   from '../hooks/useViaticoMutations'
import { ActividadItemForm }     from './ActividadItemForm'
import type { Viatico }          from '@/types/api'

export interface ActividadData {
  fecha:       string
  hora_inicio: string
  hora_fin:    string
  descripcion: string
  lugar:       string
}

const actividadItemSchema = z.object({
  fecha:       z.string().min(1, 'Seleccione la fecha'),
  hora_inicio: z.string().min(1, 'Requerido'),
  hora_fin:    z.string().min(1, 'Requerido'),
  descripcion: z.string().min(5, 'Mínimo 5 caracteres'),
  lugar:       z.string().min(1, 'Requerido'),
})

const schema = z.object({
  actividades: z.array(actividadItemSchema)
    .min(1, 'Agregue al menos una actividad'),
})

type FormData = z.infer<typeof schema>

const ACTIVIDAD_VACIA: ActividadData = {
  fecha:       '',
  hora_inicio: '08:00',
  hora_fin:    '17:00',
  descripcion: '',
  lugar:       '',
}

interface Props {
  opened:         boolean
  onClose:        () => void
  viatico:        Viatico
  onGuardar?:     (actividades: ActividadData[]) => void
  valorInicial?:  ActividadData[]
}

/**
 * La salida y el regreso son fecha y hora: van en la hora de Ecuador. Con
 * `timeZone: 'UTC'` un regreso a las 21:00 del 11 se leía como el 12.
 */
function formatFechaRango(f?: string | null): string {
  if (!f) return '—'
  return new Date(f).toLocaleDateString('es-EC', {
    day: '2-digit', month: 'long', year: 'numeric',
  })
}

export function ActividadesModal({
  opened, onClose, viatico,
  onGuardar, valorInicial = [],
}: Props) {
  const { guardarActividades } = useViaticoMutations()

  const minFecha = viatico.datetime_salida
    ? (() => {
        const d = new Date(viatico.datetime_salida as string)
        d.setHours(0, 0, 0, 0)
        return d
      })()
    : undefined

  const maxFecha = viatico.datetime_llegada
    ? (() => {
        const d = new Date(viatico.datetime_llegada as string)
        d.setHours(23, 59, 59, 999)
        return d
      })()
    : undefined

  const {
    control, handleSubmit, register, reset,
    formState: { errors },
  } = useForm<FormData>({
    resolver: zodResolver(schema),
    defaultValues: {
      actividades: valorInicial.length > 0
        ? valorInicial : [ACTIVIDAD_VACIA],
    },
  })

  const { fields, append, remove } = useFieldArray({
    control, name: 'actividades',
  })

  useEffect(() => {
    if (opened) {
      reset({
        actividades: valorInicial.length > 0
          ? valorInicial : [ACTIVIDAD_VACIA],
      })
    }
  }, [opened, valorInicial, reset])

  const onSubmit = async (values: FormData) => {
    try {
      await guardarActividades.mutateAsync({
        viaticoId:   viatico.id,
        actividades: values.actividades,
      })
      onGuardar?.(values.actividades)
      onClose()
    } catch {
      // El hook de mutación ya notifica el error.
    }
  }

  return (
    <FormModal
      opened={opened}
      onClose={onClose}
      title="Informe de actividades"
      size="xl"
      closeOnClickOutside={false}
      onSubmit={handleSubmit(onSubmit)}
      submitLabel={`Guardar actividades (${fields.length})`}
      submitting={guardarActividades.isPending}
    >
      <Stack gap="md">
        <Alert
          icon={<IconAlertCircle size={14} />}
          color="ocean"
          variant="light"
        >
          <Text size="xs" fw={500}>Período del viático</Text>
          <Text size="xs" mt={2}>
            Solo puede registrar actividades entre el{' '}
            <strong>
              {formatFechaRango(viatico.datetime_salida as string)}
            </strong>
            {' '}y el{' '}
            <strong>
              {formatFechaRango(viatico.datetime_llegada as string)}
            </strong>.
          </Text>
        </Alert>

        <Stack gap="sm">
          {fields.map((field, i) => (
            <ActividadItemForm
              key={field.id}
              index={i}
              control={control}
              register={register}
              errors={errors}
              minFecha={minFecha}
              maxFecha={maxFecha}
              onEliminar={() => remove(i)}
              puedeEliminar={fields.length > 1}
            />
          ))}
        </Stack>

        <Button
          variant="light"
          size="sm"
          leftSection={<IconPlus size={14} />}
          onClick={() => append({ ...ACTIVIDAD_VACIA })}
        >
          Agregar otro día de actividades
        </Button>
      </Stack>
    </FormModal>
  )
}
