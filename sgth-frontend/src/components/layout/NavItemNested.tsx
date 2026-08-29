'use client'

import { useState } from 'react'
import { Collapse, Tooltip, UnstyledButton } from '@mantine/core'
import { IconChevronRight } from '@tabler/icons-react'
import { NavItem } from './NavItem'
import { getNavIcon } from '@/lib/tablerIcons'
import type { NavItem as NavItemConfig } from '@/config/nav'
import classes from './Sidebar.module.css'

interface Props {
  item: NavItemConfig
  /** Decide si una ruta está activa. Lo inyecta `Sidebar`. */
  isActive: (href: string) => boolean
  collapsed: boolean
  onNavigate?: () => void
}

/**
 * Ítem del sidebar con submenú desplegable.
 *
 * El submenú se abre solo cuando la ruta activa está dentro, y el usuario
 * puede plegarlo/desplegarlo a mano. No se cierra al navegar entre hermanos.
 */
export function NavItemNested({ item, isActive, collapsed, onNavigate }: Props) {
  const hijoActivo = item.children?.some((c) => isActive(c.href)) ?? false
  const [abierto, setAbierto] = useState(hijoActivo)

  // Al navegar hacia dentro de este submenú, se despliega solo. Se ajusta
  // durante el render y no en un efecto: así el submenú ya aparece abierto en
  // el primer pintado, sin el parpadeo de abrirse un frame después.
  const [hijoActivoPrevio, setHijoActivoPrevio] = useState(hijoActivo)
  if (hijoActivo !== hijoActivoPrevio) {
    setHijoActivoPrevio(hijoActivo)
    if (hijoActivo) setAbierto(true)
  }

  const disparador = (
    <UnstyledButton
      onClick={() => setAbierto((o) => !o)}
      aria-expanded={abierto}
      className={[
        classes.item,
        // Plegado no hay submenú visible: el padre debe mostrar él mismo que
        // algo de adentro está activo.
        hijoActivo && collapsed && classes.itemActive,
        collapsed && classes.itemCollapsed,
      ]
        .filter(Boolean)
        .join(' ')}
    >
      <span className={classes.itemIcon}>{getNavIcon(item.icon)}</span>
      {!collapsed && (
        <>
          <span className={classes.itemLabel}>{item.label}</span>
          <IconChevronRight
            size={14}
            className={`${classes.chevron} ${abierto ? classes.chevronOpen : ''}`}
          />
        </>
      )}
    </UnstyledButton>
  )

  if (collapsed) {
    return (
      <Tooltip label={item.label} position="right" openDelay={0}>
        {disparador}
      </Tooltip>
    )
  }

  return (
    <div className={classes.nested}>
      {disparador}
      <Collapse expanded={abierto}>
        <div className={classes.children}>
          {item.children?.map((child) => (
            <NavItem
              key={child.href}
              {...child}
              nested
              active={isActive(child.href)}
              collapsed={false}
              onNavigate={onNavigate}
            />
          ))}
        </div>
      </Collapse>
    </div>
  )
}
