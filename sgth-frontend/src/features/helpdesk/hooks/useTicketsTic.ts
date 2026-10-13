import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import { clavesHelpdesk } from '../constants/claves'
import { ticketsService } from '../services/ticketsService'
import type { AsignarTicketData, NuevoTicketData, ResolverTicketData } from '../services/tipos'

/** Los contadores de las pestañas de la bandeja. Se refrescan cada minuto. */
export function useResumenTickets() {
  return useQuery({ queryKey: clavesHelpdesk.resumen, queryFn: ticketsService.resumen, refetchInterval: 60_000 })
}

/**
 * El trámite de TIC. Cada paso cambia el ticket, los listados y los
 * contadores; resolver uno de hardware deja además el mantenimiento en la
 * ficha del equipo.
 */
export function useTicketTicMutations() {
  const qc = useQueryClient()
  const hecho = (titulo: string, detalle: string) => {
    notificar.exito(titulo, detalle)
    qc.invalidateQueries({ queryKey: clavesHelpdesk.raiz })
    qc.invalidateQueries({ queryKey: ['inventario-ti'] })
  }

  const registrar = useMutation({
    mutationFn: (data: NuevoTicketData) => ticketsService.crear(data),
    onSuccess: (t) => hecho('Ticket registrado', `${t.folio} a nombre de ${t.solicitante?.nombre ?? 'el servidor'}.`),
    onError: notificar.alFallarSalvoCampos('No se pudo registrar el ticket'),
  })
  const asignar = useMutation({
    mutationFn: ({ id, data }: { id: number; data: AsignarTicketData }) => ticketsService.asignar(id, data),
    onSuccess: (t) => hecho('Ticket asignado', `${t.folio} queda con ${t.tecnico?.nombre ?? 'el técnico'}.`),
    onError: notificar.alFallarSalvoCampos('No se pudo asignar el ticket'),
  })
  const tomar = useMutation({
    mutationFn: (id: number) => ticketsService.tomar(id),
    onSuccess: (t) => hecho('Ticket en proceso', `${t.folio} está a su nombre.`),
    onError: notificar.alFallar('No se pudo tomar el ticket'),
  })
  const pedirInformacion = useMutation({
    mutationFn: ({ id, pregunta }: { id: number; pregunta: string }) => ticketsService.pedirInformacion(id, pregunta),
    onSuccess: (t) => hecho('Pregunta enviada', `${t.folio} espera al usuario; el SLA queda en pausa.`),
    onError: notificar.alFallar('No se pudo enviar la pregunta'),
  })
  const resolver = useMutation({
    mutationFn: ({ id, data }: { id: number; data: ResolverTicketData }) => ticketsService.resolver(id, data),
    onSuccess: (t) => hecho('Ticket resuelto', `${t.folio}: el usuario confirmará la solución.`),
    onError: notificar.alFallarSalvoCampos('No se pudo resolver el ticket'),
  })
  const escalar = useMutation({
    mutationFn: ({ id, nivel, motivo, tecnicoId }: { id: number; nivel: number; motivo: string; tecnicoId: number | null }) =>
      ticketsService.escalar(id, nivel, motivo, tecnicoId),
    onSuccess: (t) => hecho('Ticket escalado', `${t.folio} pasa al nivel ${t.nivel}.`),
    onError: notificar.alFallarSalvoCampos('No se pudo escalar el ticket'),
  })
  const anular = useMutation({
    mutationFn: ({ id, motivo }: { id: number; motivo: string }) => ticketsService.anular(id, motivo),
    onSuccess: (t) => hecho('Ticket anulado', `${t.folio} ya no se atenderá.`),
    onError: notificar.alFallar('No se pudo anular el ticket'),
  })
  const vincularBien = useMutation({
    mutationFn: ({ id, bienId }: { id: number; bienId: number | null }) => ticketsService.vincularBien(id, bienId),
    onSuccess: (t) => hecho('Equipo vinculado', t.bien ? `${t.folio} con el equipo ${t.bien.codigo_institucional}.` : `${t.folio} quedó sin equipo.`),
    onError: notificar.alFallarSalvoCampos('No se pudo vincular el equipo'),
  })

  return { registrar, asignar, tomar, pedirInformacion, resolver, escalar, anular, vincularBien }
}
