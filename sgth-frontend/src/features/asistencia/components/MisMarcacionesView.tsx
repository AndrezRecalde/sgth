'use client'

import { PageHeader, PageShell } from '@/components/ui'
import { MarcacionesTab } from './MarcacionesTab'

/**
 * Las marcaciones propias, en el portal. El menú ya enlazaba aquí, pero la
 * página no existía: el rol `servidor`, que no entra al subsistema SGTH, no
 * tenía dónde consultarlas.
 */
export function MisMarcacionesView() {
  return (
    <PageShell>
      <PageHeader
        title="Mis marcaciones"
        description="Sus registros del reloj biométrico por rango de fechas"
      />
      <MarcacionesTab soloPropias />
    </PageShell>
  )
}
