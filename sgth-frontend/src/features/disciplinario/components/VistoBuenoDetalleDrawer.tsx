'use client'

import { Stack } from '@mantine/core'
import {
  DetailList, SectionHeading, SgthDrawer, StatusBadge, type DetailItem,
} from '@/components/ui'
import {
  CAUSAL_LABELS,
  ESTADO_VISTO_BUENO_LABELS,
  TONO_VISTO_BUENO,
  nombreServidor,
  referenciaLegal,
} from '../utils/etiquetas'
import { formatFecha } from '@/lib/fecha'
import { ESTADO_LABELS } from '@/features/expediente/utils/estadoAccionPersonal'
import { ResolucionInspectorDocumento } from './ResolucionInspectorDocumento'
import type { VistoBueno } from '@/types/api'

interface Props {
  opened: boolean
  onClose: () => void
  tramite: VistoBueno | null
}

/**
 * Detalle del trámite de visto bueno.
 *
 * El listado ya trae el registro completo, y la tabla no tenía dónde enseñar el
 * fundamento de hecho, la inspectoría, el nombre del Inspector ni —lo que más
 * importa— el detalle de la resolución, que es el documento que sustenta la
 * cesación que viene después.
 */
export function VistoBuenoDetalleDrawer({ opened, onClose, tramite }: Props) {
  return (
    <SgthDrawer
      opened={opened}
      onClose={onClose}
      title="Trámite de visto bueno"
      description={tramite ? nombreServidor(tramite.servidor) : undefined}
    >
      {tramite && <Contenido tramite={tramite} />}
    </SgthDrawer>
  )
}

function Contenido({ tramite }: { tramite: VistoBueno }) {
  const solicitud: DetailItem[] = [
    {
      label: 'Estado',
      value: (
        <StatusBadge tone={TONO_VISTO_BUENO[tramite.estado]}>
          {ESTADO_VISTO_BUENO_LABELS[tramite.estado]}
        </StatusBadge>
      ),
    },
    { label: 'Cédula', value: tramite.servidor?.cedula },
    { label: 'Causal invocada', value: CAUSAL_LABELS[tramite.causal], ancho: true },
    { label: 'Referencia legal', value: referenciaLegal(tramite.causal), ancho: true },
    { label: 'Fundamento de hecho', value: tramite.hechos, ancho: true },
  ]

  const tramiteMdt: DetailItem[] = [
    {
      label: 'Número de trámite',
      value: tramite.numero_tramite_mdt,
    },
    { label: 'Presentado el', value: formatFecha(tramite.fecha_solicitud) },
    { label: 'Inspectoría', value: tramite.inspectoria },
    { label: 'Inspector', value: tramite.inspector_nombre },
    { label: 'Notificado al trabajador', value: formatFecha(tramite.fecha_notificacion) },
  ]

  const cesacion = tramite.movimiento_personal

  return (
    <Stack gap="lg">
      <DetailList items={solicitud} columnas={2} />

      <div>
        <SectionHeading title="Trámite ante el Ministerio del Trabajo" mb="xs" />
        <DetailList items={tramiteMdt} columnas={2} />
      </div>

      {tramite.resolucion_detalle && (
        <div>
          <SectionHeading title="Resolución del Inspector" mb="xs" />
          <DetailList
            columnas={1}
            items={[
              { label: 'Fecha', value: formatFecha(tramite.fecha_resolucion) },
              { label: 'Detalle', value: tramite.resolucion_detalle, ancho: true },
            ]}
          />
          <ResolucionInspectorDocumento tramite={tramite} />
        </div>
      )}

      {tramite.impugnacion_referencia && (
        <div>
          <SectionHeading title="Impugnación" mb="xs" />
          <DetailList
            columnas={2}
            items={[
              { label: 'Juicio o causa', value: tramite.impugnacion_referencia },
              { label: 'Fecha', value: formatFecha(tramite.fecha_impugnacion) },
            ]}
          />
        </div>
      )}

      {cesacion && (
        <div>
          <SectionHeading title="Cesación de funciones generada" mb="xs" />
          <DetailList
            columnas={2}
            items={[
              {
                label: 'Correlativo',
                value: cesacion.codigo_registro
                  ? <StatusBadge variant="outline">{cesacion.codigo_registro}</StatusBadge>
                  : 'Sin registrar',
              },
              {
                label: 'Estado de la acción',
                // Salía en crudo («registrada»).
                value: cesacion.estado ? ESTADO_LABELS[cesacion.estado] : null,
              },
            ]}
          />
        </div>
      )}
    </Stack>
  )
}
