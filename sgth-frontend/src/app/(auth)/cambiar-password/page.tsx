import { Metadata } from 'next'
import { Box, Card, Title, Text } from '@mantine/core'
import { CambiarPasswordForm } from '@/features/auth/components/CambiarPasswordForm'
import classes from './cambiar-password.module.css'

export const metadata: Metadata = {
  title: 'Cambiar contraseña',
}

export default function CambiarPasswordPage() {
  return (
    <Box className={classes.pagina} p="md">
      <Card radius="lg" withBorder p="xl" shadow="sm" w="100%" maw={400}>
        <Box mb="xl" ta="center">
          <Title order={1} size="h2" fw={700} mb="xs">
            Cambiar contraseña
          </Title>
          <Text c="dimmed" size="sm">
            Es su primer acceso, o TI restableció su contraseña. Por seguridad,
            elija una nueva antes de continuar.
          </Text>
        </Box>
        <CambiarPasswordForm />
      </Card>
    </Box>
  )
}
