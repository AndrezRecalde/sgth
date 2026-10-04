'use client'

import { Stack, TextInput, NumberInput, Select } from '@mantine/core'
import { FormModal } from '@/components/ui'
import { useForm, useWatch, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import {
  TIPOS_DISCAPACIDAD, discapacidadCargaSchema, discapacidadSchema,
  type DiscapacidadFormData,
} from '../schemas/discapacidad.schema'
import {
  PORCENTAJE_MINIMO_DISCAPACIDAD, TIPO_DISCAPACIDAD_OPTIONS, gradoDiscapacidad,
} from '../utils/discapacidad'

interface Props {
  opened:     boolean
  onClose:    () => void
  /** Guarda el registro: con `id`, lo edita. El hook que lo hace ya notifica. */
  onGuardar:  (data: DiscapacidadFormData, id?: number) => Promise<unknown>
  guardando:  boolean
  /** En el servidor el carné CONADIS es obligatorio; en un familiar, no. */
  carnetObligatorio?: boolean
  /** El padre monta el modal con `key` por registro: no hace falta `reset`. */
  initialValues?: {
    id:                    number
    tipo_discapacidad?:    string | null
    porcentaje?:           string | number | null
    numero_carnet_conadis?: string | null
  } | null
}

/**
 * Registrar o editar una discapacidad, del servidor o de una carga familiar.
 * Antes eran dos modales: el del familiar no permitía editar, guardaba 1 % al
 * vaciar el porcentaje y escribía su esquema dentro del componente.
 */
export function DiscapacidadModal({
  opened, onClose, onGuardar, guardando, carnetObligatorio = false, initialValues,
}: Props) {
  const contained = useContainedInput()

  const { register, control, handleSubmit, reset, setError, formState: { errors } } =
    useForm<DiscapacidadFormData>({
      resolver: zodResolver(carnetObligatorio ? discapacidadSchema : discapacidadCargaSchema),
      defaultValues: {
        tipo_discapacidad: TIPOS_DISCAPACIDAD
          .find((t) => t === initialValues?.tipo_discapacidad),
        porcentaje: initialValues?.porcentaje != null
          ? Number(initialValues.porcentaje) : undefined,
        numero_carnet_conadis: initialValues?.numero_carnet_conadis ?? '',
      },
    })

  const grado = gradoDiscapacidad(useWatch({ control, name: 'porcentaje' }))

  const handleClose = () => {
    reset()
    onClose()
  }

  const onSubmit = (values: DiscapacidadFormData) => {
    const data = { ...values, numero_carnet_conadis: values.numero_carnet_conadis || null }
    onGuardar(data, initialValues?.id).then(handleClose).catch((e) => erroresAlFormulario(
      e, setError, Object.keys(discapacidadCargaSchema.shape), 'No se pudo guardar la discapacidad',
    ))
  }

  return (
    <FormModal
      opened={opened}
      onClose={handleClose}
      title={initialValues ? 'Editar discapacidad' : 'Registrar discapacidad'}
      size="sm"
      onSubmit={handleSubmit(onSubmit)}
      submitLabel={initialValues ? 'Guardar cambios' : 'Registrar discapacidad'}
      submitting={guardando}
    >
      <Stack gap="sm">
        <Controller name="tipo_discapacidad" control={control}
          render={({ field }) => (
            <Select label="Tipo de discapacidad"
              placeholder="Seleccione"
              data={TIPO_DISCAPACIDAD_OPTIONS} {...contained}
              allowDeselect={false}
              value={field.value ?? null}
              onChange={(v) => field.onChange(v ?? undefined)}
              error={errors.tipo_discapacidad?.message} />
          )} />
        <Controller name="porcentaje" control={control}
          render={({ field }) => (
            <NumberInput label="Porcentaje de discapacidad"
              placeholder="Ej: 45"
              description={grado ? `Grado: ${grado}` : undefined}
              min={PORCENTAJE_MINIMO_DISCAPACIDAD} max={100} suffix="%"
              // Sin recortar al salir del campo: un 3 se volvía 5 sin avisar.
              // Fuera de rango lo dice el esquema.
              clampBehavior="none"
              {...contained}
              value={field.value ?? ''}
              // Vacío es vacío: antes volvía a 1 % y se guardaba sin avisar.
              onChange={(v) => field.onChange(typeof v === 'number' ? v : undefined)}
              error={errors.porcentaje?.message} />
          )} />
        <TextInput label="Número de carnet CONADIS"
          placeholder={carnetObligatorio ? 'Ej: 13.456' : 'Si lo tiene a mano'}
          {...contained} {...register('numero_carnet_conadis')}
          error={errors.numero_carnet_conadis?.message} />
      </Stack>
    </FormModal>
  )
}
