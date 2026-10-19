import { Group, Text } from '@mantine/core'
import { IconArrowBackUp, IconEdit, IconFileTypePdf, IconUserMinus } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import { StatusBadge, TableActions, confirmar } from '@/components/ui'
import { PARENTESCO_LABELS } from '@/features/expediente/constants/cargaFamiliar'
import { edadEnAnios, formatFecha } from '@/lib/fecha'
import type { MiCargaFamiliar, SolicitudCargaFamiliar } from '@/types/api'
import { TONO_SOLICITUD_CARGA } from '../constants/misCargas'

const nacimiento = (fecha?: string | null) => {
  const edad = edadEnAnios(fecha)
  return `${formatFecha(fecha)}${edad !== null ? ` (${edad} ${edad === 1 ? 'año' : 'años'})` : ''}`
}

interface AccionesCarga {
  onCorregir: (carga: MiCargaFamiliar) => void
  onBaja:     (carga: MiCargaFamiliar) => void
}

/**
 * Sus cargas en «Mi perfil» (D13): quién es y si está activa. Sin condiciones
 * de salud. Con algo pendiente no ofrece pedir más: el backend solo admite una
 * solicitud pendiente por familiar.
 */
export const getMisCargasColumns = (
  { onCorregir, onBaja }: AccionesCarga,
): DataTableColumn<MiCargaFamiliar>[] => [
  {
    accessor: 'nombres',
    title: 'Familiar',
    render: (c) => (
      <div>
        <Text size="sm" fw={600}>{`${c.apellidos} ${c.nombres}`}</Text>
        {c.cedula && <Text size="xs" c="dimmed" ff="monospace">CI: {c.cedula}</Text>}
      </div>
    ),
  },
  {
    accessor: 'parentesco',
    title: 'Parentesco',
    width: 110,
    render: (c) => <StatusBadge size="xs">{PARENTESCO_LABELS[c.parentesco] ?? c.parentesco}</StatusBadge>,
  },
  {
    accessor: 'fecha_nacimiento',
    title: 'Nacimiento',
    width: 170,
    render: (c) => <Text size="sm">{nacimiento(c.fecha_nacimiento)}</Text>,
  },
  {
    accessor: 'estado',
    title: 'Estado',
    width: 190,
    render: (c) => (
      <Group gap={6}>
        <StatusBadge tone={c.estado ? 'success' : 'neutral'}>{c.estado ? 'Activa' : 'Inactiva'}</StatusBadge>
        {c.tiene_solicitud_pendiente && <StatusBadge tone="warning" variant="dot">Solicitud pendiente</StatusBadge>}
      </Group>
    ),
  },
  {
    accessor: 'acciones',
    title: '',
    width: 50,
    render: (c) => (
      <TableActions actions={[
        {
          label: 'Pedir corrección',
          icon: <IconEdit size={14} />,
          hidden: c.tiene_solicitud_pendiente,
          onClick: () => onCorregir(c),
        },
        {
          label: 'Pedir baja',
          icon: <IconUserMinus size={14} />,
          color: 'red',
          hidden: c.tiene_solicitud_pendiente || !c.estado,
          onClick: () => onBaja(c),
        },
      ]} />
    ),
  },
]

interface AccionesSolicitud {
  onDocumento: (solicitud: SolicitudCargaFamiliar) => void
  onRetirar:   (id: number) => void
}

/** Lo que pidió y en qué quedó, con el motivo si se rechazó. */
export const getSolicitudesCargaColumns = (
  { onDocumento, onRetirar }: AccionesSolicitud,
): DataTableColumn<SolicitudCargaFamiliar>[] => [
  {
    accessor: 'tipo',
    title: 'Solicitud',
    width: 170,
    render: (s) => (
      <div>
        <Text size="sm" fw={500}>{s.tipo_etiqueta}</Text>
        <Text size="xs" c="dimmed">{formatFecha(s.created_at)}</Text>
      </div>
    ),
  },
  {
    accessor: 'nombres',
    title: 'Familiar',
    // En una baja la solicitud no trae datos: se leen de la carga.
    render: (s) => (
      <div>
        <Text size="sm">{`${s.apellidos ?? s.carga?.apellidos ?? ''} ${s.nombres ?? s.carga?.nombres ?? ''}`.trim()}</Text>
        {s.cedula && <Text size="xs" c="dimmed" ff="monospace">CI: {s.cedula}</Text>}
      </div>
    ),
  },
  {
    accessor: 'estado',
    title: 'Estado',
    width: 260,
    render: (s) => (
      <div>
        <StatusBadge tone={TONO_SOLICITUD_CARGA[s.estado]}>{s.estado_etiqueta}</StatusBadge>
        {s.estado === 'rechazada' && s.motivo_rechazo && (
          <Text size="xs" c="dimmed" mt={2}>{s.motivo_rechazo}</Text>
        )}
      </div>
    ),
  },
  {
    accessor: 'acciones',
    title: '',
    width: 50,
    render: (s) => (
      <TableActions actions={[
        {
          label: 'Ver documento',
          icon: <IconFileTypePdf size={14} />,
          hidden: !s.tiene_documento,
          onClick: () => onDocumento(s),
        },
        {
          label: 'Retirar',
          icon: <IconArrowBackUp size={14} />,
          color: 'red',
          hidden: !s.puede_retirar,
          onClick: () => confirmar({
            title: 'Retirar la solicitud',
            message: 'Talento Humano ya no la revisará. Si hace falta, puede presentar otra.',
            confirmLabel: 'Retirar',
            destructiva: true,
            onConfirm: () => onRetirar(s.id),
          }),
        },
      ]} />
    ),
  },
]
