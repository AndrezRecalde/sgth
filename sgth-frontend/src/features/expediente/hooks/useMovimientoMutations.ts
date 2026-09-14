import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  expedienteService,
  type ActualizarBorradorData,
  type TransicionarData,
} from '../services/expedienteService'
import { getApiErrorMessage } from '@/types/api'
import { notificar } from '@/components/ui'

export function useMovimientoMutations(servidorId?: number | null) {
  const qc = useQueryClient()

  const actualizarBorrador = useMutation({
    mutationFn: ({ id, data }: { id: number; data: ActualizarBorradorData }) =>
      expedienteService.actualizarBorradorMovimiento(id, data),
    onSuccess: () => {
      notificar.exito(
        'Borrador actualizado',
        'Los cambios quedaron guardados en la acción de personal.',
      )
      qc.invalidateQueries({ queryKey: ['movimientos', servidorId ?? undefined] })
      qc.invalidateQueries({ queryKey: ['movimientos'] })
      // El detalle abierto en el drawer vive bajo otra clave.
      qc.invalidateQueries({ queryKey: ['movimiento'] })
      qc.invalidateQueries({ queryKey: ['bandeja-movimientos'] })
    },
    onError: (error) => {
      notificar.error(
        'No se pudo guardar el borrador de la acción de personal',
        getApiErrorMessage(error, 'Inténtalo de nuevo en unos segundos.'),
      )
    },
  })

  const transicionar = useMutation({
    mutationFn: ({ id, ...datos }: { id: number } & TransicionarData) =>
      expedienteService.transicionarMovimiento(id, datos),
    onSuccess: () => {
      notificar.exito('Acción actualizada', 'La acción de personal avanzó de estado.')
      qc.invalidateQueries({ queryKey: ['movimientos'] })
      qc.invalidateQueries({ queryKey: ['movimiento'] })
      qc.invalidateQueries({ queryKey: ['bandeja-movimientos'] })
      // Registrar una acción materializa o cierra el vínculo laboral.
      qc.invalidateQueries({ queryKey: ['servidores'] })
      qc.invalidateQueries({ queryKey: ['contratos'] })
      qc.invalidateQueries({ queryKey: ['actividad-laboral'] })
    },
    onError: (error) => {
      notificar.error(
        'No se pudo cambiar el estado de la acción de personal',
        getApiErrorMessage(error, 'Inténtalo de nuevo en unos segundos.'),
      )
    },
  })

  return { actualizarBorrador, transicionar }
}

export function useMovimiento(id: number | null) {
  return useQuery({
    queryKey: ['movimiento', id],
    queryFn: () => expedienteService.obtenerMovimiento(id!),
    enabled: id !== null,
    staleTime: 1000 * 30,
  })
}

export function useBandejaMovimientos(params?: {
  estado?: string
  tipo_movimiento?: string
  anio?: number
}) {
  return useQuery({
    queryKey: ['bandeja-movimientos', params],
    queryFn: () => expedienteService.listarBandejaMovimientos(params),
    staleTime: 1000 * 60,
  })
}
