'use client'

import { Stack, Text } from '@mantine/core'
import type { DataTableColumn } from 'mantine-datatable'
import type { ItemRecetaDetalle } from '../services/consultaMedicaService'

/** Los medicamentos de una receta, dentro del detalle de la consulta. */
export const columnasItemsReceta: DataTableColumn<ItemRecetaDetalle>[] = [
  {
    accessor: 'inventario.nombre',
    title: 'Medicina',
    render: (item) => (
      <Stack gap={0}>
        <Text size="xs" fw={500}>
          {item.inventario?.nombre ?? item.medicamento_externo ?? '—'}
        </Text>
        <Text size="xs" c="dimmed">
          {item.inventario ? item.inventario.concentracion ?? '' : 'Fuera de farmacia'}
        </Text>
      </Stack>
    ),
  },
  { accessor: 'cantidad_prescrita', title: 'Cant.', width: 70, textAlign: 'center' },
  { accessor: 'dosis', title: 'Dosis', width: 110 },
  { accessor: 'frecuencia', title: 'Frecuencia', width: 120 },
]
