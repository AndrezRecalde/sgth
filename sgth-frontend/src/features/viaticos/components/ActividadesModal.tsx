'use client'

import { useEffect } from 'react'
import { Alert, Button, Stack } from '@mantine/core'
import { IconInfoCircle, IconPlus } from '@tabler/icons-react'
import { useFieldArray, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal } from '@/components/ui'
import { useViaticoMutations } from '../hooks/useViaticoMutations'
import { rangoDelViaje } from '../utils/rangoViaje'
import {
  actividadesSchema,
  type ActividadData,
  type ActividadesFormData,
} from '../schemas/liquidacion.schema'
import { ActividadItemForm } from './ActividadItemForm'
import type { Viatico } from '@/types/api'

const ACTIVIDAD_VACIA: ActividadData = {
  fecha:       '',
  hora_inicio: '08:00',
  hora_fin:    '17:00',
  descripcion: '',
  lugar:       '',
}

interface Props {
  opened:        boolean
  onClose:       () => void
  viatico:       Viatico
  valorInicial?: ActividadData[]
}

/** El informe de actividades: qué se hizo cada día de la comisión. */
export function ActividadesModal({ opened, onClose, viatico, valorInicial = [] }: Props) {
  const { guardarActividades } = useViaticoMutations()
  const rango = rangoDelViaje(viatico)

  const { control, handleSubmit, register, reset, formState: { errors } } =
    useForm<ActividadesFormData>({
      resolver: zodResolver(actividadesSchema),
      defaultValues: { actividades: valorInicial.length > 0 ? valorInicial : [ACTIVIDAD_VACIA] },
    })

  const { fields, append, remove } = useFieldArray({ control, name: 'actividades' })

  // El modal vive montado: al abrirlo se parte de lo guardado.
  useEffect(() => {
    if (opened) reset({ actividades: valorInicial.length > 0 ? valorInicial : [ACTIVIDAD_VACIA] })
  }, [opened, valorInicial, reset])

  const onSubmit = (values: ActividadesFormData) =>
    guardarActividades.mutate(
      { viaticoId: viatico.id, actividades: values.actividades },
      { onSuccess: onClose },
    )

  return (
    <FormModal
      opened={opened}
      onClose={onClose}
      title="Informe de actividades"
      size="xl"
      closeOnClickOutside={false}
      onSubmit={handleSubmit(onSubmit)}
      submitLabel="Guardar actividades"
      submitting={guardarActividades.isPending}
    >
      <Stack gap="md">
        <Alert color="ocean" variant="light" icon={<IconInfoCircle size={16} />}>
          Registre las actividades de los días del viaje: del <strong>{rango.desde}</strong> al{' '}
          <strong>{rango.hasta}</strong>.
        </Alert>

        {fields.map((field, i) => (
          <ActividadItemForm
            key={field.id}
            index={i}
            control={control}
            register={register}
            errors={errors}
            minFecha={rango.min}
            maxFecha={rango.max}
            onEliminar={fields.length > 1 ? () => remove(i) : undefined}
          />
        ))}

        <Button variant="light" leftSection={<IconPlus size={16} />} onClick={() => append({ ...ACTIVIDAD_VACIA })}>
          Agregar actividad
        </Button>
      </Stack>
    </FormModal>
  )
}
