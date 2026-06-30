'use client'

import { Text, Badge, Group } from '@mantine/core'
import {
  IconEdit, IconPlus, IconHistory,
  IconBan, IconCircleCheck, IconAdjustments,
} from '@tabler/icons-react'
import { TableActions } from '@/components/ui/TableActions'
import type { DataTableColumn } from 'mantine-datatable'
import type { InventarioMedicina } from '../services/inventarioMedicinaService'

interface ColumnActions {
  onEditar:        (m: InventarioMedicina) => void
  onIngresarStock: (m: InventarioMedicina) => void
  onAjustar:       (m: InventarioMedicina) => void
  onVerKardex:     (m: InventarioMedicina) => void
  onToggleEstado:  (m: InventarioMedicina) => void
}

export function getMedicinasColumns(
  actions: ColumnActions
): DataTableColumn<InventarioMedicina>[] {
  return [
    {
      accessor: 'codigo',
      title:    'Código',
      width:    110,
      render: (m) => (
        <Text size="sm" ff="monospace">{m.codigo}</Text>
      ),
    },
    {
      accessor: 'nombre',
      title:    'Medicina',
      render: (m) => (
        <Text size="sm" fw={500}>
          {m.nombre}
          {m.concentracion && (
            <Text span c="dimmed"> — {m.concentracion}</Text>
          )}
        </Text>
      ),
    },
    {
      accessor: 'principio_activo',
      title:    'Principio activo',
      render: (m) => (
        <Text size="sm" c="dimmed">{m.principio_activo}</Text>
      ),
    },
    {
      accessor: 'presentacion',
      title:    'Presentación',
      width:    130,
    },
    {
      accessor: 'stock_actual',
      title:    'Stock',
      width:    100,
      render: (m) => {
        const stockBajo = m.stock_actual <= m.stock_minimo
        return (
          <Badge
            size="sm"
            variant="light"
            color={stockBajo ? 'red' : 'emerald'}
          >
            {m.stock_actual} unid.
          </Badge>
        )
      },
    },
    {
      accessor: 'estado',
      title:    'Estado',
      width:    100,
      render: (m) => (
        <Badge
          size="sm"
          variant="light"
          color={m.estado ? 'emerald' : 'gray'}
        >
          {m.estado ? 'Activo' : 'Inactivo'}
        </Badge>
      ),
    },
    {
      accessor: 'acciones',
      title:    '',
      width:    50,
      render: (m) => (
        <TableActions actions={[
          {
            label:   'Editar',
            icon:    <IconEdit size={14} />,
            color:   'blue',
            onClick: () => actions.onEditar(m),
          },
          {
            label:   'Ingresar stock',
            icon:    <IconPlus size={14} />,
            color:   'emerald',
            onClick: () => actions.onIngresarStock(m),
          },
          {
            label:   'Ajustar inventario',
            icon:    <IconAdjustments size={14} />,
            color:   'blue',
            onClick: () => actions.onAjustar(m),
          },
          {
            label:   'Ver kardex',
            icon:    <IconHistory size={14} />,
            color:   'blue',
            onClick: () => actions.onVerKardex(m),
          },
          {
            label:   m.estado ? 'Dar de baja' : 'Reactivar',
            icon:    m.estado
              ? <IconBan size={14} />
              : <IconCircleCheck size={14} />,
            color:   m.estado ? 'red' : 'emerald',
            onClick: () => actions.onToggleEstado(m),
          },
        ]} />
      ),
    },
  ]
}
