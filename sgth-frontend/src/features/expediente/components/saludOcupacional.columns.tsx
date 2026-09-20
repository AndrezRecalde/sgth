import { Button, Stack, Text } from '@mantine/core'
import { IconDownload } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import { StatusBadge } from '@/components/ui'
import { formatFechaHora } from '@/lib/fecha'
import {
  DICTAMEN_LABELS,
  ESTADO_SOLICITUD_LABELS,
  TIPO_EVENTO_OPTIONS,
  TONO_DICTAMEN,
  TONO_ESTADO_SOLICITUD,
  type SolicitudCertificacion,
} from '@/features/dispensario/services/solicitudCertificacionService'

type Handlers = {
  /** `null` cuando no hay ninguna descarga en curso. */
  descargandoId: number | null
  onDescargar: (solicitud: SolicitudCertificacion) => void
}

const etiquetaTipo = (tipo: string) =>
  TIPO_EVENTO_OPTIONS.find((o) => o.value === tipo)?.label ?? tipo

export const getSaludOcupacionalColumns = (
  { descargandoId, onDescargar }: Handlers,
): DataTableColumn<SolicitudCertificacion>[] => [
  {
    accessor: 'tipo_evento',
    title: 'Tipo de evaluación',
    render: (s) => <StatusBadge>{etiquetaTipo(s.tipo_evento)}</StatusBadge>,
  },
  {
    accessor: 'created_at',
    title: 'Fecha de solicitud',
    width: 130,
    render: (s) => (
      <Text size="sm">{formatFechaHora(s.created_at, { conHora: false })}</Text>
    ),
  },
  {
    accessor: 'estado',
    title: 'Estado',
    width: 140,
    render: (s) => (
      <Stack gap={4}>
        <StatusBadge tone={TONO_ESTADO_SOLICITUD[s.estado] ?? 'neutral'}>
          {ESTADO_SOLICITUD_LABELS[s.estado] ?? s.estado}
        </StatusBadge>
        {s.dictamen && (
          <StatusBadge size="xs" tone={TONO_DICTAMEN[s.dictamen] ?? 'neutral'}>
            {DICTAMEN_LABELS[s.dictamen] ?? s.dictamen}
          </StatusBadge>
        )}
      </Stack>
    ),
  },
  {
    accessor: 'acciones',
    title: '',
    width: 90,
    render: (s) =>
      s.ficha_femo_id ? (
        <Button
          size="xs"
          variant="light"
          leftSection={<IconDownload size={13} />}
          loading={descargandoId === s.id}
          disabled={descargandoId !== null && descargandoId !== s.id}
          onClick={() => onDescargar(s)}
        >
          PDF
        </Button>
      ) : (
        <Text size="xs" c="dimmed">—</Text>
      ),
  },
]
