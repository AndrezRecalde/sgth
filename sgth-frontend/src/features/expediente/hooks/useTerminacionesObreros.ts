import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import { terminacionObreroService, type ActaFiniquitoData } from '../services/terminacionObreroService'

/** Las terminaciones de obreros ya registradas, con su acta de finiquito (fase 4.5). */
export function useTerminacionesObreros(sinActa = false) {
  return useQuery({
    queryKey: ['terminaciones-obreros', sinActa],
    queryFn: () => terminacionObreroService.listar(sinActa),
    staleTime: 1000 * 60,
  })
}

export function useGuardarActaFiniquito() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: ({ movimientoId, data }: { movimientoId: number; data: ActaFiniquitoData }) =>
      terminacionObreroService.guardarActa(movimientoId, data),
    onSuccess: () => {
      notificar.exito('Acta de finiquito registrada', 'Quedó enlazada a la terminación del obrero.')
      qc.invalidateQueries({ queryKey: ['terminaciones-obreros'] })
    },
    // Los errores de campo los pinta el formulario; lo demás —una fecha antes
    // de la terminación— llega como mensaje.
    onError: notificar.alFallarSalvoCampos('No se pudo registrar el acta de finiquito'),
  })
}
