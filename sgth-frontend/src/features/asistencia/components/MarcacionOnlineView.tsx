'use client'

import { PageHeader, PageShell } from '@/components/ui'
import { MarcacionOnlineTab } from './MarcacionOnlineTab'

/**
 * La marcación en línea es autoservicio: vive en el portal, donde entra todo el
 * personal. Antes estaba en el subsistema SGTH, que el rol `servidor` no ve.
 */
export function MarcacionOnlineView() {
  return (
    <PageShell>
      <PageHeader
        title="Marcación online"
        description="Registro de entrada y salida desde el navegador, con ubicación"
      />
      <MarcacionOnlineTab />
    </PageShell>
  )
}
