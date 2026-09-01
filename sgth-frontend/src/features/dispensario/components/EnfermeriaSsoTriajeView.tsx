'use client'

import { useState } from 'react'
import { Container } from '@mantine/core'

import { SolicitudesPendientesTriajeList } from './SolicitudesPendientesTriajeList'
import { SolicitudSignosVitalesForm } from './SolicitudSignosVitalesForm'
import type { SolicitudCertificacion } from '../services/solicitudCertificacionService'

export function EnfermeriaSsoTriajeView() {
  // La pantalla alterna entre la lista y el formulario de la solicitud
  // elegida; no hay navegación de por medio.
  const [solicitudSel, setSolicitudSel] = useState<SolicitudCertificacion | null>(null)

  if (!solicitudSel) {
    return <SolicitudesPendientesTriajeList onSeleccionar={setSolicitudSel} />
  }

  return (
    <Container size="sm" px={0}>
      <SolicitudSignosVitalesForm
        solicitud={solicitudSel}
        onCreado={() => setSolicitudSel(null)}
        onCancelar={() => setSolicitudSel(null)}
      />
    </Container>
  )
}
