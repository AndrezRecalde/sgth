import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { disponibilidadService } from '../services/disponibilidadService'
import { getApiErrorMessage } from '@/types/api'
import { notificar } from '@/components/ui'

export function useMiDisponibilidad(activo = true) {
  return useQuery({
    queryKey: ['mi-disponibilidad'],
    queryFn:  disponibilidadService.miEstado,
    // El control vive en la barra superior, que se pinta en todos los
    // subsistemas: sin esto se preguntaría el estado también a quien no atiende.
    enabled:  activo,
    staleTime: 1000 * 60,
  })
}

export function useAlternarDisponibilidad() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: disponibilidadService.alternar,
    onSuccess: (data) => {
      qc.setQueryData(['mi-disponibilidad'], data)
      notificar.exito(
        data.disponible ? 'Disponible' : 'No disponible',
        data.disponible
          ? 'Ahora apareces disponible para atención.'
          : 'Ya no apareces disponible para atención.',
      )
    },
    onError: (error: unknown) =>
      notificar.error('Error', getApiErrorMessage(error)),
  })
}
