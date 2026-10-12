import { keepPreviousData, useMutation, useQuery, useQueryClient, type QueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import {
  escalaRemuneracionService, type EscalaRemuneracionData, type GradoDeLaEscala,
} from '../services/escalaRemuneracionService'

/** Las escalas registradas, la más reciente primero (fase 7.3b). */
export function useEscalasRemuneracion() {
  return useQuery({
    queryKey: ['escalas-remuneracion'],
    queryFn: escalaRemuneracionService.listar,
    staleTime: 1000 * 30,
  })
}

/** A quién subiría la escala, y qué la impide. Se vuelve a pedir con cada grado. */
export function useVistaPreviaEscala(grados: GradoDeLaEscala[]) {
  return useQuery({
    queryKey: ['escalas-remuneracion', 'vista-previa', grados],
    queryFn: () => escalaRemuneracionService.previsualizar(grados),
    enabled: grados.length > 0,
    staleTime: 0,
    placeholderData: keepPreviousData,
  })
}

/** La RMU cambia en el catálogo y en los vínculos: lo que la muestra se vuelve a pedir. */
function invalidar(qc: QueryClient) {
  qc.invalidateQueries({ queryKey: ['escalas-remuneracion'] })
  qc.invalidateQueries({ queryKey: ['grupos-ocupacionales'] })
  qc.invalidateQueries({ queryKey: ['puestos'] })
  qc.invalidateQueries({ queryKey: ['novedades-nomina'] })
}

export function useRegistrarEscala() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: (data: EscalaRemuneracionData) => escalaRemuneracionService.registrar(data),
    onSuccess: (r) => {
      notificar.exito('Escala registrada', r.mensaje)
      invalidar(qc)
    },
    onError: notificar.alFallarSalvoCampos('No se pudo registrar la escala'),
  })
}

export function useRevertirEscala() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: ({ id, motivo }: { id: number; motivo: string }) => escalaRemuneracionService.revertir(id, motivo),
    onSuccess: (r) => {
      notificar.exito('Escala revertida', r.mensaje)
      invalidar(qc)
    },
    onError: notificar.alFallar('No se pudo revertir la escala'),
  })
}
