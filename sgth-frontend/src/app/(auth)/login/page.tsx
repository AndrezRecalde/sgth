import { Metadata } from 'next'
import { Box, Center } from '@mantine/core'
import { LoginForm } from '@/features/auth/components/LoginForm'

export const metadata: Metadata = {
  title: 'Iniciar sesión | SGTH',
}

export default function LoginPage() {
  return (
    <Box bg="gray.0" style={{ minHeight: '100vh', display: 'flex', flexDirection: 'column' }}>
      <Center style={{ flex: 1 }}>
        <LoginForm />
      </Center>
    </Box>
  )
}
