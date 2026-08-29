'use client'

import { useEffect } from 'react'
import {
  Modal, Button, Group, Stack,
  TextInput, Select, Textarea, Switch, NumberInput,
} from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { useForm, Controller, type Resolver } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { useContainedInput } from '@/hooks/useContainedInput'
import { BuscarServidorSelect } from '@/features/expediente/components/BuscarServidorSelect'
import { useAccidenteTrabajoMutations } from '../hooks/useAccidentesTrabajo'
import {
  accidenteTrabajoSchema, type AccidenteTrabajoFormData, GRAVEDAD_OPTIONS,
  TIPO_EVENTO_ACCIDENTE_OPTIONS,
} from '../schemas/accidenteTrabajo.schema'
import { toDateValue, fromDateValue } from '@/lib/fecha'
import type { AccidenteTrabajo } from '../services/ssoService'

interface Props {
  opened:     boolean
  onClose:    () => void
  accidente?: AccidenteTrabajo | null
}

export function AccidenteTrabajoModal({ opened, onClose, accidente }: Props) {
  const { isMobile }      = useMobileBreakpoint()
  const contained         = useContainedInput()
  const { crear, editar } = useAccidenteTrabajoMutations()
  const isEditing         = !!accidente

  const {
    register,
    control,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<AccidenteTrabajoFormData>({
    resolver: zodResolver(accidenteTrabajoSchema) as Resolver<AccidenteTrabajoFormData>,
    defaultValues: {
      servidor_id:               accidente?.servidor_id               ?? 0,
      tipo_evento:               (accidente?.tipo_evento as AccidenteTrabajoFormData['tipo_evento']) ?? 'accidente',
      fecha_accidente:           accidente?.fecha_accidente            ?? '',
      hora_accidente:            accidente?.hora_accidente             ?? '',
      lugar_accidente:           accidente?.lugar_accidente            ?? '',
      descripcion_hechos:        accidente?.descripcion_hechos         ?? '',
      gravedad:                  (accidente?.gravedad as AccidenteTrabajoFormData['gravedad']) ?? 'leve',
      requirio_atencion_medica:  accidente?.requirio_atencion_medica   ?? false,
      dias_reposo_medico:        accidente?.dias_reposo_medico         ?? undefined,
      causa_raiz:                accidente?.causa_raiz                 ?? '',
      medidas_correctivas:       accidente?.medidas_correctivas        ?? '',
      estado:                    accidente?.estado                     ?? true,
    },
  })

  useEffect(() => {
    reset({
      servidor_id:               accidente?.servidor_id               ?? 0,
      tipo_evento:               (accidente?.tipo_evento as AccidenteTrabajoFormData['tipo_evento']) ?? 'accidente',
      fecha_accidente:           accidente?.fecha_accidente            ?? '',
      hora_accidente:            accidente?.hora_accidente             ?? '',
      lugar_accidente:           accidente?.lugar_accidente            ?? '',
      descripcion_hechos:        accidente?.descripcion_hechos         ?? '',
      gravedad:                  (accidente?.gravedad as AccidenteTrabajoFormData['gravedad']) ?? 'leve',
      requirio_atencion_medica:  accidente?.requirio_atencion_medica   ?? false,
      dias_reposo_medico:        accidente?.dias_reposo_medico         ?? undefined,
      causa_raiz:                accidente?.causa_raiz                 ?? '',
      medidas_correctivas:       accidente?.medidas_correctivas        ?? '',
      estado:                    accidente?.estado                     ?? true,
    })
  }, [accidente, reset])

  const handleClose = () => {
    reset()
    onClose()
  }

  const onSubmit = (values: AccidenteTrabajoFormData) => {
    const mutation = isEditing
      ? editar.mutateAsync({ id: accidente!.id, data: values })
      : crear.mutateAsync(values)
    mutation.then(handleClose).catch(() => {})
  }

  const isPending = crear.isPending || editar.isPending

  return (
    <Modal
      opened={opened}
      onClose={handleClose}
      title={isEditing ? 'Editar accidente de trabajo' : 'Nuevo accidente de trabajo'}
      size="lg"
      fullScreen={isMobile}
      radius={isMobile ? 0 : 'xl'}
    >
      <form onSubmit={handleSubmit(onSubmit)}>
        <Stack gap="sm">
          <Controller
            name="servidor_id"
            control={control}
            render={({ field }) => (
              <BuscarServidorSelect
                label="Servidor"
                required
                value={field.value || null}
                onChange={(id) => field.onChange(id ?? 0)}
                error={errors.servidor_id?.message}
              />
            )}
          />
          <Controller
            name="tipo_evento"
            control={control}
            render={({ field }) => (
              <Select
                label="Tipo de evento"
                data={TIPO_EVENTO_ACCIDENTE_OPTIONS}
                required
                {...contained}
                value={field.value}
                onChange={(v) => field.onChange(v as AccidenteTrabajoFormData['tipo_evento'])}
                error={errors.tipo_evento?.message}
              />
            )}
          />
          <Group grow>
            <Controller
              name="fecha_accidente"
              control={control}
              render={({ field }) => (
                <DatePickerInput
                  label="Fecha del accidente"
                  placeholder="Seleccionar"
                  valueFormat="DD/MM/YYYY"
                  required
                  {...contained}
                  value={toDateValue(field.value)}
                  onChange={(d) => field.onChange(fromDateValue(d ?? null))}
                  error={errors.fecha_accidente?.message}
                />
              )}
            />
            <TextInput
              type="time"
              label="Hora del accidente"
              required
              {...contained}
              {...register('hora_accidente')}
              error={errors.hora_accidente?.message}
            />
          </Group>
          <TextInput
            label="Lugar del accidente"
            required
            {...contained}
            {...register('lugar_accidente')}
            error={errors.lugar_accidente?.message}
          />
          <Textarea
            label="Descripción de los hechos"
            rows={3}
            required
            {...contained}
            {...register('descripcion_hechos')}
            error={errors.descripcion_hechos?.message}
          />
          <Group grow>
            <Controller
              name="gravedad"
              control={control}
              render={({ field }) => (
                <Select
                  label="Gravedad"
                  data={GRAVEDAD_OPTIONS}
                  {...contained}
                  value={field.value}
                  onChange={(v) => field.onChange(v as AccidenteTrabajoFormData['gravedad'])}
                  error={errors.gravedad?.message}
                />
              )}
            />
            <Controller
              name="dias_reposo_medico"
              control={control}
              render={({ field }) => (
                <NumberInput
                  label="Días de reposo médico"
                  min={0}
                  {...contained}
                  value={field.value ?? ''}
                  onChange={(v) => field.onChange(typeof v === 'number' ? v : undefined)}
                />
              )}
            />
          </Group>
          <Controller
            name="requirio_atencion_medica"
            control={control}
            render={({ field }) => (
              <Switch
                label="Requirió atención médica"
                checked={field.value}
                onChange={(e) => field.onChange(e.currentTarget.checked)}
              />
            )}
          />
          <Textarea
            label="Causa raíz"
            placeholder="Causa raíz identificada (opcional)"
            rows={2}
            {...contained}
            {...register('causa_raiz')}
            error={errors.causa_raiz?.message}
          />
          <Textarea
            label="Medidas correctivas"
            placeholder="Medidas correctivas aplicadas (opcional)"
            rows={2}
            {...contained}
            {...register('medidas_correctivas')}
            error={errors.medidas_correctivas?.message}
          />
          <Controller
            name="estado"
            control={control}
            render={({ field }) => (
              <Switch
                label="Investigación abierta"
                checked={field.value}
                onChange={(e) => field.onChange(e.currentTarget.checked)}
              />
            )}
          />
          <Group justify="flex-end" mt="md">
            <Button variant="default" onClick={handleClose}>
              Cancelar
            </Button>
            <Button type="submit" loading={isPending} color="emerald">
              {isEditing ? 'Actualizar' : 'Registrar accidente'}
            </Button>
          </Group>
        </Stack>
      </form>
    </Modal>
  )
}
