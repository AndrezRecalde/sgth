import { Group, Paper } from '@mantine/core'

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
 */
export function Toolbar({ children, actions }: Props) {
  return (
    <Paper withBorder radius="lg" p="sm">
      <Group gap="sm" align="flex-end" wrap="wrap">
        <Group gap="sm" align="flex-end" wrap="wrap" style={{ flex: 1, minWidth: 0 }}>
          {children}
        </Group>
        {actions && (
          <Group gap="sm" wrap="nowrap">
            {actions}
          </Group>
        )}
      </Group>
    </Paper>
  )
}
