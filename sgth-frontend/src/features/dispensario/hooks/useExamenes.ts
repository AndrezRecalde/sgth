import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import { abrirOGuardarArchivo } from '@/lib/archivo'
import { getApiErrorMessage } from '@/types/api'
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

/**
 * Abre el impreso de la orden para dárselo al paciente, como la receta. Si el
 * navegador bloquea la pestaña —se abre al volver del servidor, fuera del
 * clic—, se descarga y se dice dónde quedó.
 */
export function useOrdenExamenPdf() {
  const [abriendo, setAbriendo] = useState<number | null>(null)

  const abrir = async (id: number, folio: string) => {
    setAbriendo(id)
    try {
      const blob = await examenService.pdf(id)
      if (!abrirOGuardarArchivo(blob, `orden-examenes-${folio}.pdf`)) {
        notificar.aviso('La orden se descargó', 'El navegador bloqueó la pestaña; la encontrará en Descargas.')
      }
    } catch (error: unknown) {
      notificar.error('No se pudo generar la orden', getApiErrorMessage(error))
    } finally {
      setAbriendo(null)
    }
  }

  return { abrir, abriendo }
}
