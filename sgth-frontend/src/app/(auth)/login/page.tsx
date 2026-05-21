import { Metadata } from 'next'
import { Box, Card, Text, Title, Divider, Anchor, Stack, Center } from '@mantine/core'
import { LoginForm } from '@/features/auth/components/LoginForm'

export const metadata: Metadata = {
  title: 'Iniciar sesión | SGTH',
}

export default function LoginPage() {
  return (
    <Box
      style={{
        minHeight: '100vh',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        background: 'var(--mantine-color-gray-0)',
      }}
      p="md"
    >
      <Stack w="100%" maw={400} gap={0}>
        <Card radius="lg" withBorder p={0} style={{ overflow: 'hidden' }}>

          {/* Header Navy */}
          <Box
            p="xl"
            ta="center"
            style={{ background: '#0D1F2D' }}
          >
            <Title
              order={1}
              style={{
                fontSize: '36px',
                fontWeight: 800,
                color: '#10B981',
                letterSpacing: '-1px',
                lineHeight: 1,
              }}
            >
              INTRANET
            </Title>
            <Text
              size="xs"
              mt={6}
              style={{
                color: 'rgba(255,255,255,0.5)',
                letterSpacing: '0.06em',
                textTransform: 'uppercase',
                fontWeight: 500,
              }}
            >
              GAD Provincial de Esmeraldas
            </Text>
            <Box
              mt={10}
              mx="auto"
              style={{
                width: 32,
                height: 2,
                background: '#059669',
                borderRadius: 1,
              }}
            />
          </Box>

          {/* Body */}
          <Box p="xl">
            <Text size="sm" c="dimmed" mb="md" fw={500}>
              Inicia sesión para continuar
            </Text>
            <LoginForm />
          </Box>

          {/* Footer */}
          <Divider />
          <Box p="md" ta="center">
            <Text size="xs" c="dimmed">
              Sistema de Gestión de{' '}
              <Text span c="emerald.6" fw={500}>
                Talento Humano
              </Text>
            </Text>
            <Center mt={8} style={{ gap: 8 }}>
              <Box
                component="span"
                style={{
                  fontSize: 10,
                  padding: '2px 10px',
                  borderRadius: 100,
                  background: 'var(--mantine-color-emerald-0)',
                  color: 'var(--mantine-color-emerald-8)',
                  border: '0.5px solid var(--mantine-color-emerald-2)',
                  fontWeight: 500,
                }}
              >
                v1.0
              </Box>
              <Box
                component="span"
                style={{
                  fontSize: 10,
                  padding: '2px 10px',
                  borderRadius: 100,
                  background: 'var(--mantine-color-emerald-0)',
                  color: 'var(--mantine-color-emerald-8)',
                  border: '0.5px solid var(--mantine-color-emerald-2)',
                  fontWeight: 500,
                }}
              >
                Solo uso institucional
              </Box>
            </Center>
            <Anchor
              href="https://www.gadpe.gob.ec/webmail"
              target="_blank"
              rel="noopener noreferrer"
              size="xs"
              c="dimmed"
              mt={10}
              display="block"
              style={{ textDecoration: 'none' }}
            >
              ¿Olvidaste tu contraseña? Accede a tu{' '}
              <Text span c="emerald.6" style={{ textDecoration: 'underline' }}>
                correo institucional
              </Text>
            </Anchor>
          </Box>
        </Card>
      </Stack>
    </Box>
  )
}
