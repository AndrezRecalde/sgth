'use client'

import {
  Stack, Select, Textarea,
  NumberInput, 
} from '@mantine/core'
import { FormModal } from '@/components/ui'
import { useForm, Controller } from 'react-hook-form'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useAgregarAntecedente } from '../hooks/useHistoriaClinica'

interface Props {
  opened:      boolean
  onClose:     () => void
  historiaId:  number
  agendaId:    number
  tipo:        'personal' | 'familiar'
}

const TIPO_PERSONAL_OPTIONS = [
  { value: 'quirurgico',  label: 'Quirúrgico'  },
  { value: 'patologico',  label: 'Patológico'  },
  { value: 'traumatico',  label: 'Traumático'  },
  { value: 'ginecologico',label: 'Ginecológico'},
  { value: 'otro',        label: 'Otro'         },
]

type FormData = {
  tipo:              string
  descripcion:       string
  fecha_aproximada:  number | null
}

export function AgregarAntecedenteModal({
  opened, onClose, historiaId, agendaId, tipo,
}: Props) {
  const contained = useContainedInput()
  const agregar   = useAgregarAntecedente(historiaId, agendaId)
  const esFamiliar = tipo === 'familiar'

  const {
    control, register, handleSubmit, reset,
    formState: { errors },
  } = useForm<FormData>({
    defaultValues: {
      tipo:             esFamiliar ? 'familiar' : '',
      descripcion:      '',
      fecha_aproximada: null,
    },
  })

  const onSubmit = (values: FormData) => {
    agregar.mutate(
      {
        tipo:             values.tipo,
        descripcion:      values.descripcion,
        fecha_aproximada: values.fecha_aproximada || null,
      },
      { onSuccess: () => { reset(); onClose() } }
    )
  }

  return (
    <FormModal
      opened={opened}
      onClose={() => { reset(); onClose() }}
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
            rules={{ required: 'Seleccione el tipo de antecedente' }}
            render={({ field }) => (
              <Select
                label="Tipo de antecedente"
                required
                data={TIPO_PERSONAL_OPTIONS}
                placeholder="Seleccione"
                {...contained}
                value={field.value}
                onChange={(v) => field.onChange(v ?? '')}
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
          {...register('descripcion', {
            required: 'Describa el antecedente',
            minLength: {
              value: 5,
              message: 'Mínimo 5 caracteres',
            },
          })}
          error={errors.descripcion?.message}
        />

        <Controller
          name="fecha_aproximada"
          control={control}
          render={({ field }) => (
            <NumberInput
              label="Año aproximado (opcional)"
              placeholder="Ej: 2018"
              min={1900}
              max={new Date().getFullYear()}
              {...contained}
              value={field.value ?? undefined}
              onChange={(v) => field.onChange(v ? Number(v) : null)}
            />
          )}
        />
      </Stack>
    </FormModal>
  )
}
