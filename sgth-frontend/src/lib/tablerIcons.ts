import * as TablerIcons from '@tabler/icons-react'
import type { Icon } from '@tabler/icons-react'
import { IconPoint } from '@tabler/icons-react'
import React from 'react'

type IconName = keyof typeof TablerIcons

/**
 * Resuelve un icono de Tabler a partir de su nombre en texto.
 *
 * Existe porque hay tablas de configuración —el menú, los tipos de resultado
 * médico— que necesitan declarar un icono como dato. Guardar el componente en
 * esas tablas obligaría a importar cien iconos en un archivo de configuración.
 *
 * Si el nombre no existe se devuelve un punto en vez de reventar: un icono
 * equivocado en el menú no debe tumbar la pantalla entera.
 */
export function getIcon(name: string, size = 16): React.ReactElement {
  const Componente = TablerIcons[name as IconName] as Icon | undefined
  if (!Componente) {
    return React.createElement(IconPoint, { size, stroke: 2 })
  }
  return React.createElement(Componente, { size, stroke: 2 })
}

/** Icono del menú lateral. Tamaño fijo para que todos los ítems se alineen. */
export function getNavIcon(name: string): React.ReactElement {
  return getIcon(name, 16)
}
