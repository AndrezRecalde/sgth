import { useMutation } from '@tanstack/react-query'
import { pacienteService } from '../services/pacienteService'

export function useBuscarPaciente() {
  return useMutation({
    mutationFn: (cedula: string) =>
      pacienteService.buscarPorCedula(cedula),
  })
}
