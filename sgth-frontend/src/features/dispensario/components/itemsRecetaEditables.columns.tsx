'use client'

import { Stack, Text } from '@mantine/core'
import { IconEdit, IconTrash } from '@tabler/icons-react'
import { confirmar, StatusBadge, TableActions } from '@/components/ui'
import { esItemExterno, nombreDeItem } from '../services/recetaService'
import type { DataTableColumn } from 'mantine-datatable'
import type { ItemReceta } from '../services/recetaService'

interface Acciones {
  /** `false` esconde la columna de acciones: la receta ya no se retoca. */
  puedeRetocar: boolean
  onEditar:  (item: ItemReceta) => void
  onQuitar:  (item: ItemReceta) => void
}

/**
 * Los medicamentos de una receta mientras el médico todavía puede tocarla.
 *
 * El hermano de `itemsReceta.columns`, que es el de solo lectura del cajón de
 * detalle: mismas cinco columnas, más la de acciones.
 */
export function getItemsRecetaEditablesColumns({
  puedeRetocar, onEditar, onQuitar,
}: Acciones): DataTableColumn<ItemReceta>[] {
  return [
    {
      accessor: 'medicina',
      title:    'Medicina',
      render: (item) => (
        // La insignia va debajo del nombre y no a su lado: esta columna se
        // estrecha con el ancho de la pantalla, y en línea quedaba cortada por
        // el borde justo en el aviso que hay que leer.
        <Stack gap={2} align="flex-start">
          <Text size="sm" fw={500}>{nombreDeItem(item)}</Text>
          {esItemExterno(item) ? (
            <>
              <StatusBadge tone="warning" size="xs">
                Fuera de farmacia
              </StatusBadge>
              <Text size="xs" c="dimmed">
                El paciente lo adquiere fuera del dispensario.
              </Text>
            </>
          ) : item.inventario?.concentracion ? (
            <Text size="xs" c="dimmed">
              {item.inventario.concentracion}
            </Text>
          ) : null}
        </Stack>
      ),
    },
    {
      accessor: 'cantidad_prescrita',
      title:    'Cant.',
      width:    70,
      render: (item) => (
        <Text size="sm" ta="center">{item.cantidad_prescrita}</Text>
      ),
    },
    {
      accessor: 'dosis',
      title:    'Dosis',
      width:    110,
      render: (item) => <Text size="sm">{item.dosis}</Text>,
    },
    {
      accessor: 'frecuencia',
      title:    'Frecuencia',
      width:    130,
      render: (item) => <Text size="sm">{item.frecuencia}</Text>,
    },
    {
      accessor: 'duracion',
      title:    'Duración',
      width:    100,
      render: (item) => <Text size="sm">{item.duracion}</Text>,
    },
    ...(puedeRetocar ? [{
      accessor: 'acciones',
      title:    '',
      width:    50,
      render: (item: ItemReceta) => (
        <TableActions actions={[
          {
            label:   'Editar',
            icon:    <IconEdit size={14} />,
            onClick: () => onEditar(item),
          },
          {
            label:   'Quitar',
            icon:    <IconTrash size={14} />,
            color:   'red',
            onClick: () => confirmar({
              title:   'Quitar medicamento',
              message: 'Se quitará este medicamento de la receta.',
              destructiva: true,
              confirmLabel: 'Quitar',
              onConfirm: () => onQuitar(item),
            }),
          },
        ]} />
      ),
    }] : []),
  ]
}
