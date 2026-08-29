import type { Metadata } from 'next'
import { Text } from '@mantine/core'
import { PageHeader, PageShell } from '@/components/ui'

export const metadata: Metadata = {
  title: 'Talento Humano',
  description:
    'Panel principal del subsistema de Gestión de Talento Humano del GAD Provincial de Esmeraldas',
}

export default function SgthHomePage() {
  return (
    <PageShell>
      <PageHeader
        title="Gestión de Talento Humano"
        description="Panel principal del subsistema"
      />
      <Text c="dimmed">Módulo en construcción.</Text>
    </PageShell>
  )
}
