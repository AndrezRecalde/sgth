import { useMutation, useQueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import { expedienteService } from '../services/expedienteService'

/**
 * Bajas de las condiciones de salud de una carga familiar. Vivían como
 * llamadas sueltas al servicio dentro de cada fila de la pestaña Familia.
 */
export function useCondicionCargaMutations(servidorId: number, cargaId: number) {
  const qc = useQueryClient()
  const invalidar = () =>
    qc.invalidateQueries({ queryKey: ['cargas-familiares', servidorId] })

  const eliminarDiscapacidad = useMutation({
    mutationFn: (id: number) => expedienteService.eliminarDiscapacidadCarga(cargaId, id),
    onSuccess: () => {
      notificar.exito('Discapacidad eliminada', 'La discapacidad dejó de constar en la carga familiar.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo eliminar la discapacidad'),
  })

  const eliminarEnfermedad = useMutation({
    mutationFn: (id: number) => expedienteService.eliminarEnfermedadCarga(cargaId, id),
    onSuccess: () => {
      notificar.exito('Enfermedad eliminada', 'La enfermedad dejó de constar en la carga familiar.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo eliminar la enfermedad'),
  })

  return { eliminarDiscapacidad, eliminarEnfermedad }
}
