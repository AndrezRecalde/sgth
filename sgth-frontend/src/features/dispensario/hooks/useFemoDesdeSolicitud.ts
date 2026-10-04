import { useEffect, useState } from 'react'
import { notificar } from '@/components/ui'
import { historiaClinicaService } from '../services/historiaClinicaService'
import type { SolicitudCertificacion } from '../services/solicitudCertificacionService'
import type { FichaSaludOcupacional } from '../services/femoService'
import type { FichaBaseForm } from '../schemas/femo.schema'
import type { useFemoWizardState } from './useFemoWizardState'

/**
 * Las columnas decimales del triaje llegan como texto («72.50»): el cast
 * `decimal:2` de Laravel las serializa así aunque el tipo diga número.
 */
const aNumero = (v: number | string | null | undefined): number | null =>
  v === null || v === undefined || v === '' ? null : Number(v)

type Wizard = ReturnType<typeof useFemoWizardState>

/**
 * Rellena el asistente con lo que ya sabe la solicitud: la persona, el tipo de
 * evaluación, el puesto y los signos vitales del triaje. Si la solicitud ya
 * tiene su ficha guardada como borrador, la carga tal como quedó.
 *
 * Devuelve `listo` cuando terminó: desde ahí cuenta lo que cambie el médico.
 */
export function useFemoDesdeSolicitud(
  solicitud: SolicitudCertificacion | undefined,
  fichaGuardada: FichaSaludOcupacional | undefined,
  wizard: Wizard,
) {
  const [puestoId, setPuestoId] = useState<number | null>(null)
  const [prellenada, setPrellenada] = useState(false)
  const [fichaCargada, setFichaCargada] = useState(false)

  useEffect(() => {
    if (!solicitud || prellenada) return

    // El puesto, de lo más específico a lo más general: el del aspirante va
    // primero por reclutamiento express, donde cada aspirante trae el suyo.
    const puesto =
      solicitud.postulante?.puesto ??
      solicitud.servidor?.puesto ??
      solicitud.convocatoria?.puesto ??
      null

    // La discapacidad que consta en el Expediente, ya marcada: antes el médico
    // la volvía a teclear. Se toma el porcentaje mayor si hay varias. Es solo
    // el punto de partida: el médico la confirma o la corrige en la ficha, y
    // eso no toca el Expediente.
    const porcentajes = (solicitud.servidor?.discapacidades ?? []).map(d => Number(d.porcentaje))
    const discapacidad = solicitud.servidor?.tiene_discapacidad
      ? {
          grupo_discapacidad:      true,
          porcentaje_discapacidad: porcentajes.length > 0 ? String(Math.max(...porcentajes)) : null,
        }
      : {}

    wizard.setFichaData(prev => ({
      ...prev,
      ...discapacidad,
      tipo_ficha:     solicitud.tipo_evento as FichaBaseForm['tipo_ficha'],
      numero_archivo: solicitud.cedula_paciente,
      servidor_id:    solicitud.servidor?.id ?? null,
      postulante_id:  solicitud.postulante?.id ?? null,
      ...(puesto ? {
        puesto_id:           puesto.id,
        puesto_trabajo:      puesto.cargo?.nombre ?? prev.puesto_trabajo,
        puesto_trabajo_ciuo: puesto.cargo?.codigo_ciuo ?? prev.puesto_trabajo_ciuo,
      } : {}),
    }))
    // eslint-disable-next-line react-hooks/set-state-in-effect
    setPuestoId(puesto?.id ?? null)

    const cv = solicitud.constantes_vitales
    if (cv) {
      wizard.setConstantesData({
        temperatura_c:           aNumero(cv.temperatura_c),
        presion_sistolica:       aNumero(cv.presion_sistolica),
        presion_diastolica:      aNumero(cv.presion_diastolica),
        frecuencia_cardiaca:     aNumero(cv.frecuencia_cardiaca),
        frecuencia_respiratoria: aNumero(cv.frecuencia_respiratoria),
        saturacion_oxigeno:      aNumero(cv.saturacion_oxigeno),
        peso_kg:                 aNumero(cv.peso_kg),
        talla_cm:                aNumero(cv.talla_cm),
        perimetro_abdominal_cm:  aNumero(cv.perimetro_abdominal_cm),
        imc:                     aNumero(cv.imc),
        glucosa:                 aNumero(cv.glucosa),
      })
    }

    // El candidato de ingreso todavía no tiene expediente: se le abre la
    // historia clínica por cédula. Solo la primera vez, no al retomar.
    if (solicitud.tipo_evento === 'ingreso' && !solicitud.ficha_femo_id) {
      historiaClinicaService.crearPorCedula({
        cedula_paciente: solicitud.cedula_paciente,
        tipo_paciente:   'candidato',
      }).catch(notificar.alFallar('No se pudo abrir la historia clínica del candidato'))
    }

    setPrellenada(true)
  }, [solicitud, prellenada, wizard])

  // «Continuar FEMO»: el borrador guardado manda sobre lo prellenado.
  useEffect(() => {
    if (!prellenada || !fichaGuardada || fichaCargada) return
    wizard.cargarDesdeFicha(fichaGuardada)
    // eslint-disable-next-line react-hooks/set-state-in-effect
    setFichaCargada(true)
  }, [prellenada, fichaGuardada, fichaCargada, wizard])

  const listo = prellenada && (!solicitud?.ficha_femo_id || fichaCargada)

  return { puestoId, listo }
}
