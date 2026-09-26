import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { movimientoService } from '../services/movimientoService'
import type { TransicionarData, ActualizarBorradorData } from '../services/movimientoService'
import { getApiErrorMessage } from '@/types/api'
import { notificar } from '@/components/ui'

export function useMovimientoMutations(servidorId?: number | null) {
  const qc = useQueryClient()

  const actualizarBorrador = useMutation({
    mutationFn: ({ id, data }: { id: number; data: ActualizarBorradorData }) =>
      movimientoService.actualizarBorrador(id, data),
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
      movimientoService.transicionar(id, datos),
    onSuccess: () => {
      notificar.exito('Acción actualizada', 'La acción de personal avanzó de estado.')
      qc.invalidateQueries({ queryKey: ['movimientos'] })
      qc.invalidateQueries({ queryKey: ['movimiento'] })
      qc.invalidateQueries({ queryKey: ['bandeja-movimientos'] })
      // Registrar una acción materializa o cierra el vínculo laboral.
      qc.invalidateQueries({ queryKey: ['servidores'] })
      // La ficha abierta vive bajo otra clave —['servidor', id], que no es
      // prefijo de ['servidores']— y useServidor la da por fresca cinco
      // minutos. Desde que las acciones de personal se aprueban dentro del
      // expediente, sin esto el encabezado seguía diciendo «Sin vínculo»
      // después de aprobar el Ingreso de esa misma persona.
      qc.invalidateQueries({ queryKey: ['servidor'] })
      qc.invalidateQueries({ queryKey: ['contratos'] })
      qc.invalidateQueries({ queryKey: ['actividad-laboral'] })
      // Una comisión de servicios o una licencia sin remuneración abren una
      // ausencia, y el panel que las lista no se enteraba.
      qc.invalidateQueries({ queryKey: ['ausencias-temporales'] })
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
    queryFn: () => movimientoService.obtener(id!),
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
    queryFn: () => movimientoService.listarBandeja(params),
    staleTime: 1000 * 60,
  })
}
