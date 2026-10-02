'use client'

import { PasswordInput, Stack } from '@mantine/core'
import { useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal, notificar } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresDeCampo } from '@/lib/erroresDeCampo'
import {
  actualizarContrasenaSchema,
  type ActualizarContrasenaFormData,
} from '../schemas/cambiarPassword.schema'
import { useActualizarContrasena } from '../hooks/useActualizarContrasena'
import { RequisitosContrasena } from './RequisitosContrasena'

interface Props {
  opened: boolean
  onClose: () => void
}

const CAMPOS: (keyof ActualizarContrasenaFormData)[] = [
  'contrasena_actual',
  'nueva_contrasena',
  'confirmar_contrasena',
]

const esCampo = (campo: string): campo is keyof ActualizarContrasenaFormData =>
  CAMPOS.some((c) => c === campo)

/**
 * Cambiar la contraseña por iniciativa propia.
 *
 * Hasta ahora solo se podía en el primer acceso: quien sospechaba que alguien
 * más la conocía tenía que pedirle a TI que la restableciera a la cédula —que
 * es justo lo que cualquiera puede adivinar— y volver a cambiarla.
 */
export function CambiarContrasenaModal({ opened, onClose }: Props) {
  const actualizar = useActualizarContrasena()
  const contained = useContainedInput()

  const {
    register,
    handleSubmit,
    control,
    reset,
    setError,
    formState: { errors },
  } = useForm<ActualizarContrasenaFormData>({
    resolver: zodResolver(actualizarContrasenaSchema),
    defaultValues: {
      contrasena_actual:    '',
      nueva_contrasena:     '',
      confirmar_contrasena: '',
    },
  })

  const nuevaContrasena = useWatch({ control, name: 'nueva_contrasena' })

  const cerrar = () => {
    reset()
    onClose()
  }

  const guardar = async (valores: ActualizarContrasenaFormData) => {
    try {
      await actualizar.mutateAsync({
        contrasena_actual: valores.contrasena_actual,
        nueva_contrasena:  valores.nueva_contrasena,
      })
      cerrar()
    } catch (error) {
      const campos = erroresDeCampo(error)
      if (!campos) return // el hook ya lo notificó

      const sinCampo: string[] = []
      for (const [campo, mensaje] of Object.entries(campos)) {
        if (esCampo(campo)) {
          setError(campo, { message: mensaje })
        } else {
          sinCampo.push(mensaje)
        }
      }

      if (sinCampo.length) {
        notificar.error('No se pudo cambiar la contraseña', sinCampo.join(' '))
      }
    }
  }

  return (
    <FormModal
      opened={opened}
      onClose={cerrar}
      title="Cambiar contraseña"
      onSubmit={handleSubmit(guardar)}
      submitLabel="Cambiar contraseña"
      submitting={actualizar.isPending}
      closeOnClickOutside={false}
      size="md"
    >
      <Stack gap="md">
        <PasswordInput
          label="Contraseña actual"
          autoComplete="current-password"
          data-autofocus
          {...contained}
          {...register('contrasena_actual')}
          error={errors.contrasena_actual?.message}
        />
        <PasswordInput
          label="Nueva contraseña"
          autoComplete="new-password"
          {...contained}
          {...register('nueva_contrasena')}
          error={errors.nueva_contrasena?.message}
        />
        <RequisitosContrasena contrasena={nuevaContrasena} />
        <PasswordInput
          label="Confirmar nueva contraseña"
          autoComplete="new-password"
          {...contained}
          {...register('confirmar_contrasena')}
          error={errors.confirmar_contrasena?.message}
        />
      </Stack>
    </FormModal>
  )
}
