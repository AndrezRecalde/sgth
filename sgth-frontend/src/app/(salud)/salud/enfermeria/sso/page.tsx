'use client'

import { useState } from 'react'
import { Container } from '@mantine/core'

import { SolicitudesPendientesTriajeList } from '@/features/dispensario/components/SolicitudesPendientesTriajeList'
import { SolicitudSignosVitalesForm } from '@/features/dispensario/components/SolicitudSignosVitalesForm'
import type { SolicitudCertificacion } from '@/features/dispensario/services/solicitudCertificacionService'
import { PageHeader, PageShell } from '@/components/ui'

export default function EnfermeriaSsoPage() {
  const [solicitudSel, setSolicitudSel] = useState<SolicitudCertificacion | null>(null)

  return (
    <PageShell>
      <PageHeader
        title="Enfermería"
        description="Atención SSO — signos vitales previos al FEMO"
      />

      {solicitudSel ? (
        <Container size="sm" px={0}>
          <SolicitudSignosVitalesForm
            solicitud={solicitudSel}
            onCreado={() => setSolicitudSel(null)}
            onCancelar={() => setSolicitudSel(null)}
          />
        </Container>
      ) : (
        <SolicitudesPendientesTriajeList onSeleccionar={setSolicitudSel} />
      )}
    </PageShell>
  )
}
