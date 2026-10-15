'use client'

import { Alert, Center, Container, Image, Paper, Skeleton, Stack, Text, Title } from '@mantine/core'
import { IconAlertTriangle, IconCircleCheck, IconIdOff, IconShieldX } from '@tabler/icons-react'
import { isAxiosError } from 'axios'
import { StatusBadge } from '@/components/ui'
import { useVerificacionCredencial } from '../hooks/useCredenciales'
import { useIdentidadPublica } from '../hooks/useIdentidadPublica'
import { LogoInstitucional } from './LogoInstitucional'

interface Props {
  codigo: string
}

/**
 * La verificación pública de la credencial (docs/diseno-comunicacion-social.md,
 * 6.4): aquí llega quien escanea el QR del reverso —en una puerta, en un
 * operativo— y no tiene cuenta en el sistema.
 *
 * Vigente: la foto, el nombre, el cargo y la unidad de hoy, para cotejarlos con
 * la persona que se tiene delante [D9]. No vigente: solo eso, sin decir de
 * quién era. La cédula, la sangre y el contacto no llegan nunca del backend.
 */
export function VerificacionCredencialView({ codigo }: Props) {
  const { data: credencial, isLoading, error } = useVerificacionCredencial(codigo)
  const { data: identidad } = useIdentidadPublica()

  // Solo un 404 dice que la tarjeta no salió de aquí. Un 429 —la ruta va
  // limitada por IP— o un fallo de red no dicen nada de la tarjeta, y
  // presentarlos como «no encontrada» acusaría de falsa a una auténtica.
  const estado = isAxiosError(error) ? error.response?.status : undefined

  return (
    <Container size="xs" py="xl">
      <Stack gap="lg">
        <Stack gap="xs" align="center" ta="center">
          <LogoInstitucional alto={56} />
          <Title order={1} size="h2">Verificación de credencial</Title>
          <Text size="sm" c="dimmed">{identidad?.datos.nombre_oficial}</Text>
        </Stack>

        {isLoading && <Skeleton height={360} radius="lg" />}

        {error && estado !== 404 && (
          <Alert variant="light" color="amber" icon={<IconAlertTriangle size={18} />} title="No se pudo comprobar ahora">
            {estado === 429
              ? 'Se hicieron demasiadas consultas desde esta red. Espere un minuto y vuelva a escanear el código.'
              : 'El sistema no respondió. Intente de nuevo en unos minutos.'}
          </Alert>
        )}

        {estado === 404 && (
          <Alert variant="light" color="red" icon={<IconIdOff size={18} />} title="No se encontró esta credencial">
            Ninguna credencial emitida por esta institución corresponde a este código. Si escaneó el QR de una tarjeta y
            llegó aquí, la tarjeta no es auténtica.
          </Alert>
        )}

        {credencial && !credencial.vigente && (
          <Alert variant="light" color="red" icon={<IconShieldX size={18} />} title="Esta credencial no está vigente">
            La tarjeta salió de esta institución, pero ya no vale: se revocó, venció o su titular dejó de trabajar aquí.
            No la acepte como identificación.
          </Alert>
        )}

        {credencial?.vigente && (
          <Paper withBorder radius="lg" p="lg">
            <Stack gap="md" align="center" ta="center">
              <StatusBadge tone="success" size="lg">
                <IconCircleCheck size={14} style={{ verticalAlign: 'middle' }} />
                {' '}Credencial vigente
              </StatusBadge>

              {credencial.foto && (
                <Image src={credencial.foto} alt={`Foto de ${credencial.nombre}`} w={180} radius="md" />
              )}

              <Stack gap={4}>
                <Text fw={700} size="lg">{credencial.nombre}</Text>
                {credencial.cargo && <Text size="sm">{credencial.cargo}</Text>}
                {credencial.unidad && <Text size="sm" c="dimmed">{credencial.unidad}</Text>}
              </Stack>

              <Center>
                <Text size="xs" c="dimmed" maw={360}>
                  Coteje la foto y el nombre con la persona que presenta la tarjeta. El cargo y la unidad son los de hoy:
                  si la tarjeta dice otros, está desactualizada, pero sigue siendo auténtica.
                </Text>
              </Center>
            </Stack>
          </Paper>
        )}
      </Stack>
    </Container>
  )
}
