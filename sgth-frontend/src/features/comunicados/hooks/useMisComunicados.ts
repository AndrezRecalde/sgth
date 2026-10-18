import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import { guardarArchivo } from '@/lib/archivo'
import { useAuthStore } from '@/store/auth.store'
import { clavesMisComunicados } from '../constants/claves'
import { misComunicadosService } from '../services/misComunicadosService'
import type { ComunicadoAdjunto, FiltrosMisComunicados } from '../services/tipos'

export const POR_PAGINA_MIS_COMUNICADOS = 12

/** Cada cuánto la campana vuelve a mirar si hay algo nuevo (D7). */
const CADA_CINCO_MINUTOS = 1000 * 60 * 5

const SIN_PERMISOS: readonly string[] = []

/** Las del Portal, vigentes o del archivo, filtradas y paginadas en el servidor. */
export function useMisComunicados(filtros: FiltrosMisComunicados, page: number) {
  return useQuery({
    queryKey: clavesMisComunicados.lista(filtros, page),
    queryFn: () => misComunicadosService.listar(filtros, page, POR_PAGINA_MIS_COMUNICADOS),
    placeholderData: keepPreviousData,
    staleTime: 1000 * 30,
  })
}

/**
 * Lo del inicio y la campana. La campana está en todos los subsistemas, así
 * que solo pregunta quien tiene el Portal (`acceso-autoservicio`): sin él, el
 * API respondería 403 en cada pantalla.
 */
export function useResumenComunicados() {
  const permisos = useAuthStore((s) => s.usuario?.permisos ?? SIN_PERMISOS)

  return useQuery({
    queryKey: clavesMisComunicados.resumen,
    queryFn: misComunicadosService.resumen,
    enabled: permisos.includes('acceso-autoservicio'),
    staleTime: 1000 * 60,
    refetchInterval: CADA_CINCO_MINUTOS,
  })
}

/**
 * Una publicación del Portal. Abrirla la deja leída en el backend, así que al
 * llegar se refrescan el resumen y el listado: el punto de «nuevo» y la
 * campana tienen que apagarse sin esperar cinco minutos.
 */
export function useComunicadoPortal(id: number) {
  const qc = useQueryClient()

  return useQuery({
    queryKey: clavesMisComunicados.detalle(id),
    queryFn: async () => {
      const comunicado = await misComunicadosService.ver(id)
      qc.invalidateQueries({ queryKey: clavesMisComunicados.resumen })
      qc.invalidateQueries({ queryKey: clavesMisComunicados.listas })
      return comunicado
    },
    enabled: id > 0,
    // Un 404 aquí es la respuesta —no existe o no es para usted—, no un fallo de red.
    retry: false,
  })
}

export function useConfirmarLectura(id: number) {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: () => misComunicadosService.confirmar(id),
    onSuccess: (comunicado) => {
      qc.setQueryData(clavesMisComunicados.detalle(id), comunicado)
      qc.invalidateQueries({ queryKey: clavesMisComunicados.resumen })
      qc.invalidateQueries({ queryKey: clavesMisComunicados.listas })
      notificar.exito('Lectura confirmada', 'Quedó registrada a su nombre, con la fecha y la hora.')
    },
    onError: notificar.alFallar('No se pudo confirmar la lectura'),
  })
}

export function useDescargarAdjuntoPortal(id: number) {
  return useMutation({
    mutationFn: async (adjunto: ComunicadoAdjunto) =>
      guardarArchivo(await misComunicadosService.descargarAdjunto(id, adjunto.id), adjunto.nombre),
    onError: notificar.alFallar('No se pudo descargar el documento'),
  })
}
