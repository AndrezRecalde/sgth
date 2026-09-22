'use client'

import { IconHeartbeat } from '@tabler/icons-react'
import { DataState, SgthTable } from '@/components/ui'
import { useSolicitudesPendientesTriaje } from '../hooks/useSolicitudSignosVitales'
import { getSolicitudesTriajeColumns } from './solicitudesTriaje.columns'
import type { SolicitudCertificacion } from '../services/solicitudCertificacionService'

interface Props {
  onSeleccionar: (solicitud: SolicitudCertificacion) => void
}

export function SolicitudesPendientesTriajeList({ onSeleccionar }: Props) {
  const { data: solicitudes = [], isLoading, error } =
    useSolicitudesPendientesTriaje()

  return (
    <DataState
      loading={isLoading}
      error={error}
      empty={!solicitudes.length}
      emptyProps={{
        icon: IconHeartbeat,
        title: 'Sin pendientes',
        description: 'No hay solicitudes SSO pendientes de signos vitales.',
      }}
    >
      <SgthTable
        records={solicitudes}
        columns={getSolicitudesTriajeColumns({ onSeleccionar })}
        minHeight={200}
      />
    </DataState>
  )
}
