import { useState } from 'react'
import { notificar } from '@/components/ui'
import { useActualizarFemo, useCrearFemo } from './useFemo'
import { useCompletarSolicitud } from './useSolicitudCertificacion'
import type { CrearFemoData } from '../services/femoService'

interface Opciones {
  solicitudId:      number
  construirPayload: () => CrearFemoData | null
}

/**
 * Guardar la ficha FEMO de una solicitud y emitir su dictamen.
 *
 * La ficha es el borrador de la solicitud: la primera vez se crea enlazada a
 * ella, y desde entonces se actualiza. Así «Continuar FEMO» retoma lo guardado
 * en vez de abrir un asistente vacío que creaba una segunda ficha.
 *
 * El dictamen no se elige aparte: es la aptitud de la sección L. Emitirlo
 * guarda primero lo último que se tocó y después cierra la solicitud.
 */
export function useGuardarFemo({ solicitudId, construirPayload }: Opciones) {
  const crear      = useCrearFemo()
  const actualizar = useActualizarFemo()
  const completar  = useCompletarSolicitud()

  const [fichaId, setFichaId] = useState<number | null>(null)

  /** Devuelve el id de la ficha guardada, o null si no se pudo. */
  const guardar = async (): Promise<number | null> => {
    const payload = construirPayload()
    if (!payload) {
      notificar.aviso('Faltan datos', 'Complete la sección A para poder guardar la ficha.')
      return null
    }

    try {
      if (fichaId) {
        await actualizar.mutateAsync({ id: fichaId, data: payload })
        return fichaId
      }
      const ficha = await crear.mutateAsync({ ...payload, solicitud_id: solicitudId })
      setFichaId(ficha.id)
      return ficha.id
    } catch {
      // El aviso del error ya lo dan las mutaciones.
      return null
    }
  }

  /** Guarda y emite el dictamen. Devuelve si la evaluación quedó cerrada. */
  const emitirDictamen = async (): Promise<boolean> => {
    const id = await guardar()
    if (!id) return false

    try {
      await completar.mutateAsync(solicitudId)
      return true
    } catch {
      return false
    }
  }

  return {
    fichaId,
    setFichaId,
    guardar,
    emitirDictamen,
    guardando: crear.isPending || actualizar.isPending,
    emitiendo: completar.isPending,
  }
}
