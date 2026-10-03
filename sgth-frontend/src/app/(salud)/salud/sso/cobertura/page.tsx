import type { Metadata } from 'next'
import { CoberturaTab } from '@/features/dispensario/components/CoberturaTab'
import { PageHeader, PageShell } from '@/components/ui'

export const metadata: Metadata = {
  title: 'Cobertura de evaluaciones',
  description: 'Quién tiene su evaluación médica ocupacional al día',
}

/**
 * La misma cobertura que Talento Humano ve en Certificaciones médicas, ahora
 * también en el Dispensario: es la única pantalla que muestra a quien nunca
 * tuvo una evaluación. El lote de solicitudes no aparece aquí: lo pide
 * `solicitar-certificacion-medica`, que el médico no tiene.
 */
export default function CoberturaSsoPage() {
  return (
    <PageShell>
      <PageHeader
        title="Cobertura de evaluaciones"
        description="Evaluaciones periódicas vencidas, por vencer, al día y de quien nunca tuvo una"
      />
      <CoberturaTab />
    </PageShell>
  )
}
