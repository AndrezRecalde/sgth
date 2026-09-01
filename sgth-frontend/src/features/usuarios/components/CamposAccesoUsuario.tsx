'use client'

import { TextInput, MultiSelect, Divider, Loader } from '@mantine/core'
import { Controller, type Control, type FieldErrors, type UseFormRegister } from 'react-hook-form'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useRoles } from '../hooks/useRoles'
import type { UsuarioFormValues } from '../schemas/usuario.schema'

interface Props {
  register: UseFormRegister<UsuarioFormValues>
  control:  Control<UsuarioFormValues>
  errors:   FieldErrors<UsuarioFormValues>
  /** Muestra el spinner mientras se pide el usuario TI sugerido. */
  cargandoTi?: boolean
}

/**
 * Correo, usuario del sistema y roles: los tres campos que el alta y la edición
 * comparten. La lista de roles sale de /admin/usuarios-roles y no de una
 * constante local, que era como `analista-uath` se quedaba fuera de la interfaz.
 */
export function CamposAccesoUsuario({
  register,
  control,
  errors,
  cargandoTi = false,
}: Props) {
  const contained = useContainedInput()
  const { data: roles = [] } = useRoles()

  const opcionesRol = roles.map(r => ({ value: r.valor, label: r.etiqueta }))

  return (
    <>
      <Divider label="Datos de acceso" labelPosition="left" />

      <TextInput
        label="Correo institucional"
        placeholder="usuario@gadpe.gob.ec"
        {...contained}
        {...register('email')}
        error={errors.email?.message}
      />

      <TextInput
        label="Usuario del sistema"
        placeholder="ej: jperez"
        description="Solo letras minúsculas y números"
        rightSection={cargandoTi ? <Loader size="xs" /> : undefined}
        {...contained}
        {...register('usuario_ti')}
        error={errors.usuario_ti?.message}
      />

      <Controller
        name="roles"
        control={control}
        render={({ field }) => (
          <MultiSelect
            label="Roles del sistema"
            placeholder="Seleccione uno o más roles"
            data={opcionesRol}
            searchable
            {...contained}
            value={field.value}
            onChange={field.onChange}
            error={errors.roles?.message}
          />
        )}
      />
    </>
  )
}
