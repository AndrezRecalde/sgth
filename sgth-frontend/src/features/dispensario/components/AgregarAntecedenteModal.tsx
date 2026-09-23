'use client'

import {
  Stack, Select, Textarea,
  NumberInput,
} from '@mantine/core'
import { FormModal } from '@/components/ui'
import { useForm, Controller, type DefaultValues } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useAgregarAntecedente } from '../hooks/useHistoriaClinica'
import {
  antecedenteSchema,
  TIPO_ANTECEDENTE_OPTIONS,
  type AntecedenteFormData,
} from '../schemas/historiaClinica.schema'

interface Props {
  opened:      boolean
  onClose:     () => void
  historiaId:  number
  agendaId:    number
  tipo:        'personal' | 'familiar'
}

export function AgregarAntecedenteModal({
  opened, onClose, historiaId, agendaId, tipo,
}: Props) {
  const contained  = useContainedInput()
  const agregar    = useAgregarAntecedente(historiaId, agendaId)
  const esFamiliar = tipo === 'familiar'

  // El familiar no elige tipo: el modal ya viene abierto para eso. El personal
  // sí, y no hay uno por defecto, así que va omitido —igual que en el modal de
  // alergias—.
  const valoresIniciales: DefaultValues<AntecedenteFormData> = {
    ...(esFamiliar ? { tipo: 'familiar' as const } : {}),
    descripcion:      '',
    fecha_aproximada: null,
  }

  const {
    control, register, handleSubmit, reset,
    formState: { errors },
  } = useForm<AntecedenteFormData>({
    resolver: zodResolver(antecedenteSchema),
    defaultValues: valoresIniciales,
  })

  const cerrar = () => {
    reset(valoresIniciales)
    onClose()
  }

  const onSubmit = (values: AntecedenteFormData) => {
    agregar.mutate(
      {
        tipo:             values.tipo,
        descripcion:      values.descripcion,
        fecha_aproximada: values.fecha_aproximada,
      },
      { onSuccess: cerrar }
    )
  }

  return (
    <FormModal
      opened={opened}
      onClose={cerrar}
      title={esFamiliar
        ? 'Agregar antecedente familiar'
        : 'Agregar antecedente personal'}
      size="sm"
      onSubmit={handleSubmit(onSubmit)}
      submitLabel="Agregar antecedente"
      submitting={agregar.isPending}
    >
      <Stack gap="sm">
        {!esFamiliar && (
          <Controller
            name="tipo"
            control={control}
            render={({ field }) => (
              <Select
                label="Tipo de antecedente"
                required
                data={TIPO_ANTECEDENTE_OPTIONS}
                placeholder="Seleccione"
                {...contained}
                value={field.value ?? null}
                onChange={field.onChange}
                error={errors.tipo?.message}
              />
            )}
          />
        )}

        <Textarea
          label="Descripción"
          placeholder={esFamiliar
            ? "Ej: Padre con diabetes tipo 2, madre hipertensa..."
            : "Ej: Apendicectomía 2018, fractura de fémur..."}
          autosize
          minRows={3}
          {...contained}
          required
          {...register('descripcion')}
          error={errors.descripcion?.message}
        />

        <Controller
          name="fecha_aproximada"
          control={control}
          render={({ field }) => (
            <NumberInput
              label="Año aproximado (opcional)"
              placeholder="Ej: 2018"
              hideControls
              {...contained}
              value={field.value ?? ''}
              onChange={(v) => field.onChange(v === '' ? null : Number(v))}
              error={errors.fecha_aproximada?.message}
            />
          )}
        />
      </Stack>
    </FormModal>
  )
}
