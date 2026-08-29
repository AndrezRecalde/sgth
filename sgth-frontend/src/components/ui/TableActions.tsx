'use client'

import { ActionIcon, Menu } from '@mantine/core'
import { IconDots } from '@tabler/icons-react'

export interface TableAction {
  label: string
  icon: React.ReactNode
  /** Nombre de color de Mantine. Rojo solo para acciones destructivas. */
  color?: string
  onClick: () => void
  /** Oculta la acción por completo (p. ej. por permisos). */
  hidden?: boolean
  /** La muestra pero sin permitir usarla (p. ej. por estado del registro). */
  disabled?: boolean
}

interface Props {
  actions: TableAction[]
}

/**
 * Menú de acciones de una fila. Va SIEMPRE en la última columna, con
 * `width: 50` y sin título.
 *
 * Nunca se ponen `ActionIcon` sueltos en la fila: tres o cuatro iconos por
 * fila multiplicados por quince filas son cincuenta objetivos de clic
 * compitiendo con los datos, y el conjunto de acciones cambia de un módulo
 * a otro sin que la fila lo advierta.
 */
export function TableActions({ actions }: Props) {
  const visibles = actions.filter((a) => !a.hidden)
  if (!visibles.length) return null

  return (
    <Menu width={190} position="bottom-end">
      <Menu.Target>
        <ActionIcon aria-label="Acciones de la fila">
          <IconDots size={16} />
        </ActionIcon>
      </Menu.Target>

      <Menu.Dropdown>
        {visibles.map((action) => (
          <Menu.Item
            key={action.label}
            leftSection={action.icon}
            color={action.disabled ? undefined : action.color}
            disabled={action.disabled}
            onClick={action.onClick}
          >
            {action.label}
          </Menu.Item>
        ))}
      </Menu.Dropdown>
    </Menu>
  )
}
