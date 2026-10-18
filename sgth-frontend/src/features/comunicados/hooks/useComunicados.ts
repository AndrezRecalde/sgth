import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { aDataUrl } from '@/lib/dataUrl'
import { clavesComunicados } from '../constants/claves'
import { comunicadosService } from '../services/comunicadosService'
import type { Comunicado, FiltrosComunicados, SeleccionDestinatarios } from '../services/tipos'

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

/**
 * La portada, lista para un `src`. Va como `data:` URL por la misma razón que
 * la foto del servidor (ver `aDataUrl`), y la clave lleva la fecha del
 * registro: al reemplazarla cambia, y se vuelve a pedir.
 */
export function usePortadaComunicado(comunicado: Pick<Comunicado, 'id' | 'tiene_portada' | 'actualizado_en'> | null | undefined) {
  return useQuery({
    queryKey: clavesComunicados.portada(comunicado?.id ?? 0, comunicado?.actualizado_en ?? null),
    queryFn: async () => aDataUrl(await comunicadosService.portada(comunicado?.id ?? 0)),
    enabled: !!comunicado?.tiene_portada,
    staleTime: Infinity,
    retry: false,
  })
}
