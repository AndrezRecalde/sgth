import { useMutation } from '@tanstack/react-query'
import { pacienteService } from '../services/pacienteService'

export function useBuscarPaciente() {
  return useMutation({
    mutationFn: (cedula: string) =>
      pacienteService.buscarPorCedula(cedula),
  })
}

/**
 * Por nombre o apellidos. Mutación y no consulta, como la de cédula: se
 * lanza al pulsar Buscar, no con cada tecla.
 */
export function useBuscarPacientesPorNombre() {
  return useMutation({
    mutationFn: (q: string) => pacienteService.buscarPorNombre(q),
  })
}
