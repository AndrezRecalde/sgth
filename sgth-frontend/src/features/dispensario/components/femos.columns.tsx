import { Stack, Text } from '@mantine/core'
import { IconEye } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import { StatusBadge, TableActions } from '@/components/ui'
import { formatFechaMes } from '@/lib/fecha'
import { APTITUD_OPTIONS, TIPO_FICHA_OPTIONS, TONO_APTITUD } from '../services/femoOptions'
import type { FichaSaludOcupacional } from '../services/femoService'

interface Acciones {
  onVer: (id: number) => void
}

const etiquetaTipo = (v: string) =>
  TIPO_FICHA_OPTIONS.find(o => o.value === v)?.label ?? v

/** Sin aptitud es un borrador: la evaluación sigue en curso. */
const etiquetaAptitud = (v?: string | null) =>
  v ? APTITUD_OPTIONS.find(o => o.value === v)?.label ?? v : 'Borrador'

const nombreDe = (f: FichaSaludOcupacional) =>
  f.servidor
    ? `${f.servidor.nombre} ${f.servidor.apellido}`
    : f.postulante
      ? `${f.postulante.nombres} ${f.postulante.apellidos}`
      : '—'

/** Las fichas FEMO del Dispensario. */
export function getFemosColumns(a: Acciones): DataTableColumn<FichaSaludOcupacional>[] {
  return [
    {
      accessor: 'fecha_evaluacion',
      title:    'Fecha',
      width:    110,
      render: (f) => <Text size="sm">{formatFechaMes(f.fecha_evaluacion)}</Text>,
    },
    {
      accessor: 'servidor',
      title:    'Servidor / Aspirante',
      // El puesto va debajo, como en la bandeja de solicitudes: en su propia
      // columna dejaba la tabla más ancha que la pantalla y escondía el menú
      // de acciones.
      render: (f) => (
        <Stack gap={0}>
          <Text size="sm" fw={500}>{nombreDe(f)}</Text>
          <Text size="xs" c="dimmed" ff="monospace">
            {f.servidor?.cedula ?? f.postulante?.cedula ?? ''}
          </Text>
          {f.puesto_trabajo && <Text size="xs" c="dimmed">{f.puesto_trabajo}</Text>}
        </Stack>
      ),
    },
    {
      accessor: 'tipo_ficha',
      title:    'Tipo',
      width:    110,
      render: (f) => <StatusBadge>{etiquetaTipo(f.tipo_ficha)}</StatusBadge>,
    },
    {
      accessor: 'aptitud',
      title:    'Aptitud',
      width:    160,
      render: (f) => (
        <StatusBadge tone={TONO_APTITUD[f.aptitud ?? ''] ?? 'neutral'}>
          {etiquetaAptitud(f.aptitud)}
        </StatusBadge>
      ),
    },
    {
      accessor: 'evaluador',
      title:    'Evaluador',
      width:    150,
      render: (f) => {
        const ev = f.evaluador?.servidor
        // Sin «Dr.» delante: no todo evaluador es doctor, ni varón.
        return <Text size="sm">{ev ? `${ev.nombre} ${ev.apellido}` : '—'}</Text>
      },
    },
    {
      accessor: 'acciones',
      title:    '',
      width:    50,
      render: (f) => (
        <TableActions actions={[
          { label: 'Ver detalle', icon: <IconEye size={14} />, onClick: () => a.onVer(f.id) },
        ]} />
      ),
    },
  ]
}
