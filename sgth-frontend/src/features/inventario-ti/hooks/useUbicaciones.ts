import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import { clavesInventario } from '../constants/claves'
import { ubicacionesService, type DatosMudanza } from '../services/ubicacionesService'

export const useUbicacionesPorUnidad = () =>
  useQuery({ queryKey: clavesInventario.ubicaciones.porUnidad, queryFn: ubicacionesService.porUnidad })

/**
 * La ubicación que se propone: la habitual de la unidad o, al entregar, la de
 * la unidad del custodio. Sin unidad ni servidor no consulta.
 */
export function useUbicacionSugerida({ unidadId = null, servidorId = null }: { unidadId?: number | null; servidorId?: number | null }) {
  return useQuery({
    queryKey: clavesInventario.ubicaciones.sugerida(unidadId, servidorId),
    queryFn: () => ubicacionesService.sugerida({ unidad_administrativa_id: unidadId, servidor_id: servidorId }),
    enabled: !!unidadId || !!servidorId,
  })
}

export function useUbicacionesMutations() {
  const qc = useQueryClient()

  const fijar = useMutation({
    mutationFn: ({ unidadId, ubicacionId }: { unidadId: number; ubicacionId: number | null }) => ubicacionesService.fijar(unidadId, ubicacionId),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['inventario-ti', 'ubicaciones'] }),
    onError: notificar.alFallar('No se pudo guardar la ubicación habitual'),
  })

  // Al aplicarla cambian las fichas y los listados: se invalida todo el inventario.
  const mudanza = useMutation({
    mutationFn: (data: DatosMudanza) => ubicacionesService.mudanza(data),
    onSuccess: (r) => {
      if (!r.aplicada) return
      notificar.exito('Mudanza registrada', `${r.total} ${r.total === 1 ? 'equipo cambió' : 'equipos cambiaron'} de ubicación, con sus componentes.`)
      qc.invalidateQueries({ queryKey: clavesInventario.raiz })
    },
    onError: notificar.alFallarSalvoCampos('No se pudo registrar la mudanza'),
  })

  return { fijar, mudanza }
}
