import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import { clavesComunicacion } from '../constants/claves'
import { fotografiasService } from '../services/fotografiasService'
import type { FiltrosFotografias } from '../services/tipos'

/** Los servidores con el estado de su foto, filtrados y paginados en el servidor. */
export function useFotografias(filtros: FiltrosFotografias, page: number) {
  return useQuery({
    queryKey: clavesComunicacion.fotografias.lista(filtros, page),
    queryFn: () => fotografiasService.listar(filtros, page),
    placeholderData: keepPreviousData,
    staleTime: 1000 * 30,
  })
}

/** Todas las fotos de un servidor, de la más reciente a la más antigua. */
export function useHistorialFotografias(servidorId: number | null) {
  return useQuery({
    queryKey: clavesComunicacion.fotografias.historial(servidorId ?? 0),
    queryFn: () => fotografiasService.historial(servidorId ?? 0),
    enabled: !!servidorId,
  })
}

/**
 * Subir, restaurar y retirar cambian cuál es la vigente: el listado y el
 * historial quedan viejos. Las imágenes no, porque cada foto tiene su id.
 */
export function useMutacionesFotografia() {
  const qc = useQueryClient()
  const refrescar = () => qc.invalidateQueries({ queryKey: clavesComunicacion.fotografias.todas })

  const subir = useMutation({
    mutationFn: ({ servidorId, foto }: { servidorId: number; foto: Blob }) => fotografiasService.subir(servidorId, foto),
    onSuccess: () => {
      refrescar()
      notificar.exito('Foto guardada', 'Es la vigente; la anterior, si había, quedó en el historial.')
    },
    onError: notificar.alFallar('No se pudo guardar la foto'),
  })

  const restaurar = useMutation({
    mutationFn: (fotografiaId: number) => fotografiasService.restaurar(fotografiaId),
    onSuccess: () => {
      refrescar()
      notificar.exito('Foto restaurada', 'Vuelve a ser la foto vigente del servidor.')
    },
    onError: notificar.alFallar('No se pudo restaurar la foto'),
  })

  const retirar = useMutation({
    mutationFn: (servidorId: number) => fotografiasService.retirar(servidorId),
    onSuccess: () => {
      refrescar()
      notificar.exito('Foto retirada', 'El servidor queda sin foto. Se puede restaurar desde el historial.')
    },
    onError: notificar.alFallar('No se pudo retirar la foto'),
  })

  return { subir, restaurar, retirar }
}
