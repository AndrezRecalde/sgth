'use client'

import Link from 'next/link'
import { Badge, Tooltip, UnstyledButton } from '@mantine/core'
import { getNavIcon } from '@/lib/tablerIcons'
import classes from './Sidebar.module.css'

interface Props {
  label: string
  icon: string
  href: string
  badge?: string
  active: boolean
  collapsed: boolean
  /** Marca el ítem como hijo de un submenú (altura y peso menores). */
  nested?: boolean
  onNavigate?: () => void
}

/** Destino navegable del sidebar. El estado activo lo decide `Sidebar`. */
export function NavItem({
  label,
  icon,
  href,
  badge,
  active,
  collapsed,
  nested = false,
  onNavigate,
}: Props) {
  const enlace = (
    <UnstyledButton
      component={Link}
      href={href}
      onClick={onNavigate}
      aria-current={active ? 'page' : undefined}
      className={[
        classes.item,
        nested && classes.child,
        active && classes.itemActive,
        collapsed && classes.itemCollapsed,
      ]
        .filter(Boolean)
        .join(' ')}
    >
      <span className={classes.itemIcon}>{getNavIcon(icon)}</span>
      {!collapsed && (
        <>
          <span className={classes.itemLabel}>{label}</span>
          {badge && (
            <Badge size="xs" color="red" circle variant="filled">
              {badge}
            </Badge>
          )}
        </>
      )}
    </UnstyledButton>
  )

  // Plegado el texto no se ve: el tooltip es la única forma de saber qué es.
  if (collapsed) {
    return (
      <Tooltip label={label} position="right" openDelay={0}>
        {enlace}
      </Tooltip>
    )
  }

  return enlace
}
