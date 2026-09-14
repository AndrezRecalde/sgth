import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { resultadoMedicoService } from '../services/resultadoMedicoService'
import { getApiErrorMessage } from '@/types/api'
import { notificar } from '@/components/ui'

export function useResultadosPorConsulta(
  historiaClinicaId: number,
  consultaId: number
) {
  return useQuery({
    queryKey: ['resultados', 'consulta', consultaId],
    queryFn:  () => resultadoMedicoService.listar({
      historia_clinica_id: historiaClinicaId,
      consulta_medica_id:  consultaId,
    }),
    enabled:  !!historiaClinicaId && !!consultaId,
    staleTime: 1000 * 60,
  })
}

export function useResultadosPorHistoria(historiaClinicaId: number) {
  return useQuery({
    queryKey: ['resultados', 'historia', historiaClinicaId],
    queryFn:  () => resultadoMedicoService.listar({
      historia_clinica_id: historiaClinicaId,
    }),
    enabled:  !!historiaClinicaId,
    staleTime: 1000 * 60,
  })
}

export function useSubirResultado(consultaId: number) {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: (formData: FormData) =>
      resultadoMedicoService.subir(formData),
    onSuccess: () => {
      notificar.exito('Resultado subido', 'El archivo fue registrado correctamente.')
      qc.invalidateQueries({
        queryKey: ['resultados', 'consulta', consultaId],
      })
    },
    onError: (error: unknown) =>
      notificar.error('Error', getApiErrorMessage(error)),
  })
}

export function useEliminarResultado(consultaId: number) {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: (id: number) =>
      resultadoMedicoService.eliminar(id),
    onSuccess: () => {
      notificar.exito('Resultado eliminado', 'El archivo fue removido correctamente.')
      qc.invalidateQueries({
        queryKey: ['resultados', 'consulta', consultaId],
      })
    },
    onError: (error: unknown) =>
      notificar.error('Error', getApiErrorMessage(error)),
  })
}
