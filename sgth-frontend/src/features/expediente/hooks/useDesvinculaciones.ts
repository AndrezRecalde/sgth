import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import {
  desvinculacionService, type EstadoPasoDesvinculacion, type FiltrosDesvinculaciones, type PasoDesvinculacion,
} from '../services/desvinculacionService'

/** Las desvinculaciones, por estado y por páginas (fase 7.4a). */
export function useDesvinculaciones(filtros: FiltrosDesvinculaciones) {
  return useQuery({
    queryKey: ['desvinculaciones', 'listado', filtros],
    queryFn: () => desvinculacionService.listar(filtros),
    staleTime: 1000 * 30,
    placeholderData: keepPreviousData,
  })
}

/** Una desvinculación, con sus pasos: los que se leen de su módulo, al día. */
export function useDesvinculacion(id: number | null) {
  return useQuery({
    queryKey: ['desvinculaciones', 'detalle', id],
    queryFn: () => desvinculacionService.obtener(id as number),
    enabled: id !== null,
    staleTime: 0,
  })
}

export function useMarcarPaso(id: number) {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: ({ paso, estado, nota }: { paso: PasoDesvinculacion; estado: EstadoPasoDesvinculacion; nota?: string }) =>
      desvinculacionService.marcar(id, paso, estado, nota),
    onSuccess: (r) => {
      notificar.exito(r.datos.estado === 'cerrada' ? 'Desvinculación completa' : 'Paso actualizado', r.mensaje)
      qc.invalidateQueries({ queryKey: ['desvinculaciones'] })
    },
    onError: notificar.alFallar('No se pudo actualizar el paso'),
  })
}

export function useDevolverBien(id: number) {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: ({ asignacionId, fecha, observaciones }: { asignacionId: number; fecha: string; observaciones?: string }) =>
      desvinculacionService.devolverBien(id, asignacionId, fecha, observaciones),
    onSuccess: (r) => {
      notificar.exito('Devolución registrada', r.mensaje)
      qc.invalidateQueries({ queryKey: ['desvinculaciones'] })
    },
    onError: notificar.alFallar('No se pudo registrar la devolución'),
  })
}
