import { Stack, Text } from '@mantine/core'
import { IconBan, IconCheck, IconFileDownload, IconFileText, IconPackageExport, IconUpload } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import { StatusBadge, TableActions, type TableAction } from '@/components/ui'
import { formatFecha } from '@/lib/fecha'
import { TONO_ESTADO_BAJA } from '../constants/bajas'
import type { BajaBien } from '../services/tiposBaja'
import type { AccionBaja } from './BajaModales'

interface Opciones {
  /** `dar-baja-bien`: aprobar, rechazar, el memorando y la entrega. */
  puedeResolver: boolean
  abrir: (accion: NonNullable<AccionBaja>) => void
  documento: (b: BajaBien, que: 'informe' | 'memorando') => void
}

/** Las acciones de una baja según su estado y quién la mira. */
export const accionesBaja = (b: BajaBien, { puedeResolver, abrir, documento }: Opciones): TableAction[] => {
  const propuesta = b.estado === 'propuesta'
  const aprobada = b.estado === 'aprobada'
  // La de un componente se resuelve con la de su equipo.
  const resoluble = puedeResolver && b.baja_principal_id === null

  return [
    { label: 'Informe técnico', icon: <IconFileText size={14} />, onClick: () => documento(b, 'informe') },
    { label: 'Aprobar', icon: <IconCheck size={14} />, hidden: !resoluble || !propuesta, onClick: () => abrir({ tipo: 'aprobar', baja: b }) },
    { label: 'Rechazar', icon: <IconBan size={14} />, color: 'red', hidden: !resoluble || !propuesta, onClick: () => abrir({ tipo: 'rechazar', baja: b }) },
    {
      label: b.tiene_memorando ? 'Reemplazar memorando' : 'Adjuntar memorando', icon: <IconUpload size={14} />,
      hidden: !resoluble || !aprobada, onClick: () => abrir({ tipo: 'memorando', baja: b }),
    },
    { label: 'Ver memorando', icon: <IconFileDownload size={14} />, hidden: !b.tiene_memorando, onClick: () => documento(b, 'memorando') },
    {
      label: 'Entrega a Activos Fijos', icon: <IconPackageExport size={14} />,
      hidden: !puedeResolver || !aprobada || b.entregado_activos_en !== null, onClick: () => abrir({ tipo: 'entrega', baja: b }),
    },
  ]
}

const celdaEstado = (b: BajaBien) => (
  <Stack gap={2}>
    <StatusBadge tone={TONO_ESTADO_BAJA[b.estado]}>{b.estado_etiqueta}</StatusBadge>
    {b.estado === 'aprobada' && (
      <Text size="xs" c="dimmed">
        {b.entregado_activos_en ? `Entregado a Activos el ${formatFecha(b.entregado_activos_en)}` : 'Falta entregar a Activos'}
      </Text>
    )}
    {b.estado === 'rechazada' && <Text size="xs" c="dimmed" lineClamp={2}>{b.motivo_rechazo}</Text>}
  </Stack>
)

const celdaMotivo = (b: BajaBien) => (
  <Stack gap={0}>
    <Text size="sm">{b.motivo_etiqueta}</Text>
    <Text size="xs" c="dimmed" lineClamp={2}>
      {b.principal ? `Salió con el equipo ${b.principal.codigo_institucional} (${b.principal.numero})` : b.diagnostico}
    </Text>
  </Stack>
)

/** El listado de bajas, con el equipo. */
export const columnasBajas = (accionesDe: (b: BajaBien) => TableAction[]): DataTableColumn<BajaBien>[] => [
  { accessor: 'numero', title: 'Informe', width: 110, render: (b) => <Text size="sm" fw={600}>{b.numero}</Text> },
  { accessor: 'fecha_informe', title: 'Fecha', width: 105, render: (b) => formatFecha(b.fecha_informe) },
  {
    accessor: 'bien',
    title: 'Equipo',
    width: 190,
    render: (b) => (
      <Stack gap={0}>
        <Text size="sm" fw={600}>{b.bien?.codigo_institucional}</Text>
        <Text size="xs" c="dimmed">{[b.bien?.tipo, b.bien?.marca, b.bien?.modelo].filter(Boolean).join(' · ')}</Text>
      </Stack>
    ),
  },
  { accessor: 'motivo', title: 'Motivo', render: celdaMotivo },
  { accessor: 'estado', title: 'Estado', width: 190, render: celdaEstado },
  { accessor: 'acciones', title: '', width: 50, render: (b) => <TableActions actions={accionesDe(b)} /> },
]

/** Las bajas en la ficha del equipo: sin el equipo y con quién la propuso. */
export const columnasBajasDelBien = (accionesDe: (b: BajaBien) => TableAction[]): DataTableColumn<BajaBien>[] => [
  { accessor: 'numero', title: 'Informe', width: 110, render: (b) => <Text size="sm" fw={600}>{b.numero}</Text> },
  { accessor: 'fecha_informe', title: 'Fecha', width: 105, render: (b) => formatFecha(b.fecha_informe) },
  { accessor: 'motivo', title: 'Motivo', render: celdaMotivo },
  { accessor: 'informado_por', title: 'Propuesta por', width: 170, render: (b) => b.informado_por ?? '—' },
  { accessor: 'estado', title: 'Estado', width: 190, render: celdaEstado },
  { accessor: 'acciones', title: '', width: 50, render: (b) => <TableActions actions={accionesDe(b)} /> },
]
