import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import { examenService } from '../services/examenService'
import type { EmitirOrdenExamenData } from '../services/examenService'

/**
 * El catálogo de exámenes. Cambia poco —lo toca la administración de vez en
 * cuando—, así que se guarda un buen rato.
 */
export function useCatalogoExamenes(activo = true) {
  return useQuery({
    queryKey: ['examenes', 'catalogo'],
    queryFn:  examenService.catalogo,
    enabled:  activo,
    staleTime: 1000 * 60 * 10,
  })
}

/** Los perfiles de la especialidad de la consulta: el odontólogo ve los suyos. */
export function usePerfilesExamen(especialidad: string | undefined, activo = true) {
  return useQuery({
    queryKey: ['examenes', 'perfiles', especialidad],
    queryFn:  () => examenService.perfiles(especialidad),
    enabled:  activo,
    staleTime: 1000 * 60 * 5,
  })
}

export function useOrdenesExamen(historiaClinicaId: number, consultaId?: number) {
  return useQuery({
    queryKey: ['examenes', 'ordenes', historiaClinicaId, consultaId],
    queryFn:  () => examenService.ordenes(historiaClinicaId, consultaId),
    enabled:  !!historiaClinicaId,
    staleTime: 1000 * 30,
  })
}

/**
 * Emite la orden. El fallo no se notifica aquí: el modal lleva cada error a su
 * campo y notifica solo lo que no tenga dónde ir.
 */
export function useEmitirOrdenExamen() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: (data: EmitirOrdenExamenData) => examenService.emitir(data),
    onSuccess: (orden) => {
      notificar.exito('Orden emitida', `Orden ${orden?.folio ?? ''} registrada.`)
      qc.invalidateQueries({ queryKey: ['examenes', 'ordenes'] })
    },
  })
}

export function useAnularOrdenExamen() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: ({ id, motivo }: { id: number; motivo: string }) =>
      examenService.anular(id, motivo),
    onSuccess: (orden) => {
      notificar.exito('Orden anulada', `La orden ${orden?.folio ?? ''} ya no espera resultados.`)
      qc.invalidateQueries({ queryKey: ['examenes', 'ordenes'] })
    },
    onError: notificar.alFallar('No se pudo anular la orden'),
  })
}
