import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'

export interface PersonalMedico {
  id:               number
  nombre_completo:  string
  roles:            string[]
  puesto?:          string | null
}

export const personalMedicoService = {
  listar: (rol?: 'medico' | 'odontologo' | 'enfermera') =>
    api.get<ApiResponse<PersonalMedico[]>>(
      '/dispensario/personal-medico',
      { params: rol ? { rol } : undefined }
    ).then(r => r.data.datos),
}
