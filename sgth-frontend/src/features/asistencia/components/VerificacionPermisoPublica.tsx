'use client'

/*
| Página a la que apunta el QR del permiso impreso.
|
| Antes el QR llevaba a un endpoint de la API —y encima a una ruta equivocada,
| así que daba 404—. Quien escanea es una persona con un papel en la mano, no
| un cliente HTTP: aquí ve, de un vistazo y sin sesión, si el documento es
| auténtico y si sigue vigente.
*/

import { useQuery } from '@tanstack/react-query'
import {
  Alert, Badge, Card, Container, Divider, Group, Skeleton, Stack, Text, Title,
} from '@mantine/core'
import { IconAlertCircle, IconCircleCheck, IconCircleX } from '@tabler/icons-react'
import { StatusBadge } from '@/components/ui'
import { SEMANTIC_COLOR } from '@/config/design.tokens'
import { asistenciaService } from '../services/asistenciaService'
import { formatFecha } from '@/lib/fecha'
import { getApiErrorMessage } from '@/types/api'
import type { VerificacionPermiso } from '@/types/api'

interface Props {
  folio: string
}

function Dato({ etiqueta, valor }: { etiqueta: string; valor?: string | null }) {
  return (
    <Group justify="space-between" wrap="nowrap" align="flex-start" gap="md">
      <Text size="sm" c="dimmed" style={{ whiteSpace: 'nowrap' }}>
        {etiqueta}
      </Text>
      <Text size="sm" fw={500} ta="right">
        {valor?.trim() ? valor : '—'}
      </Text>
    </Group>
  )
}

/**
 * El veredicto va en un `Alert`, no en una tarjeta con fondo propio: el color
 * de superficie lo resuelve Mantine para los dos esquemas. Pintarlo a mano
 * habría dado un verde claro ilegible en modo oscuro, que es justo lo que
 * prohíbe la regla 09.
 */
function Veredicto({ permiso }: { permiso: VerificacionPermiso }) {
  const vigente = permiso.vigente

  return (
    <Alert
      variant="light"
      radius="lg"
      color={vigente ? SEMANTIC_COLOR.success : SEMANTIC_COLOR.danger}
      icon={vigente ? <IconCircleCheck size={28} /> : <IconCircleX size={28} />}
      title={vigente ? 'Documento válido' : 'Documento sin validez'}
    >
      <Text size="sm">
        {vigente
          ? 'El permiso existe en el sistema y ampara la ausencia.'
          : `Este permiso ya no ampara una ausencia: ${permiso.estado_label.toLowerCase()}.`}
      </Text>
    </Alert>
  )
}

export function VerificacionPermisoPublica({ folio }: Props) {
  const { data: permiso, isLoading, isError, error } = useQuery({
    queryKey: ['verificar-permiso', folio],
    queryFn: () => asistenciaService.permisos.verificar(folio),
    retry: false,
  })

  if (isLoading) {
    return (
      <Container size="xs" py="xl">
        <Stack gap="md">
          <Skeleton height={32} width="70%" />
          <Skeleton height={100} radius="lg" />
          <Skeleton height={240} radius="lg" />
        </Stack>
      </Container>
    )
  }

  if (isError || !permiso) {
    return (
      <Container size="xs" py="xl">
        <Stack gap="md">
          <Title order={3}>Verificación de permiso</Title>
          <Alert
            icon={<IconAlertCircle size={18} />}
            color={SEMANTIC_COLOR.danger}
            variant="light"
            title="Folio no encontrado"
          >
            {getApiErrorMessage(
              error,
              'El folio escaneado no existe o es inválido. Verifique el código impreso en el documento.'
            )}
            <Text size="sm" mt="xs" ff="monospace">{folio}</Text>
          </Alert>
        </Stack>
      </Container>
    )
  }

  return (
    <Container size="xs" py="xl">
      <Stack gap="md">
        <Stack gap={4}>
          <Text size="xs" c="dimmed" tt="uppercase" fw={600}>
            GAD Provincial de Esmeraldas
          </Text>
          <Title order={3}>Verificación de permiso</Title>
          <Text ff="monospace" fw={600} size="lg">{permiso.folio}</Text>
        </Stack>

        <Veredicto permiso={permiso} />

        <Card withBorder radius="lg" padding="lg">
          <Stack gap="sm">
            <Dato etiqueta="Servidor" valor={permiso.servidor} />
            <Dato etiqueta="Cédula" valor={permiso.cedula_parcial} />
            <Dato etiqueta="Unidad" valor={permiso.unidad} />

            <Divider my={4} />

            <Group justify="space-between" wrap="nowrap">
              <Text size="sm" c="dimmed">Tipo</Text>
              <Badge size="sm" variant="light" color="blue">
                {permiso.tipo_label}
              </Badge>
            </Group>
            <Dato etiqueta="Fecha" valor={formatFecha(permiso.fecha)} />
            <Dato
              etiqueta="Horario"
              valor={`${permiso.hora_inicio} — ${permiso.hora_fin}`}
            />

            <Divider my={4} />

            <Group justify="space-between" wrap="nowrap">
              <Text size="sm" c="dimmed">Estado</Text>
              <StatusBadge tone={permiso.vigente ? 'success' : 'danger'}>
                {permiso.estado_label}
              </StatusBadge>
            </Group>
          </Stack>
        </Card>

        <Text size="xs" c="dimmed" ta="center">
          El motivo del permiso es información reservada y no se muestra aquí.
          <br />
          Consultado el{' '}
          {new Date(permiso.verificado_en).toLocaleString('es-EC', {
            timeZone: 'America/Guayaquil',
          })}
        </Text>
      </Stack>
    </Container>
  )
}
