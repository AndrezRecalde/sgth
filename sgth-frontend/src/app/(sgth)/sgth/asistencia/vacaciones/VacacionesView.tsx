'use client'

import { Suspense } from 'react'
import { PageHeader, PageShell } from '@/components/ui'
import { VacacionesTab } from '@/features/asistencia/components/VacacionesTab'

export function VacacionesView() {
  return (
    <PageShell>
      <PageHeader
        title="Vacaciones"
        description="Solicitudes de vacaciones: registro, aprobación y anulación"
      />
      {/*
        La pestaña lee `?folio=` del QR con `useSearchParams()`, y Next pide un
        límite de Suspense alrededor para no renderizar en el cliente todo lo
        que está por encima: así el encabezado sigue saliendo en el HTML inicial.
      */}
      <Suspense fallback={null}>
        <VacacionesTab />
      </Suspense>
    </PageShell>
  )
}
