'use client'

import { useEffect } from 'react'
import { Alert, Stack, TextInput } from '@mantine/core'
import { IconInfoCircle } from '@tabler/icons-react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod/v4'
import { FormModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import type { RespaldoContable } from '../services/viaticoService'

/*
| El número de resolución y la partida presupuestaria que asigna Gestión
| Financiera (2026-09-15): al entregar el anticipo, y al contabilizar cuando el
| viático se tramitó sin anticipo.
|
| Las dos columnas existían desde el principio y ningún formulario las pedía:
| el comprobante contable salía sin respaldo.
*/

const schema = z.object({
  numero_resolucion: z
    .string()
    .trim()
    .min(1, 'Indique el número de resolución')
    .max(100, 'Máximo 100 caracteres'),
  partida_presupuestaria: z
    .string()
    .trim()
    .min(1, 'Indique la partida presupuestaria')
    .max(100, 'Máximo 100 caracteres'),
})

interface Props {
  opened: boolean
  onClose: () => void
  title: string
  descripcion: React.ReactNode
  confirmLabel: string
  cargando?: boolean
  onConfirm: (datos: RespaldoContable) => void
}

export function RespaldoContableModal({
  opened,
  onClose,
  title,
  descripcion,
  confirmLabel,
  cargando = false,
  onConfirm,
}: Props) {
  const contained = useContainedInput()

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<RespaldoContable>({
    resolver: zodResolver(schema),
    defaultValues: { numero_resolucion: '', partida_presupuestaria: '' },
  })

  // El modal vive montado: sin esto conserva lo escrito la vez anterior.
  useEffect(() => {
    if (opened) reset({ numero_resolucion: '', partida_presupuestaria: '' })
  }, [opened, reset])

  const cerrar = () => {
    reset()
    onClose()
  }

  return (
    <FormModal
      opened={opened}
      onClose={cerrar}
      title={title}
      size="md"
      closeOnClickOutside={false}
      onSubmit={handleSubmit((datos) => onConfirm(datos))}
      submitLabel={confirmLabel}
      submitting={cargando}
    >
      <Stack gap="md">
        <Alert icon={<IconInfoCircle size={16} />} color="ocean" variant="light" py={8}>
          {descripcion}
        </Alert>

        <TextInput
          label="Número de resolución"
          placeholder="Ej: RES-GADPE-2026-0123"
          {...contained}
          {...register('numero_resolucion')}
          error={errors.numero_resolucion?.message}
        />

        <TextInput
          label="Partida presupuestaria"
          placeholder="Ej: 530301"
          {...contained}
          {...register('partida_presupuestaria')}
          error={errors.partida_presupuestaria?.message}
        />
      </Stack>
    </FormModal>
  )
}
