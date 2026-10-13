import { Text, type MantineTheme } from '@mantine/core'
import { IconEdit, IconTrash } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import { StatusBadge, TableActions } from '@/components/ui'
import { formatFecha } from '@/lib/fecha'
import type { ActividadLaboral } from '../services/misActividadesService'

/**
 * En un teléfono la descripción necesita el ancho: la categoría se filtra
 * arriba.
 */
const DESDE_SM = (theme: MantineTheme) => `(min-width: ${theme.breakpoints.sm})`

interface Acciones {
  onCorregir: (a: ActividadLaboral) => void
  onEliminar: (a: ActividadLaboral) => void
}

/** Las columnas de «Mis actividades». Las de meses anteriores ya no se tocan. */
export function getColumnasActividades({ onCorregir, onEliminar }: Acciones): DataTableColumn<ActividadLaboral>[] {
  return [
    {
      accessor: 'fecha',
      title: 'Fecha',
      width: 120,
      render: (a) => formatFecha(a.fecha),
    },
    {
      accessor: 'descripcion',
      title: 'Actividad',
      render: (a) => (
        <div>
          <Text size="sm" lineClamp={3} title={a.descripcion} style={{ whiteSpace: 'pre-line' }}>{a.descripcion}</Text>
          {a.producto_entregable && (
            <Text size="xs" c="dimmed" mt={2} lineClamp={1}>Producto: {a.producto_entregable}</Text>
          )}
        </div>
      ),
    },
    {
      accessor: 'categoria',
      title: 'Categoría',
      width: 200,
      visibleMediaQuery: DESDE_SM,
      // Una categoría va neutra: ninguna es mejor que otra.
      render: (a) => <StatusBadge>{a.categoria_etiqueta}</StatusBadge>,
    },
    {
      accessor: 'acciones',
      title: '',
      width: 50,
      render: (a) => (
        <TableActions
          actions={[
            { label: 'Corregir', icon: <IconEdit size={14} />, disabled: !a.puede_editar, onClick: () => onCorregir(a) },
            { label: 'Eliminar', icon: <IconTrash size={14} />, color: 'red', disabled: !a.puede_editar, onClick: () => onEliminar(a) },
          ]}
        />
      ),
    },
  ]
}
