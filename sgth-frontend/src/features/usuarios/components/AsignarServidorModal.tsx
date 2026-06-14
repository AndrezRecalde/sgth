'use client'

import { useState }           from 'react'
import {
  Modal, Stack, Text, TextInput,
  Button, Group, Paper, Avatar,
  Badge, Alert, ThemeIcon,
} from '@mantine/core'
import { IconSearch, IconX, IconUserCheck } from '@tabler/icons-react'
import { useMutation, useQueryClient }    from '@tanstack/react-query'
import { notifications }                  from '@mantine/notifications'
import { useContainedInput }              from '@/hooks/useContainedInput'
import { usuarioService }                 from '../services/usuarioService'
import { getApiErrorMessage }             from '@/types/api'
import type { Usuario }                   from '@/types/api'

interface Props {
  opened:   boolean
  onClose:  () => void
  usuario:  Usuario | null
}

type ServidorItem = {
  id:              number
  cedula:          string
  nombre_completo: string
}

export function AsignarServidorModal({
  opened, onClose, usuario,
}: Props) {
  const contained = useContainedInput()
  const qc        = useQueryClient()

  const [busqueda,   setBusqueda]   = useState('')
  const [resultados, setResultados] = useState<ServidorItem[]>([])
  const [buscando,   setBuscando]   = useState(false)
  const [queryBusq,  setQueryBusq]  = useState('')

  const asignar = useMutation({
    mutationFn: (servidorId: number) =>
      usuarioService.asignarServidor(
        Number(usuario?.id), servidorId
      ),
    onSuccess: () => {
      notifications.show({
        title:   'Servidor asignado',
        message: 'El servidor fue vinculado al usuario.',
        color:   'emerald',
      })
      qc.invalidateQueries({ queryKey: ['usuarios'] })
      handleClose()
    },
    onError: (error: unknown) =>
      notifications.show({
        title:   'Error',
        message: getApiErrorMessage(error),
        color:   'red',
      }),
  })

  const handleBuscar = async () => {
    if (!busqueda.trim()) return
    setBuscando(true)
    try {
      const res = await usuarioService.servidoresSinUsuario(
        busqueda.trim()
      )
      setResultados(res ?? [])
      setQueryBusq(busqueda.trim())
    } catch {
      setResultados([])
    } finally {
      setBuscando(false)
    }
  }

  const handleClose = () => {
    setBusqueda('')
    setResultados([])
    setQueryBusq('')
    onClose()
  }

  const nombreUsuario = usuario?.nombre_completo
    || usuario?.email
    || '—'

  return (
    <Modal
      opened={opened}
      onClose={handleClose}
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
      radius="xl"
    >
      <Stack gap="md">
        <Text size="sm" c="dimmed">
          Busca el servidor que deseas vincular
          a este usuario.
        </Text>

        <Group gap="xs" align="flex-end">
          <TextInput
            label="Cédula o nombre del servidor"
            placeholder="Ej: 0800123456 o Juan Pérez"
            leftSection={<IconSearch size={14} />}
            {...contained}
            value={busqueda}
            onChange={(e) => setBusqueda(e.currentTarget.value)}
            onKeyDown={(e) => {
              if (e.key === 'Enter') {
                e.preventDefault()
                handleBuscar()
              }
            }}
            style={{ flex: 1 }}
            rightSection={
              busqueda ? (
                <Button
                  size="compact-xs"
                  variant="subtle"
                  color="gray"
                  onClick={() => {
                    setBusqueda('')
                    setResultados([])
                    setQueryBusq('')
                  }}
                >
                  <IconX size={12} />
                </Button>
              ) : null
            }
          />
          <Button
            color="blue"
            variant="light"
            loading={buscando}
            onClick={handleBuscar}
            leftSection={<IconSearch size={14} />}
          >
            Buscar
          </Button>
        </Group>

        {queryBusq && resultados.length === 0 && !buscando && (
          <Alert color="gray" variant="light">
            <Text size="xs">
              No se encontraron servidores sin usuario
              para "{queryBusq}".
            </Text>
          </Alert>
        )}

        {resultados.length > 0 && (
          <Stack gap="xs">
            <Text size="xs" c="dimmed">
              {resultados.length} resultado(s) — selecciona
              el servidor a vincular:
            </Text>
            {resultados.map((s) => (
              <Paper
                key={s.id}
                withBorder
                radius="md"
                p="sm"
              >
                <Group justify="space-between">
                  <Group gap="sm">
                    <Avatar
                      color="blue" size="md" radius="xl"
                    >
                      {s.nombre_completo
                        .split(' ').slice(0, 2)
                        .map(w => w[0]).join('')
                        .toUpperCase()}
                    </Avatar>
                    <Stack gap={0}>
                      <Text size="sm" fw={600}>
                        {s.nombre_completo}
                      </Text>
                      <Text size="xs" c="dimmed">
                        CI: {s.cedula}
                      </Text>
                    </Stack>
                  </Group>
                  <Button
                    size="xs"
                    color="emerald"
                    variant="light"
                    loading={asignar.isPending}
                    onClick={() => asignar.mutate(s.id)}
                  >
                    Asignar
                  </Button>
                </Group>
              </Paper>
            ))}
          </Stack>
        )}

        <Group justify="flex-end">
          <Button variant="default" onClick={handleClose}>
            Cancelar
          </Button>
        </Group>
      </Stack>
    </Modal>
  )
}
