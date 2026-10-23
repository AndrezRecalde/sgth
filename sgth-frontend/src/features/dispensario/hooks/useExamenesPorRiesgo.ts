import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import { examenesPorRiesgoService } from '../services/examenesPorRiesgoService'

export function useExamenesPorRiesgo(activo = true) {
  return useQuery({
    queryKey: ['examenes', 'por-riesgo'],
    queryFn:  examenesPorRiesgoService.listar,
    enabled:  activo,
    staleTime: 1000 * 60 * 5,
  })
}

export function useGuardarExamenesDeRiesgo() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: examenesPorRiesgoService.guardar,
    onSuccess: (r) => {
      notificar.exito('Factor actualizado', r?.factor)
      qc.invalidateQueries({ queryKey: ['examenes', 'por-riesgo'] })
    },
    // Lo que tiene campo lo pinta el modal; aquí, el resto.
    onError: notificar.alFallarSalvoCampos('No se pudieron guardar los exámenes del factor'),
  })
}
