import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { assistService, type RespuestaAssistPayload } from '../services/assistService'
import { clavesSso } from '../constants/claves'
import { notificar } from '@/components/ui'

export function useCampaniasAssist(params?: { periodo?: string }) {
  return useQuery({
    queryKey: clavesSso.assist.campanias.lista(params),
    queryFn: () => assistService.listarCampanias(params),
    staleTime: 1000 * 30,
  })
}

export function useResultadosAssist(campaniaId: number | null) {
  return useQuery({
    queryKey: clavesSso.assist.resultados(campaniaId),
    queryFn: () => assistService.obtenerResultados(campaniaId!),
    enabled: !!campaniaId,
    staleTime: 1000 * 30,
  })
}

export function useAssistMutations() {
  const qc = useQueryClient()

  const invalidar = () => {
    qc.invalidateQueries({ queryKey: clavesSso.assist.campanias.todas })
    // El tablero cuenta las campañas activas y las respuestas del período.
    qc.invalidateQueries({ queryKey: clavesSso.tablero.todo })
  }

  const crearCampania = useMutation({
    mutationFn: (data: { periodo: string; unidad_administrativa_id?: number | null; fecha_apertura: string; fecha_cierre?: string | null }) =>
      assistService.crearCampania(data),
    onSuccess: () => {
      notificar.exito(
        'Campaña creada',
        'La campaña de tamizaje ASSIST fue creada exitosamente.',
      )
      invalidar()
    },
    onError: notificar.alFallar('No se pudo crear la campaña ASSIST'),
  })

  const cerrarCampania = useMutation({
    mutationFn: (id: number) => assistService.cerrarCampania(id),
    onSuccess: () => {
      notificar.exito('Campaña cerrada', 'La campaña fue cerrada exitosamente.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo cerrar la campaña ASSIST'),
  })

  return { crearCampania, cerrarCampania }
}

// ── Cuestionario público (anónimo) ────────────────────────────────────

export function useCuestionarioAssist(codigo: string | null) {
  return useQuery({
    queryKey: clavesSso.assist.cuestionario(codigo),
    queryFn: () => assistService.obtenerCuestionarioPublico(codigo!),
    enabled: !!codigo,
    retry: false,
    staleTime: 1000 * 60,
  })
}

export function useEnviarRespuestaAssist(codigo: string) {
  return useMutation({
    mutationFn: (data: RespuestaAssistPayload) =>
      assistService.enviarRespuestaPublica(codigo, data),
  })
}
