import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'

export interface CriticoJornada {
  id:          number
  descripcion: string
  paciente:    string | null
  comentario:  string | null
  revisor:     string | null
  revisado_en: string | null
}

interface JornadaClinica {
  perfil: 'medico' | 'odontologo'
  hoy: {
    esperando:      number
    /** De los que esperan, los que ya pueden pasar (sin triaje pendiente). */
    listos:         number
    en_consulta:    number
    atendidos:      number
    no_presentados: number
  }
  pendientes: {
    /** Consultas empezadas y sin cerrar. */
    borradores:          number
    fichas_femo:         number
    evaluaciones_listas: number
    /** De lo que pidió (o de sus consultas, sin orden), lo que nadie ha leído. */
    resultados_por_revisar: number
    /** Críticos que revisó otro médico y él no ha visto: fijos hasta que los vea (D3). */
    criticos: CriticoJornada[]
  }
  mes: {
    consultas:      number
    pacientes:      number
    reposos:        number
    dias_reposo:    number
    procedimientos: number
    diagnosticos:   { codigo: string; descripcion: string; total: number }[]
  }
}

interface JornadaEnfermeria {
  perfil: 'enfermeria'
  hoy: {
    por_triar:      number
    triaje_sso:     number
    mis_atenciones: number
  }
  mes: {
    atenciones:   number
    triajes:      number
    por_servicio: { servicio: string; total: number }[]
  }
}

/** `GET /dispensario/mi-jornada`: lo de quien pregunta, y nada de los demás. */
export type MiJornada = JornadaClinica | JornadaEnfermeria | { perfil: null }

/** La pantalla desde la que se pide: decide la jornada de quien tiene varios roles. */
export type ContextoJornada = 'medico' | 'odontologo' | 'enfermeria'

export const miJornadaService = {
  obtener: (perfil: ContextoJornada) =>
    api.get<ApiResponse<MiJornada>>('/dispensario/mi-jornada', { params: { perfil } })
      .then(r => r.data.datos),
}
