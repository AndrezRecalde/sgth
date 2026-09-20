import { useMutation, useQueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import { expedienteService } from '../services/expedienteService'

/**
 * Bajas de las condiciones de salud del servidor. Vivían como dos
 * `useMutation` dentro de la pestaña Condición.
 */
export function useCondicionMutations(servidorId: number) {
  const qc = useQueryClient()

  const eliminarDiscapacidad = useMutation({
    mutationFn: (id: number) => expedienteService.eliminarDiscapacidad(servidorId, id),
    onSuccess: () => {
      notificar.exito('Registro eliminado', 'La discapacidad fue eliminada del expediente.')
      qc.invalidateQueries({ queryKey: ['discapacidades', servidorId] })
    },
    onError: notificar.alFallar('No se pudo eliminar la discapacidad'),
  })

  const eliminarEnfermedad = useMutation({
    mutationFn: (id: number) => expedienteService.eliminarEnfermedad(servidorId, id),
    onSuccess: () => {
      notificar.exito(
        'Registro eliminado',
        'La enfermedad catastrófica fue eliminada del expediente.',
      )
      qc.invalidateQueries({ queryKey: ['enfermedades', servidorId] })
    },
    onError: notificar.alFallar('No se pudo eliminar la enfermedad'),
  })

  return { eliminarDiscapacidad, eliminarEnfermedad }
}
