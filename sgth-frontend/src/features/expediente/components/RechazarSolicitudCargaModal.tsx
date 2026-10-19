'use client'

import { MotivoModal } from '@/components/ui'
import type { SolicitudCargaFamiliar } from '@/types/api'
import { useValidacionCargas } from '../hooks/useValidacionCargas'
import { familiarDe } from './cargasPorValidar.columns'

interface Props {
  /** La solicitud que se rechaza; sin ella, el modal está cerrado. */
  solicitud: SolicitudCargaFamiliar | null
  onClose:   () => void
}

/**
 * Rechazar una solicitud de carga del Portal. El motivo se ve en «Mi perfil»
 * del servidor, desde donde puede presentar otra, así que se pide siempre.
 */
export function RechazarSolicitudCargaModal({ solicitud, onClose }: Props) {
  const { rechazar } = useValidacionCargas()

  return (
    <MotivoModal
      opened={solicitud !== null}
      onClose={onClose}
      title="Rechazar solicitud de carga"
      confirmLabel="Rechazar"
      destructiva
      cargando={rechazar.isPending}
      placeholder="Ej.: la partida de nacimiento no es legible"
      onConfirm={(motivo) => {
        if (!solicitud) return
        rechazar.mutate({ solicitud, motivo }, { onSuccess: onClose })
      }}
      descripcion={
        <>
          La solicitud de {solicitud?.tipo_etiqueta.toLowerCase()} de <b>{solicitud ? familiarDe(solicitud) : ''}</b> no
          cambia nada en el expediente. El motivo se verá en «Mi perfil» del servidor, desde donde puede
          presentar otra.
        </>
      }
    />
  )
}
