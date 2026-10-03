'use client'

import Link from 'next/link'
import { Alert, Button, Card, Group, Stack, Text } from '@mantine/core'
import { IconAlertTriangle, IconCheck, IconUserPlus } from '@tabler/icons-react'
import { ROUTES } from '@/config/routes'

interface Props {
  mensaje:  string
  critico:  boolean
  onOtro:   () => void
}

/**
 * El final de una atención. Lo que sigue casi siempre es el siguiente
 * paciente, así que esa es la acción principal; antes era un botón discreto,
 * y no había forma de ir a ver al paciente en la cola.
 */
export function CierreAtencion({ mensaje, critico, onOtro }: Props) {
  return (
    <Card withBorder radius="lg" p="lg">
      <Stack gap="md">
        <Alert
          icon={critico ? <IconAlertTriangle size={16} /> : <IconCheck size={16} />}
          color={critico ? 'red' : 'emerald'}
          variant="light"
        >
          <Text size="sm" fw={600}>{mensaje}</Text>
        </Alert>
        <Group>
          {/* Con el foco puesto: Enter y se empieza con el siguiente. */}
          <Button leftSection={<IconUserPlus size={16} />} onClick={onOtro} autoFocus>
            Atender otro paciente
          </Button>
          <Button variant="subtle" component={Link} href={ROUTES.SALUD.ENFERMERIA_COLA}>
            Ver en la cola
          </Button>
        </Group>
      </Stack>
    </Card>
  )
}
