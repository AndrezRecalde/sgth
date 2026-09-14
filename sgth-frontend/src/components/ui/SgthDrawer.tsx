'use client'

import { Drawer, Text, type DrawerProps } from '@mantine/core'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'

/** 480: una lista angosta (kardex, actividades). 560: detalle o formulario. 720: expediente completo. */
const ANCHOS = { sm: 480, md: 560, lg: 720 } as const

type Props = Omit<DrawerProps, 'title' | 'size' | 'position' | 'padding'> & {
  /** Texto, sin icono decorativo: lo mismo que el título de un modal. */
  title: string
  /** El registro abierto, debajo del título: el nombre del servidor, la medicina. */
  description?: React.ReactNode
  ancho?: keyof typeof ANCHOS
}

/**
 * El único panel lateral del sistema.
 *
 * Existe por lo mismo que `SgthModal`: los 13 drawers resolvían cada uno la
 * versión móvil, el ancho y la cabecera. Salían con siete anchos distintos
 * (480, 520, 560, 580, 720, `lg`, `xl`), con la cabecera armada de cuatro
 * formas —icono en ThemeIcon, icono suelto, título en `sm` o en `md`— y ocho de
 * ellos metían un `ScrollArea` de `calc(100vh - 80px)` dentro del cuerpo, que
 * ya tiene scroll: dos barras, y la de dentro cortaba el final.
 *
 * Aquí se decide una vez: a la derecha, pantalla completa por debajo de 768 px,
 * tres anchos y el scroll lo pone el propio drawer. Un pie con botones va en
 * `ModalFooter`, que es pegajoso también aquí.
 */
export function SgthDrawer({ title, description, ancho = 'md', ...props }: Props) {
  const { isMobile } = useMobileBreakpoint()

  return (
    <Drawer
      {...props}
      position="right"
      padding="lg"
      size={isMobile ? '100%' : ANCHOS[ancho]}
      title={
        <>
          {title}
          {description && (
            <Text component="span" display="block" size="xs" c="dimmed" fw={400} mt={4}>
              {description}
            </Text>
          )}
        </>
      }
    />
  )
}
