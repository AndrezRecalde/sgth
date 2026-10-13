import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import { clavesPrestamos } from '../constants/claves'
import { misPrestamosService } from '../services/misPrestamosService'
import type { EstadoPrestamo, SolicitarPrestamoData } from '../services/tipos'

export function useMisPrestamos(estado: EstadoPrestamo | null, page: number) {
  return useQuery({
    queryKey: clavesPrestamos.mios.lista(estado, page),
    queryFn: () => misPrestamosService.listar(estado, page),
    placeholderData: keepPreviousData,
  })
}

export function useMiPrestamo(id: number | null) {
  return useQuery({
    queryKey: clavesPrestamos.mios.uno(id ?? 0),
    queryFn: () => misPrestamosService.obtener(id ?? 0),
    enabled: id !== null,
  })
}

/** Lo que muestra la agenda: un día o una semana. Se refresca cada minuto. */
export function useDisponibilidadPortal(desde: string, hasta: string) {
  return useQuery({
    queryKey: clavesPrestamos.disponibilidad(desde, hasta),
    queryFn: () => misPrestamosService.disponibilidad(desde, hasta),
    placeholderData: keepPreviousData,
    refetchInterval: 60_000,
  })
}

export function useReglasPrestamoPortal() {
  return useQuery({
    queryKey: clavesPrestamos.reglas,
    queryFn: misPrestamosService.reglas,
    staleTime: 1000 * 60 * 10,
  })
}

/** Pedir y cancelar. Los dos cambian la disponibilidad: se invalida todo el préstamo. */
export function useMisPrestamoMutations() {
  const qc = useQueryClient()
  const invalidar = () => qc.invalidateQueries({ queryKey: clavesPrestamos.raiz })

  const solicitar = useMutation({
    mutationFn: (data: SolicitarPrestamoData) => misPrestamosService.solicitar(data),
    onSuccess: (p) => {
      notificar.exito('Solicitud enviada', `${p.folio}: TIC le avisará cuando la apruebe.`)
      invalidar()
    },
    onError: notificar.alFallarSalvoCampos('No se pudo enviar la solicitud de préstamo'),
  })

  const cancelar = useMutation({
    mutationFn: (id: number) => misPrestamosService.cancelar(id),
    onSuccess: (p) => {
      notificar.exito('Préstamo cancelado', `${p.folio} queda cancelado y el horario libre.`)
      invalidar()
    },
    onError: notificar.alFallar('No se pudo cancelar el préstamo'),
  })

  return { solicitar, cancelar }
}
