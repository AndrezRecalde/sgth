import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import { autoridadElectaService, type SubrogacionPorLeyData } from '../services/autoridadElectaService'

export function useAutoridadesElectas() {
  return useQuery({
    queryKey: ['autoridades-electas'],
    queryFn: autoridadElectaService.listar,
    staleTime: 1000 * 60,
  })
}

/**
 * Las que entran (2026-10-21). Cuelga de la misma llave, así que lo que
 * refresca a las autoridades en funciones la refresca a ella.
 */
export function useAutoridadesEntrantes() {
  return useQuery({
    queryKey: ['autoridades-electas', 'entrantes'],
    queryFn: autoridadElectaService.entrantes,
    staleTime: 1000 * 60,
  })
}

/** La subrogación por ley del Viceprefecto (fase 4.1b). */
export function useRegistrarSubrogacionPorLey() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: (data: SubrogacionPorLeyData) => autoridadElectaService.registrarSubrogacionPorLey(data),
    onSuccess: () => {
      notificar.exito(
        'Subrogación por ley preparada',
        'Quedó en borrador en la bandeja: suscríbala con el dictamen presupuestario y regístrela.',
      )
      qc.invalidateQueries({ queryKey: ['autoridades-electas'] })
      qc.invalidateQueries({ queryKey: ['movimientos'] })
      qc.invalidateQueries({ queryKey: ['bandeja-movimientos'] })
    },
    // Los errores de campo los pinta el formulario; lo demás —tres días o
    // menos, sin Viceprefecto— llega como mensaje.
    onError: notificar.alFallarSalvoCampos('No se pudo preparar la subrogación por ley'),
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
