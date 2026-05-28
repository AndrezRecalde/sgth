'use client'

import { Modal, Button, Group, Stack, TextInput } from '@mantine/core'
import { useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { useContainedInput } from '@/hooks/useContainedInput'
import { expedienteService } from '../services/expedienteService'
import { useQueryClient } from '@tanstack/react-query'
import { enfermedadSchema, type EnfermedadFormData }
  from '../schemas/enfermedad.schema'
import { DatePickerInput } from '@mantine/dates'
import '@mantine/dates/styles.css'

interface Props {
  opened:     boolean
  onClose:    () => void
  servidorId: number
}

const toDate = (v?: string | null): Date | null =>
  v ? new Date(v) : null
const fromDate = (d: Date | string | null): string | null => {
  if (!d) return null
  const date = new Date(d)
  return isNaN(date.getTime()) ? null : date.toISOString().split('T')[0]
}

export function EnfermedadModal({ opened, onClose, servidorId }: Props) {
  const { isMobile } = useMobileBreakpoint()
  const contained = useContainedInput()
  const qc = useQueryClient()

  const { register, handleSubmit, reset, control, formState: { errors, isSubmitting } } =
    useForm<EnfermedadFormData>({
      resolver: zodResolver(enfermedadSchema),
      defaultValues: {
        tipo_enfermedad:    '',
        codigo_cie10:       '',
        fecha_diagnostico:  '',
      },
    })

  const onSubmit = async (values: EnfermedadFormData) => {
    const payload = {
      ...values,
      codigo_cie10: values.codigo_cie10 || null,
      fecha_diagnostico: values.fecha_diagnostico || null,
    }
    await expedienteService.crearEnfermedad(servidorId, payload as Record<string, unknown>)
    qc.invalidateQueries({ queryKey: ['enfermedades', servidorId] })
    reset()
    onClose()
  }

  return (
    <Modal opened={opened} onClose={onClose}
      title="Registrar enfermedad catastrófica"
      size="sm" fullScreen={isMobile}
      radius={isMobile ? 0 : 'xl'}>
      <form onSubmit={handleSubmit(onSubmit)}>
        <Stack gap="sm">
          <TextInput label="Nombre/Tipo de la enfermedad"
            placeholder="Diagnóstico médico"
            {...contained} {...register('tipo_enfermedad')}
            error={errors.tipo_enfermedad?.message} />
          <TextInput label="Código CIE-10 (Opcional)"
            placeholder="Ej: C18.0"
            {...contained} {...register('codigo_cie10')}
            error={errors.codigo_cie10?.message} />
          <Controller
            name="fecha_diagnostico"
            control={control}
            render={({ field }) => (
              <DatePickerInput
                label="Fecha de diagnóstico"
                placeholder="Seleccionar fecha"
                valueFormat="YYYY-MM-DD"
                clearable
                {...contained}
                value={toDate(field.value)}
                onChange={(d) => field.onChange(fromDate(d))}
                error={errors.fecha_diagnostico?.message}
              />
            )}
          />
          <Group justify="flex-end" mt="md">
            <Button variant="default" onClick={onClose}>Cancelar</Button>
            <Button type="submit" color="emerald" variant="light"
              loading={isSubmitting}>
              Registrar enfermedad
            </Button>
          </Group>
        </Stack>
      </form>
    </Modal>
  )
}
