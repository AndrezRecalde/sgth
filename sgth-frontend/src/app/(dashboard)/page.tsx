import type { Metadata } from 'next'
import { Container, Text } from '@mantine/core'

export const metadata: Metadata = {
  title: 'Inicio',
  description: 'Panel de control principal del Sistema de Gestión de Talento Humano',
}

export default function DashboardPage() {
  return (
    <Container mt="xl">
      <Text size="xl" fw={600}>Dashboard — en construcción</Text>
    </Container>
  )
}
