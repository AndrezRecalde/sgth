'use client'

import { Text, Badge, Stack, ActionIcon, Tooltip } from '@mantine/core'
import { IconFileText, IconUpload } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import type { Adquisicion } from '../services/adquisicionService'

interface ColumnActions {
  onVerDetalle: (a: Adquisicion) => void
  onSubirDocumento: (a: Adquisicion) => void
}

function formatFecha(fecha: string): string {
  return new Date(fecha).toLocaleDateString('es-EC', {
    day: '2-digit', month: 'short', year: 'numeric',
  })
}

export function getAdquisicionesColumns(
  actions: ColumnActions
): DataTableColumn<Adquisicion>[] {
  return [
    {
      accessor: 'folio',
      title:    'Folio',
      width:    140,
      render: (a) => (
        <Text size="sm" ff="monospace" fw={500}>{a.folio}</Text>
      ),
    },
    {
      accessor: 'tipo',
      title:    'Tipo',
      width:    110,
      render: (a) => (
        <Badge
          size="sm"
          variant="light"
          color={a.tipo === 'donacion' ? 'violet' : 'blue'}
        >
          {a.tipo === 'donacion' ? 'Donación' : 'Compra'}
        </Badge>
      ),
    },
    {
      accessor: 'numero_documento',
      title:    'N° documento',
      render: (a) => (
        <Text size="sm" ff="monospace">{a.numero_documento}</Text>
      ),
    },
    {
      accessor: 'proveedor_o_donante',
      title:    'Proveedor / Donante',
      render: (a) => <Text size="sm">{a.proveedor_o_donante}</Text>,
    },
    {
      accessor: 'fecha_adquisicion',
      title:    'Fecha',
      width:    110,
      render: (a) => (
        <Text size="sm">{formatFecha(a.fecha_adquisicion)}</Text>
      ),
    },
    {
      accessor: 'items',
      title:    'Medicinas',
      width:    100,
      render: (a) => (
        <Badge size="sm" variant="light" color="gray">
          {a.items?.length ?? 0} ítem(s)
        </Badge>
      ),
    },
    {
      accessor: 'documento_respaldo',
      title:    'Respaldo',
      width:    90,
      render: (a) => (
        <Tooltip
          label={a.documento_respaldo
            ? 'Documento adjunto'
            : 'Subir documento de respaldo'}
          withArrow
        >
          <ActionIcon
            size="sm"
            variant="subtle"
            color={a.documento_respaldo ? 'emerald' : 'gray'}
            onClick={() => actions.onSubirDocumento(a)}
          >
            {a.documento_respaldo
              ? <IconFileText size={16} />
              : <IconUpload size={16} />}
          </ActionIcon>
        </Tooltip>
      ),
    },
  ]
}
