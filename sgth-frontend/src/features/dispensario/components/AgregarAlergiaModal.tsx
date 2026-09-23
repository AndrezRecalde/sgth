'use client'

import {
  Stack, Select, TextInput,
  Textarea,
} from '@mantine/core'
import { FormModal } from '@/components/ui'
import { useForm, Controller, type DefaultValues } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useAgregarAlergia } from '../hooks/useHistoriaClinica'
import {
  alergiaSchema,
  SEVERIDAD_OPTIONS,
  TIPO_ALERGIA_OPTIONS,
  type AlergiaFormData,
} from '../schemas/historiaClinica.schema'

interface Props {
  opened:      boolean
  onClose:     () => void
  historiaId:  number
  agendaId:    number
}

/**
 * Ni el tipo ni la severidad tienen un valor por defecto razonable: los elige
 * quien registra. Van omitidos, que es como el repositorio resuelve un campo
 * de enumeración sin valor inicial —darles la cadena vacía obligaría a mentir
 * sobre el tipo (ver regla 09)—.
 */
const VALORES_INICIALES: DefaultValues<AlergiaFormData> = {
  descripcion: '',
  observacion: '',
}

export function AgregarAlergiaModal({
  opened, onClose, historiaId, agendaId,
}: Props) {
  const contained = useContainedInput()
  const agregar   = useAgregarAlergia(historiaId, agendaId)

  const {
    control, register, handleSubmit, reset,
    formState: { errors },
  } = useForm<AlergiaFormData>({
    resolver: zodResolver(alergiaSchema),
    defaultValues: VALORES_INICIALES,
  })

  const cerrar = () => {
    reset(VALORES_INICIALES)
    onClose()
  }

  const onSubmit = (values: AlergiaFormData) => {
    agregar.mutate(
      {
        tipo:        values.tipo,
        descripcion: values.descripcion,
        severidad:   values.severidad,
        observacion: values.observacion || null,
      },
      { onSuccess: cerrar }
    )
  }

  return (
    <FormModal
      opened={opened}
      onClose={cerrar}
      title="Agregar alergia"
      size="sm"
      onSubmit={handleSubmit(onSubmit)}
      submitLabel="Agregar alergia"
      submitting={agregar.isPending}
    >
      <Stack gap="sm">
        <Controller
          name="tipo"
          control={control}
          render={({ field }) => (
            <Select
              label="Tipo de alergia"
              required
              data={TIPO_ALERGIA_OPTIONS}
              placeholder="Seleccione"
              {...contained}
              value={field.value ?? null}
              onChange={field.onChange}
              error={errors.tipo?.message}
            />
          )}
        />
        <TextInput
          label="Descripción"
          placeholder="Ej: Penicilina, Mariscos, Polen..."
          {...contained}
          required
          {...register('descripcion')}
          error={errors.descripcion?.message}
        />
        <Controller
          name="severidad"
          control={control}
          render={({ field }) => (
            <Select
              label="Severidad"
              required
              data={SEVERIDAD_OPTIONS}
              placeholder="Seleccione"
              {...contained}
              value={field.value ?? null}
              onChange={field.onChange}
              error={errors.severidad?.message}
            />
          )}
        />
        <Textarea
          label="Observación (opcional)"
          placeholder="Detalles adicionales de la alergia"
          autosize
          minRows={2}
          {...contained}
          {...register('observacion')}
          error={errors.observacion?.message}
        />
      </Stack>
    </FormModal>
  )
}
