'use client'

import { MotivoModal } from '@/components/ui'
import { useValidacionFormacion } from '../hooks/useValidacionFormacion'
import type { HistorialAcademicoServidor } from '@/types/api'

interface Props {
  /** El título o curso que se rechaza; sin él, el modal está cerrado. */
  registro: HistorialAcademicoServidor | null
  onClose:  () => void
}

/**
 * Rechazar un título o curso que el servidor registró desde el portal. El
 * motivo se ve en su «Mi perfil», así que se pide siempre.
 */
export function RechazarFormacionModal({ registro, onClose }: Props) {
  const { rechazar } = useValidacionFormacion()

  return (
    <MotivoModal
      opened={registro !== null}
      onClose={onClose}
      title="Rechazar título o curso"
      confirmLabel="Rechazar"
      destructiva
      cargando={rechazar.isPending}
      placeholder="Ej.: el PDF no corresponde al título declarado"
      onConfirm={(motivo) => {
        if (!registro) return
        rechazar.mutate(
          { servidorId: registro.servidor_id, id: registro.id, motivo },
          { onSuccess: onClose },
        )
      }}
      descripcion={
        <>
          <b>{registro?.titulo_capacitacion}</b> no entrará en la hoja de vida. El
          motivo se verá en «Mi perfil» del servidor, desde donde puede registrarlo
          de nuevo.
        </>
      }
    />
  )
}
