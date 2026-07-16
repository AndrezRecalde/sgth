import { useState } from 'react'
import type {
  FichaBaseForm, AntecedenteForm, FactorRiesgoForm,
  ActividadRiesgoForm,
  ExamenForm, DiagnosticoFemoForm, EmpleoAnteriorForm,
  ExamenFisicoItemForm, AntecedenteReproductivoForm, ConsumoSustanciaForm,
} from '../schemas/femo.schema'
import type { CrearFemoData, FichaSaludOcupacional } from '../services/femoService'

function fromDateNow(): string {
  const d = new Date()
  return [
    d.getFullYear(),
    String(d.getMonth() + 1).padStart(2, '0'),
    String(d.getDate()).padStart(2, '0'),
  ].join('-')
}

export function useFemoWizardState(fichaInicial?: Partial<FichaBaseForm>) {
  const [active, setActive] = useState(0)

  const [fichaData, setFichaData] = useState<Partial<FichaBaseForm>>(
    fichaInicial ?? {
      tipo_ficha:         'ingreso',
      aptitud:            'apto',
      grupo_embarazada:   false,
      grupo_discapacidad: false,
      fecha_evaluacion:   fromDateNow(),
    }
  )

  const [constantesData, setConstantesData] = useState<Record<string, number | null>>({})
  const [antecedentes, setAntecedentes] = useState<AntecedenteForm[]>([])
  const [antecedenteReproductivo, setAntecedenteReproductivo] =
    useState<Partial<AntecedenteReproductivoForm>>({})
  const [consumoSustancias, setConsumoSustancias] = useState<ConsumoSustanciaForm[]>([])
  const [factoresRiesgo, setFactoresRiesgo] = useState<FactorRiesgoForm[]>([])
  const [actividadesRiesgo, setActividadesRiesgo] = useState<ActividadRiesgoForm[]>([])
  const [empleosAnteriores, setEmpleosAnteriores] = useState<EmpleoAnteriorForm[]>([])
  const [examenFisico, setExamenFisico] = useState<ExamenFisicoItemForm[]>([])
  const [examenes, setExamenes] = useState<ExamenForm[]>([])
  const [diagnosticos, setDiagnosticos] = useState<DiagnosticoFemoForm[]>([])

  const cargarDesdeFicha = (ficha: FichaSaludOcupacional) => {
    setFichaData({
      servidor_id: ficha.servidor_id ?? null,
      postulante_id: ficha.postulante_id ?? null,
      accidente_trabajo_id: ficha.accidente_trabajo_id ?? null,
      numero_archivo: ficha.numero_archivo ?? null,
      fecha_evaluacion: ficha.fecha_evaluacion,
      tipo_ficha: ficha.tipo_ficha as FichaBaseForm['tipo_ficha'],
      puesto_trabajo: ficha.puesto_trabajo ?? null,
      puesto_trabajo_ciuo: ficha.puesto_trabajo_ciuo ?? null,
      fecha_ingreso_trabajo: ficha.fecha_ingreso_trabajo ?? null,
      grupo_embarazada: ficha.grupo_embarazada ?? false,
      grupo_discapacidad: ficha.grupo_discapacidad ?? false,
      porcentaje_discapacidad: ficha.porcentaje_discapacidad ?? null,
      aptitud: ficha.aptitud as FichaBaseForm['aptitud'],
      restricciones: ficha.restricciones ?? null,
      observaciones: ficha.observaciones ?? null,
      enfermedad_actual: ficha.enfermedad_actual ?? null,
      recomendaciones: ficha.recomendaciones ?? null,
      tratamiento: ficha.tratamiento ?? null,
      condicion_relacionada_trabajo: ficha.condicion_relacionada_trabajo ?? null,
      observacion_retiro: ficha.observacion_retiro ?? null,
      actividad_extralaboral_descripcion: ficha.actividad_extralaboral_descripcion ?? null,
      actividad_extralaboral_fecha: ficha.actividad_extralaboral_fecha ?? null,
      se_realiza_evaluacion_retiro: ficha.se_realiza_evaluacion_retiro ?? null,
      actividad_fisica_cual: ficha.actividad_fisica_cual ?? null,
      actividad_fisica_tiempo: ficha.actividad_fisica_tiempo ?? null,
      medicacion_habitual_cual: ficha.medicacion_habitual_cual ?? null,
      medicacion_habitual_cantidad: ficha.medicacion_habitual_cantidad ?? null,
    })
    setConstantesData({ ...(ficha.constantes_vitales ?? {}) })
    setAntecedentes(ficha.antecedentes ?? [])
    setAntecedenteReproductivo(ficha.antecedente_reproductivo ?? {})
    setConsumoSustancias((ficha.consumo_sustancias ?? []) as ConsumoSustanciaForm[])

    const actividadesOrdenadas = ficha.actividades ?? []
    setActividadesRiesgo(actividadesOrdenadas.map(a => ({
      puesto_actividad_id: a.puesto_actividad_id ?? null,
      actividad: a.actividad,
      medida_preventiva: a.medida_preventiva ?? null,
      orden: a.orden ?? null,
    })))
    const factoresConIndice: FactorRiesgoForm[] = actividadesOrdenadas.flatMap((a, index) =>
      (a.factores_riesgo ?? []).map(f => ({
        categoria: f.categoria,
        factor: f.factor,
        presente: f.presente,
        medida_preventiva: f.medida_preventiva ?? null,
        actividad_index: index,
      }))
    )
    const factoresHuerfanos: FactorRiesgoForm[] = (ficha.factores_riesgo ?? [])
      .filter(f => !f.ficha_actividad_id)
      .map(f => ({
        categoria: f.categoria,
        factor: f.factor,
        presente: f.presente,
        medida_preventiva: f.medida_preventiva ?? null,
      }))
    setFactoresRiesgo([...factoresConIndice, ...factoresHuerfanos])

    setEmpleosAnteriores((ficha.empleos_anteriores ?? []) as EmpleoAnteriorForm[])
    setExamenFisico((ficha.examen_fisico ?? []) as ExamenFisicoItemForm[])
    setExamenes(ficha.examenes ?? [])
    setDiagnosticos(ficha.diagnosticos ?? [])
  }

  const construirPayload = (): CrearFemoData | null => {
    const tienePersona = !!fichaData.servidor_id || !!fichaData.postulante_id
    if (!tienePersona || !fichaData.fecha_evaluacion || !fichaData.tipo_ficha || !fichaData.aptitud) {
      return null
    }

    const hayConstantes = Object.values(constantesData).some(v => v !== null && v !== undefined)
    const hayReproductivo = Object.values(antecedenteReproductivo).some(v => v !== null && v !== undefined && v !== '')

    return {
      ficha: {
        servidor_id:       fichaData.servidor_id ?? null,
        postulante_id:     fichaData.postulante_id ?? null,
        accidente_trabajo_id: fichaData.accidente_trabajo_id ?? null,
        numero_archivo:    fichaData.numero_archivo ?? null,
        fecha_evaluacion:  fichaData.fecha_evaluacion,
        tipo_ficha:        fichaData.tipo_ficha,
        aptitud:           fichaData.aptitud,
        puesto_trabajo:    fichaData.puesto_trabajo ?? null,
        puesto_trabajo_ciuo: fichaData.puesto_trabajo_ciuo ?? null,
        fecha_ingreso_trabajo: fichaData.fecha_ingreso_trabajo ?? null,
        grupo_embarazada:  fichaData.grupo_embarazada ?? false,
        grupo_discapacidad: fichaData.grupo_discapacidad ?? false,
        porcentaje_discapacidad: fichaData.porcentaje_discapacidad ?? null,
        restricciones:     fichaData.restricciones ?? null,
        observaciones:     fichaData.observaciones ?? null,
        enfermedad_actual: fichaData.enfermedad_actual ?? null,
        recomendaciones:   fichaData.recomendaciones ?? null,
        tratamiento:       fichaData.tratamiento ?? null,
        condicion_relacionada_trabajo: fichaData.condicion_relacionada_trabajo ?? null,
        observacion_retiro: fichaData.observacion_retiro ?? null,
        actividad_extralaboral_descripcion: fichaData.actividad_extralaboral_descripcion ?? null,
        actividad_extralaboral_fecha: fichaData.actividad_extralaboral_fecha ?? null,
        se_realiza_evaluacion_retiro: fichaData.se_realiza_evaluacion_retiro ?? null,
        actividad_fisica_cual: fichaData.actividad_fisica_cual ?? null,
        actividad_fisica_tiempo: fichaData.actividad_fisica_tiempo ?? null,
        medicacion_habitual_cual: fichaData.medicacion_habitual_cual ?? null,
        medicacion_habitual_cantidad: fichaData.medicacion_habitual_cantidad ?? null,
      },
      constantes_vitales: hayConstantes
        ? constantesData as CrearFemoData['constantes_vitales']
        : null,
      antecedentes,
      antecedente_reproductivo: hayReproductivo ? antecedenteReproductivo : null,
      consumo_sustancias: consumoSustancias,
      actividades:        actividadesRiesgo,
      factores_riesgo:    factoresRiesgo,
      diagnosticos,
      examenes,
      empleos_anteriores: empleosAnteriores,
      examen_fisico:      examenFisico,
    }
  }

  return {
    active, setActive,
    fichaData, setFichaData,
    constantesData, setConstantesData,
    antecedentes, setAntecedentes,
    antecedenteReproductivo, setAntecedenteReproductivo,
    consumoSustancias, setConsumoSustancias,
    factoresRiesgo, setFactoresRiesgo,
    actividadesRiesgo, setActividadesRiesgo,
    empleosAnteriores, setEmpleosAnteriores,
    examenFisico, setExamenFisico,
    examenes, setExamenes,
    diagnosticos, setDiagnosticos,
    cargarDesdeFicha,
    construirPayload,
  }
}
