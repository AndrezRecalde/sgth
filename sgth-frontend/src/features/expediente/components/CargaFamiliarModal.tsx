'use client'

import { Modal, Button, Group, Stack, TextInput, Select, Switch, Textarea } from '@mantine/core'
import { useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useCargaFamiliarMutations } from '../hooks/useCargaFamiliarMutations'
import { cargaFamiliarSchema, type CargaFamiliarFormData }
  from '../schemas/cargaFamiliar.schema'

const PARENTESCO_OPTIONS = [
  { value: 'conyugue', label: 'Cónyuge / Conviviente' },
  { value: 'hijo',     label: 'Hijo/a' },
]

interface Props {
  opened:     boolean
  onClose:    () => void
  servidorId: number
}

export function CargaFamiliarModal({ opened, onClose, servidorId }: Props) {
  const { isMobile } = useMobileBreakpoint()
  const contained = useContainedInput()
  const { crear }  = useCargaFamiliarMutations(servidorId)

  const { register, control, handleSubmit, reset, formState: { errors } } =
    useForm<CargaFamiliarFormData>({
      resolver: zodResolver(cargaFamiliarSchema),
      defaultValues: {
        nombres:                       '',
        apellidos:                     '',
        parentesco:                    'hijo',
        fecha_nacimiento:              '',
        persona_con_discapacidad:      false,
        posee_enfermedad_catastrofica: false,
        observaciones:                 '',
      },
    })

  const onSubmit = (values: CargaFamiliarFormData) => {
    const payload = {
      ...values,
      observaciones: values.observaciones || null,
    }
    crear.mutateAsync(payload as Record<string, unknown>)
      .then(() => { reset(); onClose() })
      .catch(() => {})
  }

  return (
    <Modal opened={opened} onClose={onClose}
      title="Agregar carga familiar"
      size="md" fullScreen={isMobile}
      radius={isMobile ? 0 : 'xl'}>
      <form onSubmit={handleSubmit(onSubmit)}>
        <Stack gap="sm">
          <TextInput label="Nombres"
            placeholder="Nombres del familiar"
            {...contained} {...register('nombres')}
            error={errors.nombres?.message} />

          <TextInput label="Apellidos"
            placeholder="Apellidos del familiar"
            {...contained} {...register('apellidos')}
            error={errors.apellidos?.message} />

          <Controller name="parentesco" control={control}
            render={({ field }) => (
              <Select label="Parentesco"
                data={PARENTESCO_OPTIONS} {...contained}
                value={field.value} onChange={(v) => field.onChange(v ?? 'hijo')}
                error={errors.parentesco?.message} />
            )} />

          <TextInput label="Fecha de nacimiento"
            type="date" {...contained}
            {...register('fecha_nacimiento')}
            error={errors.fecha_nacimiento?.message} />

          <Controller name="persona_con_discapacidad" control={control}
            render={({ field }) => (
              <Switch label="Persona con discapacidad"
                checked={field.value}
                onChange={(e) => field.onChange(e.currentTarget.checked)}
                color="emerald" />
            )} />

          <Controller name="posee_enfermedad_catastrofica" control={control}
            render={({ field }) => (
              <Switch label="Posee enfermedad catastrófica"
                checked={field.value}
                onChange={(e) => field.onChange(e.currentTarget.checked)}
                color="emerald" />
            )} />

          <Textarea label="Observaciones (Opcional)"
            placeholder="Observaciones adicionales"
            rows={2}
            {...contained} {...register('observaciones')}
            error={errors.observaciones?.message} />

          <Group justify="flex-end" mt="md">
            <Button variant="default" onClick={onClose}>Cancelar</Button>
            <Button type="submit" color="emerald" variant="light"
              loading={crear.isPending}>
              Agregar familiar
            </Button>
          </Group>
        </Stack>
      </form>
    </Modal>
  )
}
