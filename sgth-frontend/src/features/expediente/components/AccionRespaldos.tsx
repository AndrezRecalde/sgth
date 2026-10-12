'use client'

import { Alert, Text } from '@mantine/core'
import { IconBan } from '@tabler/icons-react'
import { DetailList, SectionHeading } from '@/components/ui'
import { BloqueDetalle } from './BloqueDetalle'
import { etiquetaNombramiento } from '../utils/tipoNombramientoOptions'
import { dinero } from '../utils/dinero'
import type { MovimientoPersonal } from '@/types/api'
import { formatFecha } from '@/lib/fecha'

/** Cómo se nombra el acto que la origina (fase 6.1). */
const ACTO_DE_ORIGEN = { decreto: 'Decreto', acuerdo: 'Acuerdo', resolucion: 'Resolución' } as const

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

        {/* La sentencia de la restitución [TH N15; fase 2.6], o la
            providencia que ordena el reintegro del obrero (fase 4.5b). */}
        {m.clase === 'restitucion' && (
          <BloqueDetalle>
            <SectionHeading title={m.fecha_providencia ? 'Orden judicial' : 'Sentencia'} mb="xs" />
            <DetailList items={[
              { label: 'Causa', value: m.causa_judicial_numero },
              { label: 'Unidad judicial', value: m.unidad_judicial },
              ...(m.fecha_providencia
                ? [{ label: 'Providencia', value: `${formatFecha(m.fecha_providencia)} · reintegro inmediato, como medida cautelar` }]
                : [
                  { label: 'Sentencia', value: formatFecha(m.fecha_sentencia) },
                  { label: 'Ejecutoría', value: formatFecha(m.fecha_ejecutoria) },
                ]),
              // Al obrero se le calculan si la sentencia no las fija.
              ...(m.remuneraciones_dejadas_de_percibir != null || m.instrumento !== 'documento_ct'
                ? [{
                  label: 'Remuneraciones dejadas de percibir',
                  value: dinero(m.remuneraciones_dejadas_de_percibir),
                }]
                : []),
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
            // La licencia del Consejo: con o sin remuneración, y su prórroga (4.1b).
            ...(m.con_remuneracion != null
              ? [{ label: 'Remuneración', value: m.con_remuneracion ? 'Con remuneración' : 'Sin remuneración' }]
              : []),
            ...(m.prorroga_causa_etiqueta
              ? [{ label: 'Prórroga del Consejo', value: m.prorroga_causa_etiqueta }]
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
            // Lo que imprime el formato del Ministerio (fase 6.1): la base
            // legal —la guardada, o la de su clase—, la casilla y el acto
            // que la origina.
            ...(m.base_legal || m.base_legal_de_la_clase
              ? [{ label: 'Base legal', value: m.base_legal || m.base_legal_de_la_clase }]
              : []),
            ...(m.casilla ? [{ label: 'Casilla del formulario', value: m.casilla }] : []),
            ...(m.acto_origen_tipo
              ? [{
                  label: 'Acto que la origina',
                  value: `${ACTO_DE_ORIGEN[m.acto_origen_tipo]} N.º ${m.acto_origen_numero ?? '—'}`
                    + (m.acto_origen_fecha ? ` · ${formatFecha(m.acto_origen_fecha)}` : ''),
                }]
              : []),
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
