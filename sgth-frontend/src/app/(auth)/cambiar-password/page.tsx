import { Metadata } from 'next'
import { Box, Center, Card, Title, Text } from '@mantine/core'
import { ChangePasswordForm } from '@/features/auth/components/ChangePasswordForm'

export const metadata: Metadata = {
  title: 'Cambiar Contraseña | SGTH',
}

export default function ChangePasswordPage() {
  return (
    <Box
      style={{
        minHeight: '100vh',
        display: 'flex',
        flexDirection: 'column',
        justifyContent: 'center',
        alignItems: 'center',
        background: 'var(--mantine-color-gray-0)',
      }}
      p="md"
    >
      <Center w="100%">
        <Card radius="lg" withBorder p="xl" shadow="sm" w="100%" maw={400}>
          <Box mb="xl" ta="center">
            <Title order={2} fw={700} c="emerald.6" mb="xs">
              Cambio de Contraseña
            </Title>
            <Text c="dimmed" size="sm">
              SGTH - GAD Provincial de Esmeraldas
            </Text>
          </Box>
          <ChangePasswordForm />
        </Card>
      </Center>
    </Box>
  )
}
