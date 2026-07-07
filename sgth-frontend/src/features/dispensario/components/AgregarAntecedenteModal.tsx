'use client'

import {
  Modal, Stack, Select, Textarea,
  NumberInput, Button, Group,
} from '@mantine/core'
import { useForm, Controller } from 'react-hook-form'
import { IconCheck } from '@tabler/icons-react'
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

const TIPO_FAMILIAR_OPTIONS = [
  { value: 'familiar', label: 'Familiar' },
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
    <Modal
      opened={opened}
      onClose={() => { reset(); onClose() }}
      title={esFamiliar
        ? 'Agregar antecedente familiar'
        : 'Agregar antecedente personal'}
      size="sm"
      radius="xl"
    >
      <form onSubmit={handleSubmit(onSubmit)}>
        <Stack gap="sm">
          {!esFamiliar && (
            <Controller
              name="tipo"
              control={control}
              rules={{ required: true }}
              render={({ field }) => (
                <Select
                  label="Tipo de antecedente"
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
            {...register('descripcion', { required: true, minLength: 5 })}
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

          <Group justify="flex-end" mt="sm">
            <Button
              variant="default"
              onClick={() => { reset(); onClose() }}
            >
              Cancelar
            </Button>
            <Button
              type="submit"
              color="emerald"
              leftSection={<IconCheck size={14} />}
              loading={agregar.isPending}
            >
              Agregar antecedente
            </Button>
          </Group>
        </Stack>
      </form>
    </Modal>
  )
}
