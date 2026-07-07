'use client'

import {
  Modal, Stack, NumberInput, Textarea,
  Button, Group, Text, Alert,
  Badge,
} from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import '@mantine/dates/styles.css'
import { useForm, Controller } from 'react-hook-form'
import { IconCheck, IconAlertCircle } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useEmitirCertificado } from '../hooks/useCertificado'
import { BuscarCie10Input } from './BuscarCie10Input'
import { useState } from 'react'
import type { ConsultaMedica } from '../services/consultaMedicaService'
import type { DiagnosticoCie10 } from '../services/cie10Service'

interface Props {
  opened:   boolean
  onClose:  () => void
  consulta: ConsultaMedica
  esFamiliar: boolean
}

type FormData = {
  dias_reposo:   number
  fecha_inicio?: string | null
  observaciones?: string
}

function toDate(v?: string | null): Date | null {
  if (!v) return null
  const [y, m, d] = v.slice(0, 10).split('-').map(Number)
  return new Date(y, m - 1, d)
}

function fromDate(d: Date | string | null): string | null {
  if (!d) return null
  const date = typeof d === 'string' ? toDate(d) : d
  if (!date || isNaN(date.getTime())) return null
  return [
    date.getFullYear(),
    String(date.getMonth() + 1).padStart(2, '0'),
    String(date.getDate()).padStart(2, '0'),
  ].join('-')
}

export function EmitirCertificadoModal({
  opened, onClose, consulta, esFamiliar,
}: Props) {
  const contained = useContainedInput()
  const emitir    = useEmitirCertificado(consulta.id)
  const [cie10Sel, setCie10Sel] =
    useState<DiagnosticoCie10 | null>(null)

  const {
    control, register, handleSubmit, reset,
    watch,
    formState: { errors },
  } = useForm<FormData>({
    defaultValues: {
      dias_reposo:   1,
      fecha_inicio:  null,
      observaciones: '',
    },
  })

  const diasReposo = watch('dias_reposo')

  const onSubmit = (values: FormData) => {
    emitir.mutate(
      {
        consulta_medica_id:    consulta.id,
        dias_reposo:           values.dias_reposo,
        fecha_inicio:          values.fecha_inicio || null,
        diagnostico_cie10_id:  cie10Sel?.id ?? null,
        observaciones:         values.observaciones || null,
      },
      {
        onSuccess: () => {
          reset()
          setCie10Sel(null)
          onClose()
        },
      }
    )
  }

  return (
    <Modal
      opened={opened}
      onClose={onClose}
      title="Emitir certificado médico"
      size="md"
      radius="xl"
    >
      <form onSubmit={handleSubmit(onSubmit)}>
        <Stack gap="sm">
          <Alert
            icon={<IconAlertCircle size={14} />}
            color="blue"
            variant="light"
          >
            <Text size="xs">
              Máximo <Text span fw={600}>3 días de reposo</Text> por
              certificado (normativa MSP Ecuador).
              {!esFamiliar && (
                <> El permiso de asistencia se generará
                automáticamente en el sistema.</>
              )}
            </Text>
          </Alert>

          <Controller
            name="dias_reposo"
            control={control}
            rules={{ required: true, min: 1, max: 3 }}
            render={({ field }) => (
              <NumberInput
                label="Días de reposo"
                min={1}
                max={3}
                {...contained}
                value={field.value}
                onChange={(v) => field.onChange(Number(v) || 1)}
                error={errors.dias_reposo?.message}
              />
            )}
          />

          <Controller
            name="fecha_inicio"
            control={control}
            render={({ field }) => (
              <DatePickerInput
                label="Fecha de inicio del reposo"
                description="Por defecto: fecha de la consulta"
                valueFormat="DD/MM/YYYY"
                clearable
                {...contained}
                value={toDate(field.value)}
                onChange={(d) => field.onChange(fromDate(d))}
              />
            )}
          />

          <BuscarCie10Input
            value={cie10Sel}
            onChange={setCie10Sel}
          />

          <Textarea
            label="Observaciones (opcional)"
            placeholder="Indicaciones adicionales del reposo"
            autosize
            minRows={2}
            {...contained}
            {...register('observaciones')}
          />

          <Group justify="flex-end" mt="sm">
            <Button variant="default" onClick={onClose}>
              Cancelar
            </Button>
            <Button
              type="submit"
              color="emerald"
              leftSection={<IconCheck size={14} />}
              loading={emitir.isPending}
            >
              Emitir certificado
            </Button>
          </Group>
        </Stack>
      </form>
    </Modal>
  )
}
