'use client'

import { Modal, Button, Group, Stack, TextInput,
         NumberInput, Select } from '@mantine/core'
import { useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { useContainedInput } from '@/hooks/useContainedInput'
import { expedienteService } from '../services/expedienteService'
import { useQueryClient } from '@tanstack/react-query'
import { discapacidadSchema, type DiscapacidadFormData }
  from '../schemas/discapacidad.schema'

const TIPO_OPTIONS = [
  { value: 'fisica',          label: 'Física' },
  { value: 'visual',          label: 'Visual' },
  { value: 'auditiva',        label: 'Auditiva' },
  { value: 'intelectual',     label: 'Intelectual' },
  { value: 'psicosocial',     label: 'Psicosocial' },
  { value: 'multiple',        label: 'Múltiple' },
]

interface Props {
  opened:     boolean
  onClose:    () => void
  servidorId: number
}

export function DiscapacidadModal({ opened, onClose, servidorId }: Props) {
  const { isMobile } = useMobileBreakpoint()
  const contained = useContainedInput()
  const qc = useQueryClient()

  const { register, control, handleSubmit, reset, formState: { errors, isSubmitting } } =
    useForm<DiscapacidadFormData>({
      resolver: zodResolver(discapacidadSchema),
      defaultValues: {
        tipo_discapacidad:     '',
        porcentaje:            1,
        numero_carnet_conadis: '',
      },
    })

  const onSubmit = async (values: DiscapacidadFormData) => {
    await expedienteService.crearDiscapacidad(servidorId, values as Record<string, unknown>)
    qc.invalidateQueries({ queryKey: ['discapacidades', servidorId] })
    reset()
    onClose()
  }

  return (
    <Modal opened={opened} onClose={onClose}
      title="Registrar discapacidad"
      size="sm" fullScreen={isMobile}
      radius={isMobile ? 0 : 'xl'}>
      <form onSubmit={handleSubmit(onSubmit)}>
        <Stack gap="sm">
          <Controller name="tipo_discapacidad" control={control}
            render={({ field }) => (
              <Select label="Tipo de discapacidad"
                data={TIPO_OPTIONS} {...contained}
                value={field.value}
                onChange={(v) => field.onChange(v ?? '')}
                error={errors.tipo_discapacidad?.message} />
            )} />
          <Controller name="porcentaje" control={control}
            render={({ field }) => (
              <NumberInput label="Porcentaje de discapacidad"
                placeholder="%" min={1} max={100} suffix="%"
                {...contained}
                value={field.value}
                onChange={(v) => field.onChange(typeof v === 'number' ? v : 1)}
                error={errors.porcentaje?.message} />
            )} />
          <TextInput label="Número de carnet CONADIS"
            placeholder="Ingrese el número de carnet"
            {...contained} {...register('numero_carnet_conadis')}
            error={errors.numero_carnet_conadis?.message} />
          <Group justify="flex-end" mt="md">
            <Button variant="default" onClick={onClose}>Cancelar</Button>
            <Button type="submit" color="emerald" variant="light"
              loading={isSubmitting}>
              Registrar discapacidad
            </Button>
          </Group>
        </Stack>
      </form>
    </Modal>
  )
}
