'use client'

import { Alert, Button, Group, Modal, Stack, Textarea } from '@mantine/core'
import { IconInfoCircle } from '@tabler/icons-react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod/v4'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'

/*
| Rechazar un documento y deshacer una confirmación son las dos acciones del
| módulo que borran algo ya hecho —la segunda incluso devuelve saldo de
| vacaciones—, así que el backend exige motivo. `confirmar()` no sirve aquí:
| pregunta sí o no, no recoge texto.
*/

const schema = z.object({
  motivo: z
    .string()
    .trim()
    .min(5, 'Explique la razón: al menos 5 caracteres')
    .max(500, 'El motivo no puede exceder los 500 caracteres'),
})

type FormData = z.infer<typeof schema>

interface Props {
  opened: boolean
  onClose: () => void
  title: string
  /** Qué va a pasar exactamente, con el folio nombrado. */
  descripcion: React.ReactNode
  confirmLabel: string
  cargando?: boolean
  onConfirm: (motivo: string) => void
}

export function MotivoPermisoModal({
  opened,
  onClose,
  title,
  descripcion,
  confirmLabel,
  cargando = false,
  onConfirm,
}: Props) {
  const contained = useContainedInput()
  const { isMobile } = useMobileBreakpoint()

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<FormData>({
    resolver: zodResolver(schema),
    defaultValues: { motivo: '' },
  })

  const cerrar = () => {
    reset()
    onClose()
  }

  const enviar = (valores: FormData) => {
    onConfirm(valores.motivo)
    reset()
  }

  return (
    <Modal
      opened={opened}
      onClose={cerrar}
      title={title}
      centered
      radius={isMobile ? 0 : 'xl'}
      fullScreen={isMobile}
      closeOnClickOutside={false}
    >
      <form onSubmit={handleSubmit(enviar)} noValidate>
        <Stack gap="md">
          <Alert
            icon={<IconInfoCircle size={16} />}
            color="amber"
            variant="light"
            py={8}
          >
            {descripcion}
          </Alert>

          <Textarea
            label="Motivo"
            placeholder="Por qué se realiza esta acción"
            autosize
            minRows={3}
            maxRows={6}
            {...contained}
            {...register('motivo')}
            error={errors.motivo?.message}
          />

          <Group justify="flex-end">
            <Button variant="default" onClick={cerrar}>
              Cancelar
            </Button>
            <Button type="submit" color="orange" variant="light" loading={cargando}>
              {confirmLabel}
            </Button>
          </Group>
        </Stack>
      </form>
    </Modal>
  )
}
