import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'
import type {
  DatosInstitucionales, EstadoIdentidad, ManifiestoIdentidad, VarianteIdentidad, VersionIdentidad,
} from './tiposIdentidad'

/** Sin esto axios manda el FormData como JSON: la instancia lo fija por defecto. */
const MULTIPART = { headers: { 'Content-Type': 'multipart/form-data' } }

const URL = '/comunicacion/identidad'

/** La identidad institucional (fase 2.3): lo público y lo que administra la persona designada. */
export const identidadService = {
  /** Sin sesión: lo piden el sidebar y el login. */
  manifiesto: () =>
    api.get<ApiResponse<ManifiestoIdentidad>>('/identidad').then((r) => r.data.datos),

  estado: () =>
    api.get<ApiResponse<EstadoIdentidad>>(URL).then((r) => r.data.datos),

  versiones: (variante: VarianteIdentidad) =>
    api.get<ApiResponse<VersionIdentidad[]>>(`${URL}/${variante}/versiones`).then((r) => r.data.datos),

  subir: (variante: VarianteIdentidad, datos: { archivo: File; vigente_desde?: string | null; nota?: string | null }) => {
    const formulario = new FormData()
    formulario.append('archivo', datos.archivo, datos.archivo.name)
    if (datos.vigente_desde) formulario.append('vigente_desde', datos.vigente_desde)
    if (datos.nota) formulario.append('nota', datos.nota)

    return api.post<ApiResponse<VersionIdentidad>>(`${URL}/${variante}`, formulario, MULTIPART)
      .then((r) => r.data)
  },

  reutilizar: (versionId: number, vigenteDesde?: string | null) =>
    api.post<ApiResponse<VersionIdentidad>>(`${URL}/versiones/${versionId}/reutilizar`, {
      vigente_desde: vigenteDesde ?? null,
    }).then((r) => r.data),

  anular: (versionId: number) =>
    api.delete<ApiResponse<VersionIdentidad>>(`${URL}/versiones/${versionId}`).then((r) => r.data.datos),

  /** El archivo de cualquier versión, también de una programada, que aún no es pública. */
  archivo: (versionId: number) =>
    api.get<Blob>(`${URL}/versiones/${versionId}/archivo`, { responseType: 'blob' }).then((r) => r.data),

  actualizarDatos: (datos: DatosInstitucionales) =>
    api.put<ApiResponse<DatosInstitucionales>>(`${URL}/datos`, datos).then((r) => r.data.datos),
}
