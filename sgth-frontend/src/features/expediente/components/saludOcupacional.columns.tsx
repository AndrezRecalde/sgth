import { Stack, Text } from '@mantine/core'
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

const etiquetaTipo = (tipo: string) =>
  TIPO_EVENTO_OPTIONS.find((o) => o.value === tipo)?.label ?? tipo

/*
| El historial de evaluaciones tal como lo ve Talento Humano.
|
| Sin columna de descarga: el PDF de la ficha es el formulario 028 del MSP
| completo —motivo de consulta, antecedentes, examen físico y diagnóstico
| CIE-10—, y eso es historia clínica. Lo que el expediente administrativo
| recibe es la aptitud y sus restricciones, que es el acuerdo con la UATH del
| 2026-09-26 y lo que se pinta en la última columna.
|
| El botón existía y nunca funcionó: `fichas-sso/{id}/pdf` pide
| `role:medico|admin-dispensario`, así que a Talento Humano le respondía 403 y
| el `catch` del hook lo mostraba como «No se pudo generar el PDF». Quien
| evalúa sigue teniéndolo en Salud Ocupacional y en el detalle de la ficha.
*/
export const getSaludOcupacionalColumns =
  (): DataTableColumn<SolicitudCertificacion>[] => [
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
      <StatusBadge tone={TONO_ESTADO_SOLICITUD[s.estado] ?? 'neutral'}>
        {ESTADO_SOLICITUD_LABELS[s.estado] ?? s.estado}
      </StatusBadge>
    ),
  },
  {
    accessor: 'dictamen',
    title: 'Aptitud',
    // El dictamen iba como una insignia pequeña debajo del estado, dentro de
    // su columna: es el resultado de la evaluación, no un detalle del trámite.
    // Y las restricciones, que son lo que condiciona el puesto, no se veían.
    render: (s) => {
      if (!s.dictamen) return <Text size="sm" c="dimmed">—</Text>

      const restricciones = s.ficha_salud_ocupacional?.restricciones

      return (
        <Stack gap={4}>
          <StatusBadge tone={TONO_DICTAMEN[s.dictamen] ?? 'neutral'}>
            {DICTAMEN_LABELS[s.dictamen] ?? s.dictamen}
          </StatusBadge>
          {restricciones && (
            <Text size="xs" c="dimmed" lineClamp={2}>{restricciones}</Text>
          )}
        </Stack>
      )
    },
  },
]
