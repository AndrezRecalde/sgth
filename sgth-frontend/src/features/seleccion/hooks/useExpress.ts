import { useQuery } from '@tanstack/react-query'
import { expressService, type FiltroAnios } from '../services/expressService'

export function useResumenExpress(params?: FiltroAnios) {
  return useQuery({
    queryKey: ['express-resumen', params],
    queryFn: () => expressService.resumen(params),
    staleTime: 1000 * 60,
  })
}

export function useAniosExpress() {
  return useQuery({
    queryKey: ['express-anios'],
    queryFn: () => expressService.anios(),
    staleTime: 1000 * 60 * 10,
  })
}

const sinPagina = (clave: readonly unknown[]) =>
  JSON.stringify([clave[1], { ...(clave[2] as object), page: undefined }])

/** El mismo tamaño de página que el backend usa por defecto. */
export const ASPIRANTES_POR_PAGINA = 20

export function useAspirantesExpress(
  convocatoriaId: number | null,
  params?: FiltroAnios & { estado?: string; page?: number },
) {
  return useQuery({
    queryKey: ['express-aspirantes', convocatoriaId, params],
    queryFn: () => expressService.aspirantes(convocatoriaId!, { ...params, per_page: ASPIRANTES_POR_PAGINA }),
    enabled: convocatoriaId !== null,
    staleTime: 1000 * 60,
    // Al pasar de página se queda la anterior mientras llega la nueva. Solo
    // si lo único que cambió es la página: con otra modalidad o con otro
    // filtro serían filas que no corresponden.
    placeholderData: (previa, consulta) =>
      consulta && sinPagina(consulta.queryKey) === sinPagina(['express-aspirantes', convocatoriaId, params])
        ? previa : undefined,
  })
}
