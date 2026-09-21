'use client'

import { useEffect } from 'react'
import { Stack, TextInput,
         NumberInput, Select } from '@mantine/core'
import { FormModal } from '@/components/ui'
import { useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useCondicionMutations } from '../hooks/useCondicionMutations'
import { discapacidadSchema, type DiscapacidadFormData }
  from '../schemas/discapacidad.schema'

const TIPO_OPTIONS = [
  { value: 'fisica',       label: 'Física' },
  { value: 'sensorial',    label: 'Sensorial (Visual / Auditiva)' },
  { value: 'intelectual',  label: 'Intelectual' },
  { value: 'psicosocial',  label: 'Psicosocial o Mental' },
  { value: 'visceral',     label: 'Visceral u Orgánica' },
  { value: 'multiple',     label: 'Múltiple' },
]

interface Props {
  opened:     boolean
  onClose:    () => void
  servidorId: number
  initialValues?: {
    id:                    number
    tipo_discapacidad:     string
    porcentaje:            string | number
    numero_carnet_conadis?: string | null
  } | null
}

export function DiscapacidadModal({ opened, onClose, servidorId, initialValues }: Props) {
  const contained = useContainedInput()
  const { crearDiscapacidad, editarDiscapacidad } = useCondicionMutations(servidorId)

  const { register, control, handleSubmit, reset, formState: { errors } } =
    useForm<DiscapacidadFormData>({
      resolver: zodResolver(discapacidadSchema),
      defaultValues: {
        tipo_discapacidad:     'fisica',
        porcentaje:            1,
        numero_carnet_conadis: '',
      },
    })

  useEffect(() => {
    if (initialValues) {
      reset({
        tipo_discapacidad: initialValues.tipo_discapacidad as DiscapacidadFormData['tipo_discapacidad'],
        porcentaje:        Number(initialValues.porcentaje),
        numero_carnet_conadis: initialValues.numero_carnet_conadis ?? '',
      })
    } else {
      reset({
        tipo_discapacidad:     'fisica',
        porcentaje:            1,
        numero_carnet_conadis: '',
      })
    }
  }, [initialValues, reset])

  const handleClose = () => {
    reset()
    onClose()
  }


  const onSubmit = (values: DiscapacidadFormData) => {
    const guardado = initialValues
      ? editarDiscapacidad.mutateAsync({ id: initialValues.id, data: values })
      : crearDiscapacidad.mutateAsync(values)
    guardado.then(handleClose).catch(() => {}) // el hook ya notificó
  }

  return (
    <FormModal
      opened={opened}
      onClose={handleClose}
      title={initialValues ? 'Editar discapacidad' : 'Registrar discapacidad'}
      size="sm"
      onSubmit={handleSubmit(onSubmit)}
      submitLabel={initialValues ? 'Actualizar' : 'Registrar discapacidad'}
      submitting={crearDiscapacidad.isPending || editarDiscapacidad.isPending}
    >
      <Stack gap="sm">
        <Controller name="tipo_discapacidad" control={control}
          render={({ field }) => (
            <Select label="Tipo de discapacidad"
              data={TIPO_OPTIONS} {...contained}
              value={field.value}
              onChange={(v) => field.onChange(v ?? '')}
              error={errors.tipo_discapacidad?.message} />
          )} />
        <Controller name="porcentaje" control={control}
          render={({ field }) => (
            <NumberInput label="Porcentaje de discapacidad"
              placeholder="%" min={1} max={100} suffix="%"
              {...contained}
              value={field.value}
              onChange={(v) => field.onChange(typeof v === 'number' ? v : 1)}
              error={errors.porcentaje?.message} />
          )} />
        <TextInput label="Número de carnet CONADIS"
          placeholder="Ingrese el número de carnet"
          {...contained} {...register('numero_carnet_conadis')}
          error={errors.numero_carnet_conadis?.message} />
      </Stack>
    </FormModal>
  )
}

