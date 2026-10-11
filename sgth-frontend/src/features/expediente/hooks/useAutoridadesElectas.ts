import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import { autoridadElectaService } from '../services/autoridadElectaService'

export function useAutoridadesElectas() {
  return useQuery({
    queryKey: ['autoridades-electas'],
    queryFn: autoridadElectaService.listar,
    staleTime: 1000 * 60,
  })
}

/** El fin del período de la autoridad que no lo tiene (fase 4.1). */
export function useFijarFinDelPeriodo() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: ({ contratoId, fechaFin }: { contratoId: number; fechaFin: string }) =>
      autoridadElectaService.fijarFinDelPeriodo(contratoId, fechaFin),
    onSuccess: () => {
      notificar.exito('Fin del período fijado', 'Ese día el vínculo se cierra solo.')
      qc.invalidateQueries({ queryKey: ['autoridades-electas'] })
    },
    // Los errores de campo los pinta el formulario; lo demás —más de cuatro
    // años, un período que ya lo dice su acto— llega como mensaje.
    onError: notificar.alFallarSalvoCampos('No se pudo fijar el fin del período'),
  })
}
