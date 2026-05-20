import { Metadata } from 'next'
import { Box, Center, Card, Title, Text } from '@mantine/core'
import { CambiarPasswordForm } from '@/features/auth/components/CambiarPasswordForm'

export const metadata: Metadata = {
  title: 'Cambiar contraseña | SGTH',
}

export default function CambiarPasswordPage() {
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
              Cambiar contraseña
            </Title>
            <Text c="dimmed" size="sm">
              Por seguridad debes cambiar tu contraseña antes de continuar
            </Text>
          </Box>
          <CambiarPasswordForm />
        </Card>
      </Center>
    </Box>
  )
}
