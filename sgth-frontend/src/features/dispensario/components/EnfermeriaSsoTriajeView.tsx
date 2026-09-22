'use client'

import { useState } from 'react'
import { Box } from '@mantine/core'

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
    // Mismo ancho de lectura que tenía el `Container`, alineado a la izquierda
    // como el título de la página. La lista de arriba sí ocupa todo el ancho.
    <Box maw={720}>
      <SolicitudSignosVitalesForm
        solicitud={solicitudSel}
        onCreado={() => setSolicitudSel(null)}
        onCancelar={() => setSolicitudSel(null)}
      />
    </Box>
  )
}
