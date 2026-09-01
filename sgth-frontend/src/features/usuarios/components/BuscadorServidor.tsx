'use client'

import { useState } from 'react'
import {
  Stack, Group, Text, TextInput, Paper, Avatar,
  Alert, Button, ActionIcon, Loader,
} from '@mantine/core'
import { useDebouncedValue } from '@mantine/hooks'
import { IconSearch, IconX } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useServidoresSinUsuario } from '../hooks/useServidoresSinUsuario'

export type ServidorItem = {
  id: number
  cedula: string
  nombre_completo: string
}

interface Props {
  /** Texto del botón de cada resultado. */
  etiquetaAccion?: string
  onSeleccionar: (servidor: ServidorItem) => void
  /** Id del servidor cuya acción está en curso, para no girar toda la lista. */
  idEnProceso?: number | null
}

const iniciales = (nombre: string) =>
  nombre.split(' ').slice(0, 2).map(w => w[0]).join('').toUpperCase()

/**
 * Buscador de servidores sin usuario, compartido por el alta de usuario y la
 * vinculación posterior. Ambos tenían la misma lista copiada, y las dos exigían
 * pulsar "Buscar": ahora consulta sola tras 300 ms de pausa.
 */
export function BuscadorServidor({
  etiquetaAccion = 'Seleccionar',
  onSeleccionar,
  idEnProceso = null,
}: Props) {
  const contained = useContainedInput()
  const [busqueda, setBusqueda] = useState('')
  const [debounced] = useDebouncedValue(busqueda.trim(), 300)

  const consulta = debounced.length >= 2 ? debounced : ''
  const { data: resultados = [], isFetching } = useServidoresSinUsuario(consulta)

  const sinResultados =
    consulta !== '' && !isFetching && resultados.length === 0

  return (
    <Stack gap="sm">
      <TextInput
        label="Cédula o nombre del servidor"
        placeholder="Ej: 0800123456 o Juan Pérez"
        description="Solo aparecen servidores que aún no tienen usuario"
        {...contained}
        value={busqueda}
        onChange={(e) => setBusqueda(e.currentTarget.value)}
        leftSection={<IconSearch size={14} />}
        rightSection={
          isFetching ? (
            <Loader size="xs" />
          ) : busqueda ? (
            <ActionIcon
              size="sm"
              variant="subtle"
              color="gray"
              aria-label="Limpiar búsqueda"
              onClick={() => setBusqueda('')}
            >
              <IconX size={12} />
            </ActionIcon>
          ) : null
        }
      />

      {sinResultados && (
        <Alert color="gray" variant="light">
          <Text size="xs">
            No se encontraron servidores sin usuario para “{consulta}”.
          </Text>
        </Alert>
      )}

      {resultados.length > 0 && (
        <Stack gap="xs">
          <Text size="xs" c="dimmed">
            {resultados.length} resultado(s) — selecciona el servidor:
          </Text>
          {(resultados as ServidorItem[]).map((s) => (
            <Paper key={s.id} withBorder radius="md" p="sm">
              <Group justify="space-between" wrap="nowrap">
                <Group gap="sm" wrap="nowrap">
                  <Avatar color="emerald" size="md" radius="xl">
                    {iniciales(s.nombre_completo)}
                  </Avatar>
                  <Stack gap={0}>
                    <Text size="sm" fw={600}>{s.nombre_completo}</Text>
                    <Text size="xs" c="dimmed">CI: {s.cedula}</Text>
                  </Stack>
                </Group>
                <Button
                  size="xs"
                  color="emerald"
                  variant="light"
                  loading={idEnProceso === s.id}
                  disabled={idEnProceso !== null && idEnProceso !== s.id}
                  onClick={() => onSeleccionar(s)}
                >
                  {etiquetaAccion}
                </Button>
              </Group>
            </Paper>
          ))}
        </Stack>
      )}
    </Stack>
  )
}
