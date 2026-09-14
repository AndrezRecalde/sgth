import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { inventarioMedicinaService } from '../services/inventarioMedicinaService'
import type {
  CrearMedicinaData, ActualizarMedicinaData,
} from '../services/inventarioMedicinaService'
import { notificar } from '@/components/ui'

export function useInventarioMedicinas(
  params?: Record<string, unknown>
) {
  return useQuery({
    queryKey: ['inventario-medicinas', params],
    queryFn:  () => inventarioMedicinaService.listar(params),
    staleTime: 1000 * 30,
  })
}

/**
 * Los lotes con existencias de una medicina, en orden FEFO.
 *
 * Se piden al abrir el diálogo de baja: el listado no los trae, y elegir de
 * qué lote sale una baja necesita verlos con su caducidad y sus unidades.
 */
export function useLotesDeMedicina(id: number | null, enabled = true) {
  return useQuery({
    queryKey: ['inventario-medicinas', 'lotes', id],
    queryFn:  () => inventarioMedicinaService.obtener(id!)
      .then(m => m.lotes ?? []),
    enabled:  !!id && enabled,
    staleTime: 0,
  })
}

export function useKardexMedicina(id: number | null, page = 1) {
  return useQuery({
    queryKey: ['inventario-medicinas', 'kardex', id, page],
    queryFn:  () => inventarioMedicinaService.kardex(id!, page),
    enabled:  !!id,
    // La página anterior se queda a la vista mientras llega la siguiente.
    placeholderData: (anterior) => anterior,
  })
}

/**
 * `enabled` lo usa el menú lateral, que vive en los tres subsistemas: solo
 * tiene sentido pedir el conteo a quien puede entrar al inventario.
 */
export function useStockBajoCount({ enabled = true }: { enabled?: boolean } = {}) {
  return useQuery({
    queryKey: ['inventario-medicinas', 'stock-bajo-count'],
    queryFn:  () => inventarioMedicinaService.contarStockBajo(),
    enabled,
    staleTime: 1000 * 60,
    refetchInterval: 1000 * 60 * 5,
  })
}

export function useInventarioMutations() {
  const qc = useQueryClient()

  const invalidar = () =>
    qc.invalidateQueries({ queryKey: ['inventario-medicinas'] })

  const crear = useMutation({
    mutationFn: (data: CrearMedicinaData) =>
      inventarioMedicinaService.crear(data),
    onSuccess: () => {
      notificar.exito('Medicina registrada', 'La medicina fue agregada al inventario.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo registrar la medicina'),
  })

  const actualizar = useMutation({
    mutationFn: ({ id, data }: {
      id: number; data: ActualizarMedicinaData
    }) => inventarioMedicinaService.actualizar(id, data),
    onSuccess: () => {
      notificar.exito('Medicina actualizada', 'Los datos fueron actualizados.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo actualizar la medicina'),
  })

  const registrarBaja = useMutation({
    mutationFn: ({ id, cantidad, motivo, loteId }: {
      id: number; cantidad: number; motivo: string; loteId?: number | null
    }) => inventarioMedicinaService.registrarBaja(id, cantidad, motivo, loteId),
    onSuccess: () => {
      notificar.exito(
        'Existencias dadas de baja',
        'Las unidades salieron del inventario y quedó constancia en el kardex.',
      )
      invalidar()
      // Lo que se retiró ya no debe ofrecerse al recetar.
      qc.invalidateQueries({ queryKey: ['medicinas-buscar'] })
    },
    onError: notificar.alFallar('No se pudieron dar de baja las existencias'),
  })

  const ajustarInventario = useMutation({
    mutationFn: ({ id, nuevoStock, motivo }: {
      id: number; nuevoStock: number; motivo: string
    }) => inventarioMedicinaService.ajustarInventario(
      id, nuevoStock, motivo
    ),
    onSuccess: () => {
      notificar.exito('Inventario ajustado', 'El stock fue corregido correctamente.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo ajustar el inventario'),
  })


  const toggleEstado = useMutation({
    mutationFn: (id: number) =>
      inventarioMedicinaService.toggleEstado(id),
    onSuccess: (data) => {
      const reactivada = !!data?.estado
      notificar.exito(
        reactivada ? 'Medicina reactivada' : 'Medicina retirada del catálogo',
        reactivada
          ? 'Vuelve a estar disponible para recetar y despachar.'
          : 'Deja de aparecer en recetas y despachos. Sus existencias no se movieron.',
      )
      invalidar()
      // Deja de estar disponible —o vuelve a estarlo— para recetar.
      qc.invalidateQueries({ queryKey: ['medicinas-buscar'] })
    },
    onError: notificar.alFallar('No se pudo cambiar el estado de la medicina'),
  })

  return { crear, actualizar, registrarBaja, ajustarInventario, toggleEstado }
}
