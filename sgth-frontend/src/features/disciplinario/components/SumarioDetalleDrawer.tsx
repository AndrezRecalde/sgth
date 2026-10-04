'use client'

import { Stack, Text } from '@mantine/core'
import {
  DetailList, SectionHeading, SgthDrawer, StatusBadge, type DetailItem,
} from '@/components/ui'
import {
  ESTADO_SUMARIO_LABELS,
  TIPO_FALTA_LABELS,
  TIPO_SANCION_LABELS,
  TONO_SUMARIO,
  nombreServidor,
} from '../utils/etiquetas'
import { formatFecha } from '@/lib/fecha'
import { ESTADO_LABELS, TONO_ACCION } from '@/features/expediente/utils/estadoAccionPersonal'
import type { Sumario } from '@/types/api'

const dolares = (v: number) =>
  `$${v.toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`

interface Props {
  opened: boolean
  onClose: () => void
  sumario: Sumario | null
}

/**
 * Detalle del sumario.
 *
 * El listado ya trae el registro completo —el `index` carga el modelo entero y
 * su sanción—, y la tabla solo podía enseñar seis columnas: el motivo recortado
 * a dos líneas, y ninguna de las fechas de los hitos ni un solo dato de la
 * sanción más allá de su tipo. Todo lo que se capturaba al abrir el sumario y
 * al resolverlo dejaba de ser visible en cuanto se guardaba.
 *
 * Es un cajón y no un modal porque acompaña a la lista, que sigue a la vista
 * (regla 06).
 */
export function SumarioDetalleDrawer({ opened, onClose, sumario }: Props) {
  return (
    <SgthDrawer
      opened={opened}
      onClose={onClose}
      title="Sumario administrativo"
      description={sumario ? nombreServidor(sumario.servidor) : undefined}
    >
      {sumario && <Contenido sumario={sumario} />}
    </SgthDrawer>
  )
}

function Contenido({ sumario }: { sumario: Sumario }) {
  const procedimiento: DetailItem[] = [
    {
      label: 'Estado',
      value: (
        <StatusBadge tone={TONO_SUMARIO[sumario.estado]}>
          {ESTADO_SUMARIO_LABELS[sumario.estado]}
        </StatusBadge>
      ),
    },
    { label: 'Cédula', value: sumario.servidor?.cedula },
    { label: 'Motivo', value: sumario.motivo, ancho: true },
  ]

  const hitos: DetailItem[] = [
    { label: 'Apertura', value: formatFecha(sumario.fecha_apertura) },
    {
      label: 'Notificación al sumariado',
      value: sumario.notificado_sn
        ? formatFecha(sumario.fecha_notificacion)
        : 'Sin notificar',
    },
    { label: 'Término del período de prueba', value: formatFecha(sumario.fecha_termino_prueba) },
    { label: 'Informe del instructor', value: formatFecha(sumario.fecha_informe) },
    { label: 'Resolución', value: formatFecha(sumario.fecha_resolucion) },
  ]

  const sancion = sumario.sancion
  const descuento = sancion?.descuento_referencial ?? null
  const accion = sancion?.movimiento_personal ?? null

  return (
    <Stack gap="lg">
      <DetailList items={procedimiento} columnas={2} />

      <div>
        <SectionHeading title="Hitos procesales" mb="xs" />
        <DetailList items={hitos} columnas={2} />
      </div>

      {sancion && (
        <div>
          <SectionHeading title="Sanción impuesta" mb="xs" />
          <DetailList
            columnas={2}
            items={[
              { label: 'Gravedad de la falta', value: TIPO_FALTA_LABELS[sancion.tipo_falta] },
              {
                label: 'Sanción',
                value: (
                  <StatusBadge
                    tone={sancion.tipo_sancion === 'destitucion' ? 'danger' : 'neutral'}
                  >
                    {TIPO_SANCION_LABELS[sancion.tipo_sancion]}
                  </StatusBadge>
                ),
              },
              // Solo la fila que corresponde a la sanción: una suspensión
              // enseñaba también «Multa: —».
              ...(sancion.tipo_sancion === 'multa' ? [{
                label: 'Multa',
                // Salía «5.00%»: el decimal viene de la base como texto.
                value: sancion.porcentaje_multa
                  ? `${Number(sancion.porcentaje_multa).toLocaleString('es-EC')} % de la remuneración`
                  : null,
              }] : []),
              ...(sancion.tipo_sancion === 'suspension' ? [{
                label: 'Suspensión',
                value: sancion.dias_suspension ? `${sancion.dias_suspension} días` : null,
              }] : []),
              { label: 'Surte efecto desde', value: formatFecha(sancion.fecha_efectiva) },
              ...(descuento ? [{
                label: 'Descuento referencial',
                value: descuento.monto !== null
                  ? `${dolares(descuento.monto)}${descuento.hasta ? ` · hasta el ${formatFecha(descuento.hasta)}` : ''}`
                  : 'Sin remuneración registrada',
              }] : []),
              ...(accion ? [{
                label: 'Acción de personal',
                value: (
                  <StatusBadge tone={TONO_ACCION[accion.estado]}>
                    {accion.codigo_registro
                      ? `${accion.codigo_registro} · ${ESTADO_LABELS[accion.estado]}`
                      : ESTADO_LABELS[accion.estado]}
                  </StatusBadge>
                ),
              }] : []),
              { label: 'Observaciones', value: sancion.observaciones, ancho: true },
            ]}
          />
          {descuento && (
            <Text size="xs" c="dimmed" mt="xs">
              Al registrarse la acción de personal se envía al jefe de Gestión
              Financiera, que aplica el descuento en el rol de pagos. El valor es
              una referencia.
            </Text>
          )}
        </div>
      )}
    </Stack>
  )
}
