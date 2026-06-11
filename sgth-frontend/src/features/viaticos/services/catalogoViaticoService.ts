import api from '@/lib/axios'
import type {
  ApiResponse,
  CatalogoTransporte,
  EmpresaTransporte,
  CategoriaFactura,
} from '@/types/api'

export const catalogoViaticoService = {
  tiposTransporte: () =>
    api.get<ApiResponse<CatalogoTransporte[]>>(
      '/viaticos/catalogos/tipos-transporte'
    ).then(r => r.data.datos ?? []),

  empresasPorTipo: (tipoId: number) =>
    api.get<ApiResponse<EmpresaTransporte[]>>(
      `/viaticos/catalogos/empresas/${tipoId}`
    ).then(r => r.data.datos ?? []),

  categoriasFactura: () =>
    api.get<ApiResponse<CategoriaFactura[]>>(
      '/viaticos/catalogos/categorias-factura'
    ).then(r => r.data.datos ?? []),
}
