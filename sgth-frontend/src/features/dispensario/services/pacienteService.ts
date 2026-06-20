import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'

export interface PacienteEncontrado {
  tipo:                    'servidor' | 'beneficiario'
  id:                      number
  cedula:                  string
  nombre_completo:         string
  puesto?:                 string | null
  unidad_administrativa?:  string | null
  tipo_familiar?:          string | null
  servidor_titular?:       string | null
  tiene_historia_clinica:  boolean
  historia_clinica_id?:    number | null
}

export const pacienteService = {
  buscarPorCedula: (cedula: string) =>
    api.get<ApiResponse<PacienteEncontrado>>(
      '/dispensario/pacientes/buscar',
      { params: { cedula } }
    ).then(r => r.data.datos),
}
