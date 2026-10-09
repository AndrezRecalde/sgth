import api from '@/lib/axios'
import type { ApiResponse, CatalogoAccionesPersonal } from '@/types/api'

export const catalogoAccionesService = {
  /**
   * Qué acciones de personal existen, a qué nombramientos aplican y qué pide el
   * formulario en cada una. No depende del servidor: el cruce con el
   * nombramiento vigente lo hace la pantalla con estos datos.
   */
  obtener: () =>
    api
      .get<ApiResponse<CatalogoAccionesPersonal>>('/expediente/acciones-personal/catalogo')
      .then((r) => r.data.datos),
}
