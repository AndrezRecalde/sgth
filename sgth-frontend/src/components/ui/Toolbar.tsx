import { Group, Paper } from '@mantine/core'
import classes from './Toolbar.module.css'

interface Props {
  /** Filtros y búsqueda. Se estiran para ocupar el ancho disponible. */
  children: React.ReactNode
  /** Acciones ligadas a la selección o al conjunto (exportar, limpiar…). */
  actions?: React.ReactNode
}

/**
 * Barra de filtros sobre un listado.
 *
 * Va SIEMPRE entre `PageHeader` y la tabla, dentro de su propia superficie:
 * separar los filtros del listado deja claro qué recorta los resultados y
 * qué es un resultado.
 *
 * Los campos que van dentro usan la variante compacta del patrón contained
 * (`useContainedInput('sm')`), para que convivan a la misma altura con los
 * botones sin el aire de un formulario de captura.
 *
 * Cuando los filtros y las acciones no caben en una fila —un teléfono, o una
 * barra con muchos filtros— las acciones bajan a la suya. El reparto vive en
 * `Toolbar.module.css`.
 */
export function Toolbar({ children, actions }: Props) {
  return (
    <Paper withBorder radius="lg" p="sm">
      <Group gap="sm" align="flex-end" wrap="wrap">
        <Group gap="sm" align="flex-end" wrap="wrap" className={classes.filtros}>
          {children}
        </Group>
        {actions && (
          <Group gap="sm" wrap="wrap">
            {actions}
          </Group>
        )}
      </Group>
    </Paper>
  )
}
