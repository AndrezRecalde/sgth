import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { psicosocialService, type RespuestaPsicosocialPayload } from '../services/psicosocialService'
import { clavesSso } from '../constants/claves'
import { notificar } from '@/components/ui'

export function useCampaniasPsicosocial(params?: { periodo?: string }) {
  return useQuery({
    queryKey: clavesSso.psicosocial.campanias.lista(params),
    queryFn: () => psicosocialService.listarCampanias(params),
    staleTime: 1000 * 30,
  })
}

export function useResultadosPsicosociales(campaniaId: number | null) {
  return useQuery({
    queryKey: clavesSso.psicosocial.resultados(campaniaId),
    queryFn: () => psicosocialService.obtenerResultados(campaniaId!),
    enabled: !!campaniaId,
    staleTime: 1000 * 30,
  })
}

export function usePsicosocialMutations() {
  const qc = useQueryClient()

  const invalidar = () => {
    qc.invalidateQueries({ queryKey: clavesSso.psicosocial.campanias.todas })
    // El tablero cuenta las campañas activas y las respuestas del período.
    qc.invalidateQueries({ queryKey: clavesSso.tablero.todo })
  }

  const crearCampania = useMutation({
    mutationFn: (data: { periodo: string; unidad_administrativa_id?: number | null; fecha_apertura: string; fecha_cierre?: string | null }) =>
      psicosocialService.crearCampania(data),
    onSuccess: () => {
      notificar.exito(
        'Campaña creada',
        'La campaña de evaluación psicosocial fue creada exitosamente.',
      )
      invalidar()
    },
    onError: notificar.alFallar('No se pudo crear la campaña psicosocial'),
  })

  const cerrarCampania = useMutation({
    mutationFn: (id: number) => psicosocialService.cerrarCampania(id),
    onSuccess: () => {
      notificar.exito('Campaña cerrada', 'La campaña fue cerrada exitosamente.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo cerrar la campaña psicosocial'),
  })

  return { crearCampania, cerrarCampania }
}

// ── Cuestionario público (anónimo) ────────────────────────────────────

export function useCuestionarioPsicosocial(codigo: string | null) {
  return useQuery({
    queryKey: clavesSso.psicosocial.cuestionario(codigo),
    queryFn: () => psicosocialService.obtenerCuestionarioPublico(codigo!),
    enabled: !!codigo,
    retry: false,
    staleTime: 1000 * 60,
  })
}

export function useEnviarRespuestaPsicosocial(codigo: string) {
  return useMutation({
    mutationFn: (data: RespuestaPsicosocialPayload) =>
      psicosocialService.enviarRespuestaPublica(codigo, data),
  })
}
