'use client'

import { Stack } from '@mantine/core'
import { DetailList, SectionHeading, SgthDrawer, StatusBadge } from '@/components/ui'
import { formatFecha } from '@/lib/fecha'
import {
  DICTAMEN_LABELS, ESTADO_SOLICITUD_LABELS, TONO_DICTAMEN, TONO_ESTADO_SOLICITUD,
} from '@/features/dispensario/services/solicitudCertificacionOptions'
import { usePostulantes } from '../hooks/useConvocatoria'
import { ESTADO_POSTULANTE_OPTIONS, TONO_POSTULANTE } from '../services/convocatoriaService'
import { ESTADO_CIVIL_OPTIONS, GENERO_OPTIONS, etiquetaDe } from '../constants/postulante'
import { DocumentosPostulante } from './DocumentosPostulante'
import { nombreCandidato } from './RankingCandidatoCard'

interface Props {
  convocatoriaId: number
  /** `null` cierra el panel. */
  postulanteId:   number | null
  onClose:        () => void
  puedeGestionar: boolean
}

const pts = (n?: number | string | null) => (n == null ? '—' : `${Number(n).toFixed(2)} pts`)

/**
 * El perfil del candidato en un panel lateral (decisión 4 de TH, 2026-10-05).
 * «Ver perfil» llevaba a una página que no existía. El candidato se toma del
 * listado ya cargado, así los documentos que se suben aparecen sin recargar.
 */
export function PerfilPostulanteDrawer({ convocatoriaId, postulanteId, onClose, puedeGestionar }: Props) {
  const { data: postulantes = [] } = usePostulantes(convocatoriaId)
  const p = postulantes.find((x) => x.id === postulanteId) ?? null
  const ev = p?.evaluacion
  const sol = p?.solicitud_certificacion

  return (
    <SgthDrawer
      opened={postulanteId !== null}
      onClose={onClose}
      title={p ? nombreCandidato(p) : 'Candidato'}
      description={p ? `Cédula ${p.cedula}` : undefined}
      ancho="lg"
    >
      {p && (
        <Stack gap="lg">
          <DetailList items={[
            { label: 'Estado', value: (
              <StatusBadge tone={TONO_POSTULANTE[p.estado] ?? 'neutral'}>
                {etiquetaDe(ESTADO_POSTULANTE_OPTIONS, p.estado)}
              </StatusBadge>
            ) },
            { label: 'Inscripción', value: formatFecha(p.fecha_inscripcion) },
            { label: 'Correo', value: p.correo },
            { label: 'Teléfono', value: p.telefono || '—' },
            { label: 'Género', value: etiquetaDe(GENERO_OPTIONS, p.genero) },
            { label: 'Estado civil', value: etiquetaDe(ESTADO_CIVIL_OPTIONS, p.estado_civil) },
            { label: 'Fecha de nacimiento', value: formatFecha(p.fecha_nacimiento) },
            { label: 'Tipo de sangre', value: p.tipo_sangre || '—' },
          ]} />

          <SectionHeading title="Evaluación" />
          {ev ? (
            <DetailList columnas={3} items={[
              { label: 'Méritos', value: pts(ev.puntaje_meritos) },
              { label: 'Oposición', value: pts(ev.puntaje_oposicion) },
              { label: 'Total', value: pts(ev.puntaje_total) },
            ]} />
          ) : (
            <DetailList items={[{ label: 'Calificación', value: 'Todavía no ha sido calificado.', ancho: true }]} />
          )}

          {sol && (
            <>
              <SectionHeading title="Evaluación médica" />
              <DetailList items={[
                { label: 'Solicitud', value: (
                  <StatusBadge tone={TONO_ESTADO_SOLICITUD[sol.estado] ?? 'neutral'}>
                    {ESTADO_SOLICITUD_LABELS[sol.estado] ?? sol.estado}
                  </StatusBadge>
                ) },
                { label: 'Dictamen', value: sol.dictamen ? (
                  <StatusBadge tone={TONO_DICTAMEN[sol.dictamen] ?? 'neutral'}>
                    {DICTAMEN_LABELS[sol.dictamen] ?? sol.dictamen}
                  </StatusBadge>
                ) : 'Pendiente' },
              ]} />
            </>
          )}

          <SectionHeading title="Documentos" />
          <DocumentosPostulante convocatoriaId={convocatoriaId} postulante={p} puedeGestionar={puedeGestionar} />
        </Stack>
      )}
    </SgthDrawer>
  )
}
