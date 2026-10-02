'use client'

import { Stack, Text } from '@mantine/core'
import type { DataTableColumn } from 'mantine-datatable'
import { StatusBadge } from '@/components/ui'
import { formatFechaMes } from '@/lib/fecha'
import {
  DICTAMEN_LABELS,
  TONO_DICTAMEN,
} from '../services/solicitudCertificacionService'
import {
  ESTADO_COBERTURA_LABELS,
  TONO_ESTADO_COBERTURA,
  type FilaCobertura,
} from '../services/coberturaCertificacionService'

type Columna = DataTableColumn<FilaCobertura>

const ESTADO_SOLICITUD_ACTIVA: Record<string, string> = {
  pendiente:  'Pendiente',
  en_proceso: 'En proceso',
}

/*
| El tablero de cobertura: una fila por servidor activo.
|
| No reutiliza `solicitudes-certificacion.columns.tsx` a propósito. Aquello
| describe un trámite —tipo de evento, origen, quién lo pidió, fecha límite— y
| esto describe a una persona frente a una obligación. Compartir columnas
| obligaría a que las dos tablas hablaran del mismo registro, y no lo hacen.
*/
export function getCoberturaColumns(): Columna[] {
  return [
    {
      accessor: 'servidor',
      title: 'Servidor',
      render: (f) => (
        <Stack gap={0}>
          <Text size="sm" fw={500}>{f.nombre_completo}</Text>
          <Text size="xs" c="dimmed" ff="monospace">{f.cedula}</Text>
        </Stack>
      ),
    },
    {
      accessor: 'unidad',
      title: 'Unidad y cargo',
      width: 220,
      render: (f) => (
        <Stack gap={0}>
          <Text size="sm">{f.unidad ?? 'Sin unidad asignada'}</Text>
          {f.cargo && <Text size="xs" c="dimmed">{f.cargo}</Text>}
        </Stack>
      ),
    },
    {
      accessor: 'ultima_evaluacion',
      title: 'Última evaluación',
      width: 200,
      render: (f) => {
        if (!f.fecha_evaluacion) {
          return <Text size="sm" c="dimmed">Nunca evaluado</Text>
        }

        return (
          <Stack gap={4}>
            <Text size="sm">{formatFechaMes(f.fecha_evaluacion)}</Text>
            {f.ultimo_dictamen && (
              <StatusBadge size="xs" tone={TONO_DICTAMEN[f.ultimo_dictamen] ?? 'neutral'}>
                {DICTAMEN_LABELS[f.ultimo_dictamen] ?? f.ultimo_dictamen}
              </StatusBadge>
            )}
            {/* Las restricciones son lo que condiciona el puesto: es el dato
                administrativo que el Dispensario sí entrega a RRHH. */}
            {f.restricciones && (
              <Text size="xs" c="dimmed" lineClamp={2}>{f.restricciones}</Text>
            )}
          </Stack>
        )
      },
    },
    {
      accessor: 'vence_el',
      title: 'Vence',
      width: 110,
      render: (f) =>
        f.vence_el
          ? (
              <Text
                size="sm"
                c={f.estado_cobertura === 'vencida' ? 'red' : undefined}
                fw={f.estado_cobertura === 'vencida' ? 600 : undefined}
              >
                {formatFechaMes(f.vence_el)}
              </Text>
            )
          : <Text size="sm" c="dimmed">—</Text>,
    },
    {
      accessor: 'estado_cobertura',
      title: 'Estado',
      width: 140,
      render: (f) => (
        <StatusBadge tone={TONO_ESTADO_COBERTURA[f.estado_cobertura]}>
          {ESTADO_COBERTURA_LABELS[f.estado_cobertura]}
        </StatusBadge>
      ),
    },
    {
      accessor: 'solicitud_activa',
      title: 'Ya solicitada',
      width: 130,
      // Sin esta columna, una fila vencida invita a pedir una evaluación que
      // ya está pedida, y `storeLote` la omitiría en silencio.
      render: (f) =>
        f.solicitud_activa_estado
          ? (
              <Stack gap={0}>
                <StatusBadge size="xs" tone="info">
                  {ESTADO_SOLICITUD_ACTIVA[f.solicitud_activa_estado]
                    ?? f.solicitud_activa_estado}
                </StatusBadge>
                {f.solicitud_activa_fecha_limite && (
                  <Text size="xs" c="dimmed">
                    hasta {formatFechaMes(f.solicitud_activa_fecha_limite)}
                  </Text>
                )}
              </Stack>
            )
          : <Text size="sm" c="dimmed">—</Text>,
    },
  ]
}
