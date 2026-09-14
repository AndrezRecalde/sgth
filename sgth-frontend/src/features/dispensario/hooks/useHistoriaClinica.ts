import { useMutation, useQueryClient } from '@tanstack/react-query'
import { historiaClinicaService } from '../services/historiaClinicaService'
import { getApiErrorMessage } from '@/types/api'
import type {
  CrearAlergiaData, CrearAntecedenteData,
} from '../services/historiaClinicaService'
import { notificar } from '@/components/ui'

export function useCrearHistoriaClinica() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: historiaClinicaService.crear,
    onSuccess: () => {
      notificar.exito(
        'Historia clínica creada',
        'Se registró la historia clínica del paciente.',
      )
      qc.invalidateQueries({ queryKey: ['historias-clinicas'] })
    },
    onError: (error: unknown) =>
      notificar.error('Error', getApiErrorMessage(error)),
  })
}

export function useAgregarAlergia(
  historiaId: number,
  agendaId: number
) {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: (data: CrearAlergiaData) =>
      historiaClinicaService.agregarAlergia(historiaId, data),
    onSuccess: () => {
      notificar.exito('Alergia registrada', 'La alergia fue agregada al historial.')
      qc.invalidateQueries({
        queryKey: ['contexto-consulta', historiaId, agendaId],
      })
    },
    onError: (error: unknown) =>
      notificar.error('Error', getApiErrorMessage(error)),
  })
}

export function useAgregarAntecedente(
  historiaId: number,
  agendaId: number
) {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: (data: CrearAntecedenteData) =>
      historiaClinicaService.agregarAntecedente(historiaId, data),
    onSuccess: () => {
      notificar.exito('Antecedente registrado', 'El antecedente fue agregado al historial.')
      qc.invalidateQueries({
        queryKey: ['contexto-consulta', historiaId, agendaId],
      })
    },
    onError: (error: unknown) =>
      notificar.error('Error', getApiErrorMessage(error)),
  })
}

export function useAnularAlergia(
  historiaId: number,
  agendaId: number
) {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: ({ id, motivo }: { id: number; motivo: string }) =>
      historiaClinicaService.anularAlergia(historiaId, id, motivo),
    onSuccess: () => {
      notificar.exito('Alergia anulada', 'La alergia fue anulada con trazabilidad.')
      qc.invalidateQueries({
        queryKey: ['contexto-consulta', historiaId, agendaId],
      })
    },
    onError: (error: unknown) =>
      notificar.error('Error', getApiErrorMessage(error)),
  })
}

export function useAnularAntecedente(
  historiaId: number,
  agendaId: number
) {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: ({ id, motivo }: { id: number; motivo: string }) =>
      historiaClinicaService.anularAntecedente(historiaId, id, motivo),
    onSuccess: () => {
      notificar.exito('Antecedente anulado', 'El antecedente fue anulado con trazabilidad.')
      qc.invalidateQueries({
        queryKey: ['contexto-consulta', historiaId, agendaId],
      })
    },
    onError: (error: unknown) =>
      notificar.error('Error', getApiErrorMessage(error)),
  })
}
