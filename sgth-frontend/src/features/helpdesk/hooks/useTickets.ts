import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import { abrirOGuardarArchivo } from '@/lib/archivo'
import { clavesHelpdesk } from '../constants/claves'
import { ticketsService } from '../services/ticketsService'
import type { FiltrosTickets, NuevoTicketData } from '../services/tipos'

export function useTickets(filtros: FiltrosTickets, page: number) {
  return useQuery({
    queryKey: clavesHelpdesk.lista(filtros, page),
    queryFn: () => ticketsService.listar(filtros, page),
    placeholderData: keepPreviousData,
  })
}

/** El ticket con su conversación. Se refresca cada minuto: la respuesta de TIC llega sola. */
export function useTicket(id: number) {
  return useQuery({
    queryKey: clavesHelpdesk.uno(id),
    queryFn: () => ticketsService.obtener(id),
    enabled: id > 0,
    refetchInterval: 60_000,
  })
}

export function useCatalogosHelpdesk() {
  return useQuery({ queryKey: clavesHelpdesk.catalogos, queryFn: ticketsService.catalogos, staleTime: 1000 * 60 * 10 })
}

/**
 * Lo que hace quien abrió el ticket: abrirlo, escribir, confirmar la solución,
 * reabrirlo o anularlo mientras nadie lo atiende.
 */
export function useTicketSolicitanteMutations() {
  const qc = useQueryClient()
  const invalidar = () => qc.invalidateQueries({ queryKey: clavesHelpdesk.raiz })

  const crear = useMutation({
    mutationFn: (data: NuevoTicketData) => ticketsService.crear(data),
    onSuccess: (t) => {
      notificar.exito('Ticket registrado', `${t.folio}: TIC le responderá por aquí.`)
      invalidar()
    },
    onError: notificar.alFallarSalvoCampos('No se pudo registrar el ticket'),
  })

  const comentar = useMutation({
    mutationFn: ({ id, comentario, archivo, interno }: { id: number; comentario: string; archivo: File | null; interno?: boolean }) =>
      ticketsService.comentar(id, comentario, archivo, interno),
    onSuccess: invalidar,
    onError: notificar.alFallarSalvoCampos('No se pudo enviar el mensaje'),
  })

  const confirmar = useMutation({
    mutationFn: ({ id, calificacion, comentario }: { id: number; calificacion: number | null; comentario: string | null }) =>
      ticketsService.confirmar(id, calificacion, comentario),
    onSuccess: (t) => {
      notificar.exito('Gracias', `${t.folio} quedó cerrado.`)
      invalidar()
    },
    onError: notificar.alFallar('No se pudo confirmar la solución'),
  })

  const reabrir = useMutation({
    mutationFn: ({ id, motivo }: { id: number; motivo: string }) => ticketsService.reabrir(id, motivo),
    onSuccess: (t) => {
      notificar.exito('Ticket reabierto', `${t.folio} vuelve a TIC.`)
      invalidar()
    },
    onError: notificar.alFallar('No se pudo reabrir el ticket'),
  })

  const anular = useMutation({
    mutationFn: ({ id, motivo }: { id: number; motivo: string }) => ticketsService.anular(id, motivo),
    onSuccess: (t) => {
      notificar.exito('Ticket anulado', `${t.folio} ya no se atenderá.`)
      invalidar()
    },
    onError: notificar.alFallar('No se pudo anular el ticket'),
  })

  return { crear, comentar, confirmar, reabrir, anular }
}

export function useArchivoTicket() {
  return useMutation({
    mutationFn: async ({ id, comentarioId, nombre }: { id: number; comentarioId: number; nombre: string }) =>
      abrirOGuardarArchivo(await ticketsService.archivo(id, comentarioId), nombre),
    onError: notificar.alFallar('No se pudo abrir el archivo del ticket'),
  })
}
