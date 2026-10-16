import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import { abrirOGuardarArchivo } from '@/lib/archivo'
import { clavesHelpdesk } from '@/features/helpdesk/constants/claves'
import { clavesInventario } from '@/features/inventario-ti/constants/claves'
import { clavesConsumibles } from '../constants/claves'
import { solicitudesService, type DespachoData, type FiltrosSolicitudes, type NuevaSolicitudData } from '../services/solicitudesService'

export const useSolicitudes = (filtros: FiltrosSolicitudes, page = 1, enabled = true) =>
  useQuery({
    queryKey: clavesConsumibles.solicitudes.lista(filtros, page),
    queryFn: () => solicitudesService.listar(filtros, page),
    enabled,
  })

/**
 * Generar, despachar, anular e imprimir la solicitud a bodega (5.4c). El
 * despacho deja entregas en el kárdex y cierra el ticket: se invalidan el
 * inventario y la mesa de ayuda.
 */
export function useSolicitudMutations() {
  const qc = useQueryClient()
  const refrescar = () => qc.invalidateQueries({ queryKey: clavesConsumibles.solicitudes.todas })

  const crear = useMutation({
    mutationFn: (data: NuevaSolicitudData) => solicitudesService.crear(data),
    onSuccess: (s) => {
      notificar.exito(`Solicitud ${s?.codigo ?? ''} generada`, 'Imprímala para las firmas y llévela a Bodega.')
      refrescar()
    },
    onError: notificar.alFallarSalvoCampos('No se pudo generar la solicitud a bodega'),
  })
  const despachar = useMutation({
    mutationFn: ({ id, data }: { id: number; data: DespachoData }) => solicitudesService.despachar(id, data),
    onSuccess: (r) => {
      notificar.exito('Despacho anotado', r.mensaje)
      qc.invalidateQueries({ queryKey: clavesInventario.raiz })
      qc.invalidateQueries({ queryKey: clavesHelpdesk.raiz })
    },
    onError: notificar.alFallarSalvoCampos('No se pudo anotar el despacho de Bodega'),
  })
  const anular = useMutation({
    mutationFn: ({ id, motivo }: { id: number; motivo: string }) => solicitudesService.anular(id, motivo),
    onSuccess: () => {
      notificar.exito('Solicitud anulada')
      refrescar()
    },
    onError: notificar.alFallar('No se pudo anular la solicitud a bodega'),
  })
  const pdf = useMutation({
    mutationFn: async ({ id, codigo }: { id: number; codigo: string }) =>
      abrirOGuardarArchivo(await solicitudesService.pdf(id), `${codigo.toLowerCase()}.pdf`),
    onError: notificar.alFallar('No se pudo generar el PDF de la solicitud'),
  })

  return { crear, despachar, anular, pdf }
}
