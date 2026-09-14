import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { agendaService } from '../services/agendaService'
import { personalMedicoService } from '../services/personalMedicoService'
import type { CrearAgendaData } from '../services/agendaService'
import { notificar } from '@/components/ui'

export function usePersonalMedico(
  rol?: 'medico' | 'odontologo' | 'enfermera'
) {
  return useQuery({
    queryKey: ['personal-medico', rol],
    queryFn:  () => personalMedicoService.listar(rol),
    staleTime: 1000 * 60 * 5,
  })
}

/**
 * A quién asignarle un turno de esta atención.
 *
 * Pedía la lista completa del rol y la pantalla la presentaba como «marcados
 * como disponibles», así que el interruptor que pulsa el médico no tenía ningún
 * efecto sobre lo que veía Recepción. Ahora filtra de verdad, y cuando nadie se
 * ha marcado devuelve a todos avisando de ello: bloquear el alta de turnos
 * porque aún nadie pulsó su interruptor sería peor que el problema.
 */
export function usePersonalDisponible(
  tipoAtencion: 'medicina_general' | 'odontologia'
) {
  return useQuery({
    queryKey: ['personal-medico', 'disponible', tipoAtencion],
    queryFn:  () => personalMedicoService.paraAtencion(tipoAtencion),
    staleTime: 1000 * 30,
  })
}

export function useColaTurnos(filtros: {
  medico_id?: number
  fecha?:     string
  estado?:    string
}) {
  return useQuery({
    queryKey: ['agenda', 'cola', filtros],
    queryFn:  () => agendaService.listar(filtros),
    staleTime: 1000 * 15,
    refetchInterval: 1000 * 30,
  })
}

export function useCrearTurno() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: (data: CrearAgendaData) =>
      agendaService.crear(data),
    onSuccess: () => {
      notificar.exito('Turno creado', 'El turno fue registrado correctamente.')
      qc.invalidateQueries({ queryKey: ['agenda'] })
    },
    onError: notificar.alFallar('No se pudo crear el turno'),
  })
}

export function useCancelarTurno() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: (id: number) => agendaService.cancelar(id),
    onSuccess: () => {
      notificar.exito('Turno cancelado', 'El turno fue cancelado correctamente.')
      qc.invalidateQueries({ queryKey: ['agenda'] })
    },
    onError: notificar.alFallar('No se pudo cancelar el turno'),
  })
}

export function useListosParaConsulta() {
  return useQuery({
    queryKey: ['agenda', 'listos-para-consulta'],
    queryFn:  agendaService.listosParaConsulta,
    staleTime: 1000 * 15,
    refetchInterval: 1000 * 30,
  })
}

export function useTurnosDelDia(params?: {
  fecha_desde?: string
  fecha_hasta?: string
}) {
  return useQuery({
    queryKey: ['agenda', 'turnos-del-dia', params],
    queryFn:  () => agendaService.turnosDelDia(params),
    staleTime: 1000 * 15,
    refetchInterval: params ? undefined : 1000 * 30,
  })
}

export function useAccionesTurno() {
  const qc = useQueryClient()

  const invalidar = () => {
    qc.invalidateQueries({ queryKey: ['agenda'] })
  }

  const noPresentado = useMutation({
    mutationFn: (id: number) =>
      agendaService.marcarNoPresentado(id),
    onSuccess: () => {
      notificar.exito('Turno marcado', 'Paciente marcado como no presentado.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo marcar el turno como no presentado'),
  })

  const reactivar = useMutation({
    mutationFn: (id: number) =>
      agendaService.reactivar(id),
    onSuccess: () => {
      notificar.exito('Turno reactivado', 'El paciente fue reactivado en la cola.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo reactivar el turno'),
  })

  const enConsulta = useMutation({
    mutationFn: (id: number) =>
      agendaService.marcarEnConsulta(id),
    onSuccess: () => invalidar(),
    onError: notificar.alFallar('No se pudo pasar el turno a consulta'),
  })

  return { noPresentado, reactivar, enConsulta }
}
