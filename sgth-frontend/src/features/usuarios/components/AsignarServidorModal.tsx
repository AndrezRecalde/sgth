'use client'

import { Modal, Stack, Text, Group, ThemeIcon, Button, Alert } from '@mantine/core'
import { IconUserCheck, IconInfoCircle } from '@tabler/icons-react'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { useUsuarioMutations } from '../hooks/useUsuarioMutations'
import { BuscadorServidor, type ServidorItem } from './BuscadorServidor'
import type { Usuario } from '@/types/api'

interface Props {
  opened: boolean
  onClose: () => void
  usuario: Usuario | null
}

export function AsignarServidorModal({ opened, onClose, usuario }: Props) {
  const { isMobile } = useMobileBreakpoint()
  const { asignarServidor } = useUsuarioMutations()

  const handleSeleccionar = (s: ServidorItem) => {
    if (!usuario?.id) return
    asignarServidor.mutate(
      { id: Number(usuario.id), servidorId: s.id },
      { onSuccess: onClose },
    )
  }

  const nombreUsuario = usuario?.nombre_completo || usuario?.email || '—'

  return (
    <Modal
      opened={opened}
      onClose={onClose}
      title={
        <Group gap="xs">
          <ThemeIcon color="emerald" variant="light" size="sm">
            <IconUserCheck size={14} />
          </ThemeIcon>
          <Stack gap={0}>
            <Text fw={700} size="sm">Asignar servidor</Text>
            <Text size="xs" c="dimmed">{nombreUsuario}</Text>
          </Stack>
        </Group>
      }
      size="md"
      fullScreen={isMobile}
      radius={isMobile ? 0 : 'xl'}
    >
      <Stack gap="md">
        <Alert color="blue" variant="light" icon={<IconInfoCircle size={16} />}>
          <Text size="xs">
            Al vincular la ficha, el usuario vuelve a quedar activo y recupera el
            acceso a su expediente.
          </Text>
        </Alert>

        <BuscadorServidor
          etiquetaAccion="Asignar"
          onSeleccionar={handleSeleccionar}
          idEnProceso={
            asignarServidor.isPending
              ? asignarServidor.variables?.servidorId ?? null
              : null
          }
        />

        <Group justify="flex-end">
          <Button variant="default" onClick={onClose}>Cerrar</Button>
        </Group>
      </Stack>
    </Modal>
  )
}
