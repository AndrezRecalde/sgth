'use client'

import { DetailList, SectionHeading } from '@/components/ui'
import { BloqueDetalle } from './BloqueDetalle'
import { etiquetaNombramiento } from '../utils/tipoNombramientoOptions'
import { dinero } from '../utils/dinero'
import type { MovimientoPersonal } from '@/types/api'
import { formatFecha } from '@/lib/fecha'

/**
 * Con qué se respalda la acción: el instrumento que nace —solo en un ingreso—,
 * la resolución y el dictamen médico, y quiénes firmaron.
 */
export function AccionRespaldos({ m }: { m: MovimientoPersonal }) {
  const esIngreso = m.tipo_movimiento === 'ingreso'

  return (
    <>
        {/* Los datos de la contratación solo existen en el ingreso, que es la
            única acción que da origen a un contrato. En un traspaso o una
            comisión salían todos en blanco y hacían creer que faltaba algo por
            llenar. Resolución y dictamen sí aplican a cualquier acción, así que
            se quedan fuera de ese bloque. */}
        {esIngreso && (
          <BloqueDetalle>
            <SectionHeading title="Datos de la contratación" mb="xs" />
            <DetailList items={[
              {
                label: 'Nombramiento',
                value: etiquetaNombramiento(m.tipo_nombramiento_propuesto),
              },
              { label: 'N.º de contrato', value: m.numero_contrato },
              { label: 'Remuneración', value: dinero(m.remuneracion_propuesta) },
              {
                label: 'Marca asistencia',
                value: m.puede_marcar == null ? null : (m.puede_marcar ? 'Sí' : 'No'),
              },
            ]} />
          </BloqueDetalle>
        )}

        <BloqueDetalle>
          <SectionHeading title="Respaldos" mb="xs" />
          <DetailList items={[
            { label: 'N.º de resolución', value: m.resolucion_numero },
            {
              label: 'Dictamen médico',
              value: m.requiere_dictamen_medico
                ? (m.solicitud_certificacion?.dictamen ?? 'Pendiente')
                : 'No requiere',
            },
            ...(m.caucionado
              ? [
                  { label: 'Caución N.º', value: m.caucion_numero },
                  { label: 'Fecha de caución', value: formatFecha(m.caucion_fecha) },
                ]
              : []),
          ]} />
        </BloqueDetalle>

        {(m.firmante_autoridad_nombre || m.firmante_th_nombre) && (
          <BloqueDetalle>
            <SectionHeading title="Firmantes sellados" mb="xs" />
            <DetailList items={[
              {
                label: m.firmante_autoridad_cargo ?? 'Autoridad',
                value: m.firmante_autoridad_nombre,
              },
              {
                label: m.firmante_th_cargo ?? 'Talento Humano',
                value: m.firmante_th_nombre,
              },
            ]} />
          </BloqueDetalle>
        )}
    </>
  )
}
