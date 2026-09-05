import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'

export interface PersonalMedico {
  id:               number
  nombre_completo:  string
  roles:            string[]
  puesto?:          string | null
}

export interface PersonalParaAtencion {
  personal: PersonalMedico[]
  /** `false` cuando nadie se marcó disponible y se muestra a todo el rol. */
  hayDisponibles: boolean
}

export const personalMedicoService = {
  /**
   * A quién asignarle un turno de esta atención.
   *
   * Filtra por disponibilidad de verdad: hasta ahora la pantalla pedía la
   * lista completa del rol y decía que eran los «marcados como disponibles».
   */
  paraAtencion: (tipoAtencion: 'medicina_general' | 'odontologia') =>
    api.get<ApiResponse<PersonalMedico[], { hay_disponibles: boolean }>>(
      '/dispensario/disponibilidad/personal',
      { params: { tipo_atencion: tipoAtencion } }
    ).then(r => ({
      personal:       r.data.datos ?? [],
      hayDisponibles: r.data.meta?.hay_disponibles ?? true,
    })),

  listar: (rol?: 'medico' | 'odontologo' | 'enfermera') =>
    api.get<ApiResponse<PersonalMedico[]>>(
      '/dispensario/personal-medico',
      { params: rol ? { rol } : undefined }
    ).then(r => r.data.datos),
}
