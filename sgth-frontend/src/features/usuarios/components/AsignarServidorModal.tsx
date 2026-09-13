'use client'

import { Stack, Text, Alert } from '@mantine/core'
import { ModalFooter, SgthModal } from '@/components/ui'
import { IconInfoCircle } from '@tabler/icons-react'
import { useUsuarioMutations } from '../hooks/useUsuarioMutations'
import { BuscadorServidor, type ServidorItem } from './BuscadorServidor'
import type { Usuario } from '@/types/api'

interface Props {
  opened: boolean
  onClose: () => void
  usuario: Usuario | null
}

export function AsignarServidorModal({ opened, onClose, usuario }: Props) {
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
    <SgthModal
      opened={opened}
      onClose={onClose}
      title={
        <Text fw={600}>
          Asignar servidor
          <Text span c="dimmed" ml={4}>— {nombreUsuario}</Text>
        </Text>
      }
      size="md"
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

        <ModalFooter onCancel={onClose} cancelLabel="Cerrar" sinPrincipal />
      </Stack>
    </SgthModal>
  )
}
