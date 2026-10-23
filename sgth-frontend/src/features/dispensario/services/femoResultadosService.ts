import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'
import type { ExamenForm } from '../schemas/femoEvaluacion.schema'
import type { ResultadoMedico } from './resultadoMedicoService'

/** Un resultado del paciente con la fila de la sección J ya armada por el servidor. */
export interface ResultadoParaFemo extends ResultadoMedico {
  para_femo: ExamenForm & { resultado_medico_id: number }
}

export const femoResultadosService = {
  /** Los del último año hasta la fecha de la evaluación, del más reciente al más antiguo. */
  listar: (cedula: string, hasta?: string | null) =>
    api.get<ApiResponse<ResultadoParaFemo[]>>('/dispensario/fichas-sso/resultados-del-paciente', {
      params: { cedula, hasta: hasta || undefined },
    }).then(r => r.data.datos ?? []),
}
