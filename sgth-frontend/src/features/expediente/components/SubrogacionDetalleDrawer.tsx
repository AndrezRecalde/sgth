'use client'

import { Stack, Text } from '@mantine/core'
import { DetailList, SectionHeading, SgthDrawer, StatusBadge } from '@/components/ui'
import { formatFecha } from '@/lib/fecha'
import type { Subrogacion } from '@/types/api'
import {
  ESTADO_LABELS, MOTIVO_LABELS, TIPO_LABELS, TONO_SUBROGACION,
} from '../utils/subrogaciones'
import { ESTADO_LABELS as ESTADO_ACCION_LABELS } from '../utils/estadoAccionPersonal'
import type { EstadoAccionPersonal } from '@/types/api'

/**
 * El detalle de una fila.
 *
 * Existe porque tres datos que la subrogación guarda no se veían en ninguna
 * parte: el número de resolución, la observación y quién la registró —este
 * último se escribía desde la primera migración y no salía en ningún listado,
 * así que no había forma de saberlo sin abrir la base de datos—.
 *
 * Van en un cajón y no en columnas nuevas: la tabla ya tiene siete y estos tres
 * se consultan de uno en uno, que es exactamente para lo que sirve un
 * `SgthDrawer` (regla 06).
 */
export function SubrogacionDetalleDrawer({
  subrogacion,
  opened,
  onClose,
}: {
  subrogacion: Subrogacion | null
  opened: boolean
  onClose: () => void
}) {
  const nombre = (s?: { nombre?: string; apellido?: string } | null) =>
    s ? [s.apellido, s.nombre].filter(Boolean).join(' ') : null

  const etiquetaAccion = (estado?: string | null) =>
    (estado && ESTADO_ACCION_LABELS[estado as EstadoAccionPersonal])?.toLowerCase()
      ?? 'trámite'

  return (
    <SgthDrawer
      opened={opened}
      onClose={onClose}
      title="Detalle de la subrogación"
      description={subrogacion?.puesto_subrogado?.cargo?.nombre ?? undefined}
    >
      {subrogacion && (
        <Stack gap="lg">
          <DetailList
            columnas={1}
            items={[
              {
                label: 'Figura',
                value: <StatusBadge>{TIPO_LABELS[subrogacion.tipo]}</StatusBadge>,
              },
              {
                label: 'Estado',
                value: (
                  <StatusBadge tone={TONO_SUBROGACION[subrogacion.estado]}>
                    {ESTADO_LABELS[subrogacion.estado]}
                  </StatusBadge>
                ),
              },
              {
                label: subrogacion.tipo === 'encargo' ? 'Encargado' : 'Subrogante',
                value: nombre(subrogacion.subrogante),
              },
              {
                label: 'Titular subrogado',
                value: nombre(subrogacion.subrogado),
              },
              { label: 'Unidad administrativa', value: subrogacion.unidad_administrativa?.nombre },
              { label: 'Puesto', value: subrogacion.puesto_subrogado?.cargo?.nombre },
              {
                label: 'Período',
                value: `${formatFecha(subrogacion.fecha_inicio)} — ${formatFecha(subrogacion.fecha_fin)}`,
              },
              { label: 'Motivo', value: MOTIVO_LABELS[subrogacion.motivo] },
            ]}
          />

          <div>
            <SectionHeading title="Respaldo" mb="xs" />
            <DetailList
              columnas={1}
              items={[
                { label: 'Número de resolución', value: subrogacion.resolucion_numero },
                {
                  // El correlativo AP-AAAA-NNNN solo existe desde que la acción
                  // se registra; antes hay acción pero no número, y decirlo así
                  // es lo que explica por qué la subrogación sigue pendiente.
                  label: 'Acción de personal',
                  value: subrogacion.movimiento_personal?.codigo_registro
                    ?? (subrogacion.movimiento_personal
                      ? `Sin correlativo todavía — la acción está en ${etiquetaAccion(subrogacion.movimiento_personal.estado)}`
                      : null),
                },
                {
                  label: 'Registró',
                  value: subrogacion.registrado_por_usuario?.nombre_completo,
                },
              ]}
            />
          </div>

          <div>
            <SectionHeading title="Observación" mb="xs" />
            <Text size="sm" c={subrogacion.observacion ? undefined : 'dimmed'} style={{ whiteSpace: 'pre-line' }}>
              {subrogacion.observacion || 'Sin observaciones.'}
            </Text>
          </div>
        </Stack>
      )}
    </SgthDrawer>
  )
}
