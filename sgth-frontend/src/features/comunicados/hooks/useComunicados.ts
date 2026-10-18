import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { clavesComunicados } from '../constants/claves'
import { comunicadosService } from '../services/comunicadosService'
import type { FiltrosComunicados, SeleccionDestinatarios } from '../services/tipos'

export const POR_PAGINA_COMUNICADOS = 15

/** El listado de Talento Humano, filtrado y paginado en el servidor. */
export function useComunicados(filtros: FiltrosComunicados, page: number) {
  return useQuery({
    queryKey: clavesComunicados.lista(filtros, page),
    queryFn: () => comunicadosService.listar(filtros, page, POR_PAGINA_COMUNICADOS),
    placeholderData: keepPreviousData,
    staleTime: 1000 * 30,
  })
}

export function useComunicado(id: number) {
  return useQuery({
    queryKey: clavesComunicados.detalle(id),
    queryFn: () => comunicadosService.ver(id),
    enabled: id > 0,
    // Un 404 aquí es la respuesta, no un fallo de red.
    retry: false,
  })
}

/**
 * Cuántos verían lo elegido en el formulario. Quien la usa le pasa la
 * selección ya serenada (`useDebouncedValue`): cada casilla no es una petición.
 */
export function useConteoDestinatarios(seleccion: SeleccionDestinatarios) {
  return useQuery({
    queryKey: clavesComunicados.conteo(seleccion),
    queryFn: () => comunicadosService.contarDestinatarios(seleccion),
    placeholderData: keepPreviousData,
    staleTime: 1000 * 60,
  })
}
