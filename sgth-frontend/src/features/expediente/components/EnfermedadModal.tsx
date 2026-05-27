'use client'

import { Modal, Button, Group, Stack, TextInput } from '@mantine/core'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { useContainedInput } from '@/hooks/useContainedInput'
import { expedienteService } from '../services/expedienteService'
import { useQueryClient } from '@tanstack/react-query'
import { enfermedadSchema, type EnfermedadFormData }
  from '../schemas/enfermedad.schema'

interface Props {
  opened:     boolean
  onClose:    () => void
  servidorId: number
}

export function EnfermedadModal({ opened, onClose, servidorId }: Props) {
  const { isMobile } = useMobileBreakpoint()
  const contained = useContainedInput()
  const qc = useQueryClient()

  const { register, handleSubmit, reset, formState: { errors, isSubmitting } } =
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
          <TextInput label="Fecha de diagnóstico"
            type="date" {...contained}
            {...register('fecha_diagnostico')}
            error={errors.fecha_diagnostico?.message} />
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
