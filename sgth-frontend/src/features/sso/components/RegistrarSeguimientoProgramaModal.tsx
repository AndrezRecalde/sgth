'use client'

import { useEffect, useState } from 'react'
import {
  Modal, Button, Group, Stack, Select, Textarea, Text, Divider,
} from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { useForm, Controller, type Resolver } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useProgramaDrogasMutations } from '../hooks/useProgramaDrogas'
import { DocumentosSsoPanel } from './DocumentosSsoPanel'
import {
  seguimientoProgramaSchema, type SeguimientoProgramaFormData, ESTADO_ACTIVIDAD_PROGRAMA_OPTIONS,
} from '../schemas/programaDrogas.schema'
import { toDateValue, fromDateValue } from '@/lib/fecha'
import type { FilaSeguimientoPrograma } from '../services/programaDrogasService'

interface Props {
  opened: boolean
  onClose: () => void
  fila: FilaSeguimientoPrograma | null
  periodo: string
}

export function RegistrarSeguimientoProgramaModal({ opened, onClose, fila, periodo }: Props) {
  const { isMobile } = useMobileBreakpoint()
  const contained = useContainedInput()
  const { registrarSeguimiento } = useProgramaDrogasMutations()
  const [seguimientoId, setSeguimientoId] = useState<number | null>(fila?.seguimiento?.id ?? null)

  const {
    register, control, handleSubmit, reset,
    formState: { errors },
  } = useForm<SeguimientoProgramaFormData>({
    resolver: zodResolver(seguimientoProgramaSchema) as Resolver<SeguimientoProgramaFormData>,
    defaultValues: {
      estado: (fila?.seguimiento?.estado as SeguimientoProgramaFormData['estado']) ?? 'en_proceso',
      fecha_ejecucion: fila?.seguimiento?.fecha_ejecucion ?? null,
      observaciones: fila?.seguimiento?.observaciones ?? '',
    },
  })

  useEffect(() => {
    setSeguimientoId(fila?.seguimiento?.id ?? null)
    reset({
      estado: (fila?.seguimiento?.estado as SeguimientoProgramaFormData['estado']) ?? 'en_proceso',
      fecha_ejecucion: fila?.seguimiento?.fecha_ejecucion ?? null,
      observaciones: fila?.seguimiento?.observaciones ?? '',
    })
  }, [fila, reset])

  const handleClose = () => {
    reset()
    setSeguimientoId(null)
    onClose()
  }

  const onSubmit = (values: SeguimientoProgramaFormData) => {
    if (!fila) return
    registrarSeguimiento.mutateAsync({
      programa_droga_actividad_id: fila.actividad.id,
      periodo,
      ...values,
    }).then((resultado) => {
      setSeguimientoId(resultado?.id ?? null)
    }).catch(() => {})
  }

  return (
    <Modal
      opened={opened}
      onClose={handleClose}
      title="Registrar seguimiento de actividad"
      size="md"
      fullScreen={isMobile}
      radius={isMobile ? 0 : 'xl'}
    >
      <form onSubmit={handleSubmit(onSubmit)}>
        <Stack gap="sm">
          {fila && (
            <Text size="sm" c="dimmed">
              <Text span fw={600}>{fila.actividad.nombre}</Text> — período {periodo}
            </Text>
          )}
          <Controller
            name="estado"
            control={control}
            render={({ field }) => (
              <Select
                label="Estado"
                data={ESTADO_ACTIVIDAD_PROGRAMA_OPTIONS}
                required
                {...contained}
                value={field.value}
                onChange={(v) => field.onChange(v as SeguimientoProgramaFormData['estado'])}
                error={errors.estado?.message}
              />
            )}
          />
          <Controller
            name="fecha_ejecucion"
            control={control}
            render={({ field }) => (
              <DatePickerInput
                label="Fecha de ejecución (opcional)"
                placeholder="Seleccionar"
                valueFormat="DD/MM/YYYY"
                clearable
                {...contained}
                value={toDateValue(field.value ?? undefined)}
                onChange={(d) => field.onChange(d ? fromDateValue(d) : null)}
              />
            )}
          />
          <Textarea
            label="Observaciones"
            rows={2}
            {...contained}
            {...register('observaciones')}
            error={errors.observaciones?.message}
          />
          <Group justify="flex-end">
            <Button type="submit" loading={registrarSeguimiento.isPending} color="emerald" size="xs" variant="light">
              Guardar
            </Button>
          </Group>

          <Divider label="Evidencia" labelPosition="center" />
          <DocumentosSsoPanel tipo="programa_drogas_seguimiento" documentableId={seguimientoId} />

          <Group justify="flex-end" mt="md">
            <Button variant="default" onClick={handleClose}>
              Cerrar
            </Button>
          </Group>
        </Stack>
      </form>
    </Modal>
  )
}
