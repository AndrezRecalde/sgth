import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import { periodoDePruebaService } from '../services/periodoDePruebaService'

export function usePeriodosDePrueba() {
  return useQuery({
    queryKey: ['periodos-de-prueba'],
    queryFn: periodoDePruebaService.listar,
    staleTime: 1000 * 60,
  })
}

/** El nombramiento definitivo de un período de prueba que concluyó (fase 3.2). */
export function usePrepararNombramientoDefinitivo() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: (contratoId: number) => periodoDePruebaService.prepararDefinitivo(contratoId),
    onSuccess: () => {
      notificar.exito(
        'Nombramiento definitivo preparado',
        'Quedó en borrador en la bandeja de acciones de personal, para suscribirlo y registrarlo.',
      )
      qc.invalidateQueries({ queryKey: ['periodos-de-prueba'] })
      qc.invalidateQueries({ queryKey: ['movimientos'] })
      qc.invalidateQueries({ queryKey: ['bandeja-movimientos'] })
    },
    onError: notificar.alFallar('No se pudo preparar el nombramiento definitivo'),
  })
}
