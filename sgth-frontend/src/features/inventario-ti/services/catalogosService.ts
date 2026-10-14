import api from '@/lib/axios'
import type { ApiResponse } from '@/types/api'
import type { CatalogosInventario, ContratoVigencia, FichaTipo, Marca, TipoBien, Ubicacion, VentanaCronograma } from './tipos'

const URL = '/inventario'

/** Un catálogo simple: el mismo trío de llamadas para tipos, subtipos, marcas y ubicaciones. */
const crud = <T, D>(recurso: string) => ({
  crear: (data: D) => api.post<ApiResponse<T>>(`${URL}/${recurso}`, data).then((r) => r.data.datos),
  actualizar: (id: number, data: D) => api.put<ApiResponse<T>>(`${URL}/${recurso}/${id}`, data).then((r) => r.data.datos),
  eliminar: (id: number) => api.delete(`${URL}/${recurso}/${id}`),
})

export type GuardarTipoData = { nombre: string; anios_vida_util: number; ficha: FichaTipo }
export type GuardarSubtipoData = { tipo_bien_id: number; nombre: string }
export type GuardarMarcaData = { nombre: string }
export type GuardarUbicacionData = { sede: string; piso: string | null; descripcion: string | null }
export type GuardarContratoData = {
  proceso: string
  proveedor: string | null
  objeto: string | null
  administrador_id: number | null
  fecha_inicio: string | null
  fecha_fin: string | null
  observaciones: string | null
  cronograma: VentanaCronograma[]
}

/** Los catálogos del inventario (`CatalogoInventarioController`, `ContratoVigenciaController`). */
export const catalogosService = {
  formularios: () =>
    api.get<ApiResponse<CatalogosInventario>>(`${URL}/catalogos`).then((r) => r.data.datos),

  tipos: () => api.get<ApiResponse<TipoBien[]>>(`${URL}/tipos`).then((r) => r.data.datos ?? []),
  marcas: () => api.get<ApiResponse<Marca[]>>(`${URL}/marcas`).then((r) => r.data.datos ?? []),
  ubicaciones: () => api.get<ApiResponse<Ubicacion[]>>(`${URL}/ubicaciones`).then((r) => r.data.datos ?? []),
  contratos: () => api.get<ApiResponse<ContratoVigencia[]>>(`${URL}/contratos-vigencia`).then((r) => r.data.datos ?? []),

  tipo: crud<TipoBien, GuardarTipoData>('tipos'),
  subtipo: crud<TipoBien, GuardarSubtipoData>('subtipos'),
  marca: crud<Marca, GuardarMarcaData>('marcas'),
  ubicacion: crud<Ubicacion, GuardarUbicacionData>('ubicaciones'),
  contrato: crud<ContratoVigencia, GuardarContratoData>('contratos-vigencia'),
}
