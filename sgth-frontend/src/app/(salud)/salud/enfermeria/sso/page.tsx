'use client'

import { useState } from 'react'
import { Stack, Container } from '@mantine/core'
import { IconShieldCheck } from '@tabler/icons-react'
import { PageHeader } from '@/components/ui/PageHeader'
import { SolicitudesPendientesTriajeList } from '@/features/dispensario/components/SolicitudesPendientesTriajeList'
import { SolicitudSignosVitalesForm } from '@/features/dispensario/components/SolicitudSignosVitalesForm'
import type { SolicitudCertificacion } from '@/features/dispensario/services/solicitudCertificacionService'

export default function EnfermeriaSsoPage() {
  const [solicitudSel, setSolicitudSel] = useState<SolicitudCertificacion | null>(null)

  return (
    <Stack gap="md">
      <PageHeader
        title="Enfermería"
        subtitle="Atención SSO — signos vitales previos al FEMO"
        icon={<IconShieldCheck size={24} />}
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
    </Stack>
  )
}
