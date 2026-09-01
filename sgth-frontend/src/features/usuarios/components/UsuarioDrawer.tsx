'use client'

import { CrearUsuarioDrawer } from './CrearUsuarioDrawer'
import { EditarUsuarioDrawer } from './EditarUsuarioDrawer'
import type { Usuario } from '@/types/api'

interface Props {
  opened: boolean
  onClose: () => void
  usuario?: Usuario | null
}

/**
 * Elige entre el alta y la edición, que son dos flujos distintos: el alta es un
 * asistente de dos pasos que arranca de una ficha de servidor, y la edición un
 * formulario plano sobre un usuario que ya existe. Vivían en un solo componente
 * con banderas `modoEditar` cruzando todo el JSX y un único `useForm` sirviendo
 * a ambos.
 *
 * Quien llama no distingue: sigue pasando `usuario` o no.
 */
export function UsuarioDrawer({ opened, onClose, usuario }: Props) {
  return usuario
    ? (
      <EditarUsuarioDrawer
        opened={opened}
        onClose={onClose}
        usuario={usuario}
      />
    )
    : (
      <CrearUsuarioDrawer
        opened={opened}
        onClose={onClose}
      />
    )
}
