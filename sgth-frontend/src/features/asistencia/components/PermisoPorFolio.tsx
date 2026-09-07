'use client'

/*
| La pantalla que abre el QR del permiso impreso.
|
| Talento Humano escanea el papel firmado y confirma o rechaza aquí mismo, sin
| buscar el folio a mano en el listado. Se ve casi siempre desde un celular, así
| que la información va apilada y los botones son grandes.
|
| Quien llegue sin sesión pasa antes por el acceso y vuelve a esta misma URL:
| de eso se encarga el `next` que pone `proxy.ts`.
*/

import { useState } from 'react'
import { Alert, Button, Skeleton, Stack, Text, Title } from '@mantine/core'
import { useQuery } from '@tanstack/react-query'
import { IconAlertCircle, IconArrowBackUp, IconCheck, IconX } from '@tabler/icons-react'
import { SEMANTIC_COLOR } from '@/config/design.tokens'
import { getApiErrorMessage } from '@/types/api'
import { asistenciaService } from '../services/asistenciaService'
import { usePermisoMutations } from '../hooks/usePermisoMutations'
import { MotivoPermisoModal } from './MotivoPermisoModal'
import { PermisoResumen } from './PermisoResumen'
import { ESTADOS_CONFIRMADOS } from './permisos.constants'

interface Props {
  folio: string
}

type AccionConMotivo = 'rechazar' | 'revertir'

export function PermisoPorFolio({ folio }: Props) {
  const [conMotivo, setConMotivo] = useState<AccionConMotivo | null>(null)

  const { data: permiso, isLoading, isError, error, refetch } = useQuery({
    queryKey: ['permiso-por-folio', folio],
    queryFn: () => asistenciaService.permisos.porFolio(folio),
    retry: false,
  })

  const { confirmar, rechazar, revertirConfirmacion } = usePermisoMutations()

  if (isLoading) {
    return (
      <Stack gap="md">
        <Skeleton height={30} width="60%" />
        <Skeleton height={280} radius="lg" />
      </Stack>
    )
  }

  if (isError || !permiso) {
    return (
      <Stack gap="md">
        <Title order={3}>Permiso</Title>
        <Alert
          icon={<IconAlertCircle size={18} />}
          color={SEMANTIC_COLOR.danger}
          variant="light"
          title="No se encontró el permiso"
        >
          {getApiErrorMessage(
            error,
            'El folio escaneado no existe, o no tiene permiso para verlo.'
          )}
          <Text size="sm" mt="xs" ff="monospace">{folio}</Text>
        </Alert>
      </Stack>
    )
  }

  const estado = permiso.estado as string
  const pendiente = estado === 'pendiente'
  const confirmado = ESTADOS_CONFIRMADOS.includes(estado)
  const esRechazo = conMotivo === 'rechazar'

  const enviarMotivo = (motivo: string) => {
    const mutacion = esRechazo ? rechazar : revertirConfirmacion

    mutacion.mutate(
      { id: permiso.id, motivo },
      { onSuccess: () => { setConMotivo(null); refetch() } }
    )
  }

  return (
    <Stack gap="md">
      <Stack gap={4}>
        <Text size="xs" c="dimmed" tt="uppercase" fw={600}>Permiso de ausencia</Text>
        <Text ff="monospace" fw={700} size="xl">{permiso.folio}</Text>
      </Stack>

      <PermisoResumen permiso={permiso} />

      {pendiente && (
        <Alert icon={<IconAlertCircle size={16} />} color="amber" variant="light" py={8}>
          <Text size="xs">
            Este permiso espera la confirmación del documento físico. Si no
            llega dentro del plazo, pasa a falta injustificada.
          </Text>
        </Alert>
      )}

      <Stack gap="sm">
        {pendiente && (
          <>
            <Button
              size="md"
              color="emerald"
              variant="light"
              leftSection={<IconCheck size={18} />}
              loading={confirmar.isPending}
              onClick={() => permiso.folio && confirmar.mutate(permiso.folio, {
                onSuccess: () => refetch(),
              })}
            >
              Confirmar recepción
            </Button>
            <Button
              size="md"
              color="orange"
              variant="light"
              leftSection={<IconX size={18} />}
              onClick={() => setConMotivo('rechazar')}
            >
              Rechazar documento
            </Button>
          </>
        )}

        {confirmado && (
          <Button
            size="md"
            color="orange"
            variant="light"
            leftSection={<IconArrowBackUp size={18} />}
            onClick={() => setConMotivo('revertir')}
          >
            Revertir confirmación
          </Button>
        )}
      </Stack>

      <MotivoPermisoModal
        opened={conMotivo !== null}
        onClose={() => setConMotivo(null)}
        title={esRechazo ? 'Rechazar documento' : 'Revertir confirmación'}
        confirmLabel={esRechazo ? 'Rechazar' : 'Revertir'}
        cargando={esRechazo ? rechazar.isPending : revertirConfirmacion.isPending}
        onConfirm={enviarMotivo}
        descripcion={
          esRechazo ? (
            <>El permiso <b>{permiso.folio}</b> quedará rechazado y no amparará la ausencia.</>
          ) : (
            <>
              El permiso <b>{permiso.folio}</b> volverá a pendiente y se devolverá
              al servidor el saldo de vacaciones descontado.
            </>
          )
        }
      />
    </Stack>
  )
}
