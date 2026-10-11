'use client'

import { Alert, Text } from '@mantine/core'
import { IconBan } from '@tabler/icons-react'
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
  // La restitución también crea el vínculo, con el nombramiento que tenía.
  const esIngreso = m.clase === 'ingreso' || m.clase === 'restitucion'

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
              // Para qué se da el provisional y el acta del de prueba (fase 3.2).
              ...(m.supuesto_provisional_etiqueta
                ? [{ label: 'Supuesto', value: m.supuesto_provisional_etiqueta }]
                : []),
              ...(m.acta_concurso_numero
                ? [{ label: 'Acta del concurso', value: `${m.acta_concurso_numero} · ${formatFecha(m.acta_concurso_fecha)}` }]
                : []),
              { label: 'N.º de contrato', value: m.numero_contrato },
              { label: 'Remuneración', value: dinero(m.remuneracion_propuesta) },
              {
                label: 'Marca asistencia',
                value: m.puede_marcar == null ? null : (m.puede_marcar ? 'Sí' : 'No'),
              },
            ]} />
          </BloqueDetalle>
        )}

        {/* La sentencia de la restitución [TH N15; fase 2.6]. */}
        {m.clase === 'restitucion' && (
          <BloqueDetalle>
            <SectionHeading title="Sentencia" mb="xs" />
            <DetailList items={[
              { label: 'Causa', value: m.causa_judicial_numero },
              { label: 'Unidad judicial', value: m.unidad_judicial },
              { label: 'Sentencia', value: formatFecha(m.fecha_sentencia) },
              { label: 'Ejecutoría', value: formatFecha(m.fecha_ejecutoria) },
              {
                label: 'Remuneraciones dejadas de percibir',
                value: dinero(m.remuneraciones_dejadas_de_percibir),
              },
            ]} />
          </BloqueDetalle>
        )}

        <BloqueDetalle>
          <SectionHeading title="Respaldos" mb="xs" />
          <DetailList items={[
            // El informe del traslado (fase 3.1), solo donde lo hay. En el
            // reintegro del ascendido es el de su evaluación (fase 3.3).
            ...(m.informe_uath_numero
              ? [{
                  label: m.clase === 'reintegro' ? 'Informe de la evaluación' : 'Informe técnico de la UATH',
                  value: m.informe_uath_numero,
                }]
              : []),
            // La credencial y la posesión de una autoridad (fase 4.1).
            ...(m.credencial_cne_numero
              ? [{ label: 'Credencial del CNE', value: `${m.credencial_cne_numero} · ${formatFecha(m.credencial_cne_fecha)}` }]
              : []),
            ...(m.acta_posesion_numero
              ? [{ label: 'Acta de posesión', value: m.acta_posesion_numero }]
              : []),
            // El acta del ascenso, que no es ingreso (fase 3.3).
            ...(!esIngreso && m.acta_concurso_numero
              ? [{ label: 'Acta del concurso', value: `${m.acta_concurso_numero} · ${formatFecha(m.acta_concurso_fecha)}` }]
              : []),
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

        {/* Por qué se anuló. Se pide al anular desde el 2026-09-27; en lo anulado
            antes no hay nada que mostrar, así que el bloque no se monta. */}
        {m.estado === 'anulada' && m.motivo_anulacion && (
          <Alert variant="light" color="red" icon={<IconBan size={16} />}>
            <Text size="sm" fw={600} mb={2}>Motivo de la anulación</Text>
            <Text size="sm">{m.motivo_anulacion}</Text>
          </Alert>
        )}

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
