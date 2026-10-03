import { useState } from 'react'
import type {
  FichaBaseForm, AntecedenteForm, FactorRiesgoForm,
  ActividadRiesgoForm,
  ExamenForm, DiagnosticoFemoForm, EmpleoAnteriorForm,
  ExamenFisicoItemForm, AntecedenteReproductivoForm, ConsumoSustanciaForm,
} from '../schemas/femo.schema'
import type { CrearFemoData, FichaSaludOcupacional } from '../services/femoService'
import { hoyIso } from '@/lib/fecha'
import { constantesDe, fichaAFormulario, fichaAPayload, riesgosDe } from './femoMapeo'

export function useFemoWizardState(fichaInicial?: Partial<FichaBaseForm>) {
  const [active, setActive] = useState(0)

  const [fichaData, setFichaData] = useState<Partial<FichaBaseForm>>(
    fichaInicial ?? {
      tipo_ficha:         'ingreso',
      // Sin aptitud: se elige a conciencia antes de emitir el dictamen. Antes
      // nacía en «apto» y una ficha guardada sin pensarlo quedaba apta.
      aptitud:            null,
      grupo_embarazada:   false,
      grupo_discapacidad: false,
      fecha_evaluacion:   hoyIso(),
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
    setFichaData(fichaAFormulario(ficha))
    setConstantesData(constantesDe(ficha))
    setAntecedentes(ficha.antecedentes ?? [])
    setAntecedenteReproductivo(ficha.antecedente_reproductivo ?? {})
    setConsumoSustancias((ficha.consumo_sustancias ?? []) as ConsumoSustanciaForm[])
    const riesgos = riesgosDe(ficha)
    setActividadesRiesgo(riesgos.actividades)
    setFactoresRiesgo(riesgos.factores)
    setEmpleosAnteriores((ficha.empleos_anteriores ?? []) as EmpleoAnteriorForm[])
    setExamenFisico((ficha.examen_fisico ?? []) as ExamenFisicoItemForm[])
    setExamenes(ficha.examenes ?? [])
    setDiagnosticos(ficha.diagnosticos ?? [])
  }

  const construirPayload = (): CrearFemoData | null => {
    const tienePersona = !!fichaData.servidor_id || !!fichaData.postulante_id
    if (!tienePersona || !fichaData.fecha_evaluacion || !fichaData.tipo_ficha) {
      return null
    }

    const hayReproductivo = Object.values(antecedenteReproductivo)
      .some(v => v !== null && v !== undefined && v !== '')

    return {
      ficha: fichaAPayload(fichaData, fichaData.fecha_evaluacion, fichaData.tipo_ficha),
      // Sin constantes vitales: el servidor las copia del triaje de Enfermería.
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
