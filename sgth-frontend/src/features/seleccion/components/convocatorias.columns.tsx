import { Stack, Text } from '@mantine/core'
import { IconEdit, IconEye, IconTrash, IconWorldUpload } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import { StatusBadge, TableActions } from '@/components/ui'
import { formatFechaMes } from '@/lib/fecha'
import {
  ESTADO_CONVOCATORIA_OPTIONS, TIPO_CONVOCATORIA_OPTIONS, TONO_CONVOCATORIA, type Convocatoria,
} from '../services/convocatoriaService'
import { etiquetaDe } from '../constants/postulante'

interface Acciones {
  gestiona:   boolean
  onVer:      (c: Convocatoria) => void
  onPublicar: (c: Convocatoria) => void
  onEditar:   (c: Convocatoria) => void
  onEliminar: (c: Convocatoria) => void
}

/** El listado de convocatorias formales. Publicar, editar y eliminar, solo en borrador. */
export function columnasConvocatorias(acc: Acciones): DataTableColumn<Convocatoria>[] {
  const enBorrador = (c: Convocatoria) => acc.gestiona && c.estado === 'borrador'

  return [
    {
      accessor: 'codigo',
      title: 'Código',
      width: 170,
      render: (c) => <Text size="sm" ff="monospace">{c.codigo}</Text>,
    },
    {
      accessor: 'titulo',
      title: 'Convocatoria',
      render: (c) => (
        <Stack gap={0}>
          <Text size="sm" fw={500}>{c.titulo}</Text>
          <Text size="xs" c="dimmed">
            {c.puesto?.cargo?.nombre ?? '—'}
            {c.puesto?.unidad_administrativa?.nombre ? ` · ${c.puesto.unidad_administrativa.nombre}` : ''}
          </Text>
        </Stack>
      ),
    },
    {
      accessor: 'tipo',
      title: 'Tipo',
      width: 100,
      render: (c) => <StatusBadge>{etiquetaDe(TIPO_CONVOCATORIA_OPTIONS, c.tipo)}</StatusBadge>,
    },
    {
      accessor: 'vacantes',
      title: 'Vacantes',
      width: 80,
      render: (c) => <Text size="sm" ta="center">{c.vacantes}</Text>,
    },
    {
      accessor: 'fecha_inicio',
      title: 'Período',
      width: 160,
      render: (c) => <Text size="xs">{formatFechaMes(c.fecha_inicio)} — {formatFechaMes(c.fecha_fin)}</Text>,
    },
    {
      accessor: 'estado',
      title: 'Estado',
      width: 140,
      render: (c) => (
        <StatusBadge tone={TONO_CONVOCATORIA[c.estado] ?? 'neutral'}>
          {etiquetaDe(ESTADO_CONVOCATORIA_OPTIONS, c.estado)}
        </StatusBadge>
      ),
    },
    {
      accessor: 'acciones',
      title: '',
      width: 50,
      render: (c) => (
        <TableActions actions={[
          { label: 'Ver detalle', icon: <IconEye size={14} />, onClick: () => acc.onVer(c) },
          { label: 'Publicar', icon: <IconWorldUpload size={14} />, onClick: () => acc.onPublicar(c), hidden: !enBorrador(c) },
          { label: 'Editar', icon: <IconEdit size={14} />, onClick: () => acc.onEditar(c), hidden: !enBorrador(c) },
          {
            label: 'Eliminar', icon: <IconTrash size={14} />, color: 'red',
            onClick: () => acc.onEliminar(c), hidden: !enBorrador(c),
          },
        ]} />
      ),
    },
  ]
}
