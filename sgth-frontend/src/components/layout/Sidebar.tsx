'use client'

import { useMemo } from 'react'
import Image from 'next/image'
import Link from 'next/link'
import { ScrollArea, Text } from '@mantine/core'
import { usePathname } from 'next/navigation'
import { NavItem } from './NavItem'
import { NavItemNested } from './NavItemNested'
import { SubsistemaSwitcher } from './SubsistemaSwitcher'
import { useAuth } from '@/hooks/useAuth'
import { useStockBajoCount } from '@/features/dispensario/hooks/useInventarioMedicina'
import { buildNav, ROLES_INVENTARIO_MED } from '@/config/nav'
import { SUBSISTEMAS } from '@/config/subsistemas'
import { ROUTES, type Subsistema } from '@/config/routes'
import classes from './Sidebar.module.css'

interface Props {
  subsistema: Subsistema
  collapsed: boolean
  onNavigate?: () => void
}

export function Sidebar({ subsistema, collapsed, onNavigate }: Props) {
  const { usuario } = useAuth()
  const pathname = usePathname()
  const permisos = usuario?.permisos ?? []
  const permisosKey = permisos.join(',')
  const roles = usuario?.roles ?? []
  const rolesKey = roles.join(',')

  // El contador se pedía en todas las cargas de todos los subsistemas, así que
  // a quien no es del dispensario le devolvía un 403 cada vez. Solo se consulta
  // donde el badge se pinta y a quien el backend deja entrar al inventario.
  const { data: stockBajo = 0 } = useStockBajoCount({
    enabled: subsistema === 'salud' &&
      roles.some((rol) => ROLES_INVENTARIO_MED.includes(rol)),
  })

  /**
   * Menú del subsistema: filtrado por permisos y roles, y con los badges
   * dinámicos ya inyectados en la estructura. Hoy el único badge es el conteo
   * de medicinas bajo mínimo, que cuelga tanto del ítem Farmacia como de su
   * hijo Inventario.
   */
  const grupos = useMemo(() => {
    const badgeDe = (href: string) =>
      href === ROUTES.SALUD.FARMACIA && stockBajo > 0
        ? String(stockBajo)
        : undefined

    return buildNav(subsistema, permisos, roles).map((grupo) => ({
      ...grupo,
      items: grupo.items.map((item) => ({
        ...item,
        badge: badgeDe(item.href) ?? item.badge,
        children: item.children?.map((child) => ({
          ...child,
          badge: badgeDe(child.href) ?? child.badge,
        })),
      })),
    }))
    // Se depende del contenido de `permisos` y `roles`, no de su identidad de
    // array, que es nueva en cada render.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [subsistema, permisosKey, rolesKey, stockBajo])

  /**
   * Un destino está activo si es la coincidencia MÁS LARGA con la ruta actual.
   *
   * Sustituye a las listas de rutas "de coincidencia exacta" que antes estaban
   * escritas a mano dentro de NavItem y NavItemNested: cada pantalla anidada
   * nueva obligaba a acordarse de registrar su padre en esas listas, y
   * olvidarlo dejaba dos ítems iluminados a la vez.
   */
  const hrefActivo = useMemo(() => {
    const destinos = grupos.flatMap((g) =>
      g.items.flatMap((item) =>
        item.children?.length ? item.children.map((c) => c.href) : [item.href],
      ),
    )

    return destinos
      .filter((href) => pathname === href || pathname.startsWith(`${href}/`))
      .reduce<string | null>(
        (mejor, href) => (!mejor || href.length > mejor.length ? href : mejor),
        null,
      )
  }, [grupos, pathname])

  const isActive = (href: string) => href === hrefActivo
  const cfg = SUBSISTEMAS[subsistema]

  return (
    <nav className={classes.sidebar} aria-label="Navegación principal">
      <Link
        href={cfg.home}
        className={`${classes.brand} ${collapsed ? classes.brandCollapsed : ''}`}
      >
        <Image
          src="/logo.png"
          alt="GAD Provincial de Esmeraldas"
          width={32}
          height={32}
          className={classes.brandMark}
          priority
        />
        {!collapsed && (
          <span className={classes.brandText}>
            <span className={classes.brandName}>GADPE</span>
            <span className={classes.brandSub}>Esmeraldas</span>
          </span>
        )}
      </Link>

      <div className={classes.switcherSlot} data-collapsed={collapsed || undefined}>
        <SubsistemaSwitcher actual={subsistema} collapsed={collapsed} />
      </div>

      <ScrollArea className={classes.nav} type="scroll" scrollbarSize={6}>
        {grupos.map((grupo) => (
          <div key={grupo.label} className={classes.group}>
            {collapsed ? (
              <div className={classes.groupRule} />
            ) : (
              <Text component="div" className={classes.groupLabel}>
                {grupo.label}
              </Text>
            )}

            {grupo.items.map((item) =>
              item.children?.length ? (
                <NavItemNested
                  key={item.href}
                  item={item}
                  isActive={isActive}
                  collapsed={collapsed}
                  onNavigate={onNavigate}
                />
              ) : (
                <NavItem
                  key={item.href}
                  {...item}
                  active={isActive(item.href)}
                  collapsed={collapsed}
                  onNavigate={onNavigate}
                />
              ),
            )}
          </div>
        ))}
      </ScrollArea>

      {!collapsed && (
        <div className={classes.footer}>
          <Text component="div" className={classes.footerText}>
            SGTH · GAD Provincial de Esmeraldas
          </Text>
        </div>
      )}
    </nav>
  )
}
