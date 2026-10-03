'use client'

import { Stack, Text } from '@mantine/core'
import { IconFileCertificate } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import { StatusBadge, TableActions } from '@/components/ui'
import { formatFechaMes } from '@/lib/fecha'
import {
  DICTAMEN_LABELS,
  ESTADO_SOLICITUD_LABELS,
  TONO_DICTAMEN,
} from '../services/solicitudCertificacionService'
import {
  ESTADO_COBERTURA_LABELS,
  TONO_ESTADO_COBERTURA,
  type FilaCobertura,
} from '../services/coberturaCertificacionService'

type Columna = DataTableColumn<FilaCobertura>

/*
| El tablero de cobertura: una fila por servidor activo.
|
| No reutiliza `solicitudes-certificacion.columns.tsx` a propósito: aquello
| describe un trámite y esto describe a una persona frente a una obligación.
| Compartir columnas obligaría a que las dos tablas hablaran del mismo
| registro, y no lo hacen.
*/
interface AccionesCobertura {
  /** `null` cuando no hay ninguna descarga en curso. */
  descargandoId: number | null
  onDescargarCertificado: (fila: FilaCobertura) => void
}

export function getCoberturaColumns(acciones: AccionesCobertura): Columna[] {
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
      width: 190,
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
      width: 170,
      // El vencimiento va aquí y no en su propia columna: es un dato de la
      // misma evaluación, y con siete columnas la tabla no cabía en la
      // pantalla y el estado se cortaba en el borde.
      render: (f) => {
        if (!f.fecha_evaluacion) {
          return <Text size="sm" c="dimmed">Nunca evaluado</Text>
        }

        const vencida = f.estado_cobertura === 'vencida'
        return (
          <Stack gap={4}>
            <Text size="sm">{formatFechaMes(f.fecha_evaluacion)}</Text>
            {f.ultimo_dictamen && (
              <StatusBadge size="xs" tone={TONO_DICTAMEN[f.ultimo_dictamen] ?? 'neutral'}>
                {DICTAMEN_LABELS[f.ultimo_dictamen] ?? f.ultimo_dictamen}
              </StatusBadge>
            )}
            {f.vence_el && (
              <Text size="xs" c={vencida ? 'red' : 'dimmed'} fw={vencida ? 600 : undefined}>
                {vencida ? 'Venció' : 'Vence'} el {formatFechaMes(f.vence_el)}
              </Text>
            )}
            {/* Las restricciones condicionan el puesto: es el dato
                administrativo que el Dispensario sí entrega a RRHH. */}
            {f.restricciones && (
              <Text size="xs" c="dimmed" lineClamp={2}>{f.restricciones}</Text>
            )}
          </Stack>
        )
      },
    },
    {
      accessor: 'estado_cobertura',
      title: 'Estado',
      width: 150,
      render: (f) => (
        <Stack gap={4}>
          <StatusBadge tone={TONO_ESTADO_COBERTURA[f.estado_cobertura]}>
            {ESTADO_COBERTURA_LABELS[f.estado_cobertura]}
          </StatusBadge>
          {/* Sin esto, una fila vencida invita a pedir una evaluación que ya
              está pedida, y `storeLote` la omitiría en silencio. */}
          {f.solicitud_activa_estado && (
            <>
              <StatusBadge size="xs" tone="info">
                Solicitada · {ESTADO_SOLICITUD_LABELS[f.solicitud_activa_estado]
                  ?? f.solicitud_activa_estado}
              </StatusBadge>
              {f.solicitud_activa_fecha_limite && (
                <Text size="xs" c="dimmed">
                  hasta {formatFechaMes(f.solicitud_activa_fecha_limite)}
                </Text>
              )}
            </>
          )}
        </Stack>
      ),
    },
    {
      accessor: 'acciones',
      title: '',
      width: 50,
      render: (f) => (
        <TableActions
          actions={[{
            label: 'Certificado de aptitud',
            icon: <IconFileCertificate size={14} />,
            // Hace falta la última evaluación Y su ficha: sin acto médico
            // firmado el API responde 422.
            hidden: !f.ultima_solicitud_id || !f.ultima_ficha_id,
            disabled: acciones.descargandoId !== null,
            onClick: () => acciones.onDescargarCertificado(f),
          }]}
        />
      ),
    },
  ]
}
