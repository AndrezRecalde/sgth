'use client'

import { Text, Badge, Group, Stack } from '@mantine/core'
import {
  IconFileText, IconEye, IconBan, IconFileSearch,
} from '@tabler/icons-react'
import { TableActions } from '@/components/ui/TableActions'
import type { DataTableColumn } from 'mantine-datatable'
import type { Adquisicion } from '../services/adquisicionService'

interface ColumnActions {
  onVerDetalle:     (a: Adquisicion) => void
  onDescargarDocumento:   (a: Adquisicion) => void
  onSubirDocumento: (a: Adquisicion) => void
  onAnular:         (a: Adquisicion) => void
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
      width:    150,
      render: (a) => (
        <Stack gap={2}>
          <Text
            size="sm"
            ff="monospace"
            fw={500}
            td={a.anulado_en ? 'line-through' : undefined}
            c={a.anulado_en ? 'dimmed' : undefined}
          >
            {a.folio}
          </Text>
          {a.anulado_en && (
            <Badge size="xs" variant="light" color="orange">
              Anulada
            </Badge>
          )}
        </Stack>
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
      width:    120,
      render: (a) => (
        <Text size="sm">{formatFecha(a.fecha_adquisicion)}</Text>
      ),
    },
    {
      accessor: 'items',
      title:    'Ítems',
      width:    80,
      render: (a) => (
        <Badge size="sm" variant="light" color="gray">
          {a.items?.length ?? 0}
        </Badge>
      ),
    },
    {
      accessor: 'documento_respaldo',
      title:    'Respaldo',
      width:    90,
      render: (a) => (
        <Group gap={4} justify="center">
          <Badge
            size="sm"
            variant="dot"
            color={a.documento_respaldo ? 'emerald' : 'gray'}
          >
            {a.documento_respaldo ? 'Adjunto' : 'Pendiente'}
          </Badge>
        </Group>
      ),
    },
    {
      accessor: 'acciones',
      title:    '',
      width:    50,
      render: (a) => (
        <TableActions actions={[
          {
            label:   'Ver detalle',
            icon:    <IconEye size={14} />,
            color:   'blue',
            onClick: () => actions.onVerDetalle(a),
          },
          ...(a.documento_respaldo ? [{
            label:   'Descargar respaldo',
            icon:    <IconFileSearch size={14} />,
            color:   'blue',
            onClick: () => actions.onDescargarDocumento(a),
          }] : []),
          // Una adquisición anulada ya no admite respaldo ni segunda
          // anulación: solo queda consultarla.
          ...(a.anulado_en ? [] : [
            {
              label:   a.documento_respaldo
                ? 'Reemplazar documento'
                : 'Subir documento de respaldo',
              icon:    <IconFileText size={14} />,
              color:   a.documento_respaldo ? 'gray' : 'emerald',
              onClick: () => actions.onSubirDocumento(a),
            },
            {
              label:   'Anular adquisición',
              icon:    <IconBan size={14} />,
              color:   'orange',
              onClick: () => actions.onAnular(a),
            },
          ]),
        ]} />
      ),
    },
  ]
}
