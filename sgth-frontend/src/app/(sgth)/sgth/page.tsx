import type { Metadata } from 'next'
import { PageHeader, PageShell } from '@/components/ui'

export const metadata: Metadata = {
  title: 'Talento Humano',
  description:
    'Subsistema de Gestión de Talento Humano del GAD Provincial de Esmeraldas',
}

/**
 * La entrada del SGTH, sin contenido propio. Su panel estaba en construcción
 * y salió del menú (docs/plan-roles-y-accesos.md, PR 9): el shell lleva a
 * quien llega aquí a su primera pantalla del menú, o le dice que no tiene
 * ninguna (`GuardaDePantalla`), así que esto no llega a pintarse.
 */
export default function SgthHomePage() {
  return (
    <PageShell>
      <PageHeader title="Gestión de Talento Humano" />
    </PageShell>
  )
}
