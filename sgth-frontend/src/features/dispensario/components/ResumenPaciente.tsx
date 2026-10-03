'use client'

import type { ReactNode } from 'react'
import { Avatar, Card, Group, Stack, Text } from '@mantine/core'
import { IconUser, IconUsers } from '@tabler/icons-react'
import { StatusBadge } from '@/components/ui'

interface Props {
  nombre:     string
  esServidor: boolean
  /** Debajo del nombre: folio, cédula, edad. Sin él, «Servidor» o «Familiar». */
  detalle?:   ReactNode
  /** Categoría a la derecha (especialidad, tipo de evaluación). Sin tono. */
  etiqueta?:  string
  /** Una acción terciaria, como «Cambiar». */
  accion?:    ReactNode
  /** Una línea más bajo la cabecera, como el motivo del turno. */
  children?:  ReactNode
}

/**
 * Quién es el paciente, arriba de cada paso del flujo de Enfermería.
 *
 * Estaba escrita a mano cinco veces con tres estilos: con fondo de acento y
 * etiqueta en la elección de acción, el turno y el servicio; lisa y con folio
 * o cédula en los dos formularios de signos vitales. Ahora es una sola, y el
 * paciente se reconoce igual en todos los pasos.
 */
export function ResumenPaciente({
  nombre, esServidor, detalle, etiqueta, accion, children,
}: Props) {
  return (
    <Card withBorder radius="md" p="sm" bg="var(--sgth-accent-light)">
      <Stack gap="xs">
        <Group justify="space-between" wrap="nowrap">
          <Group gap="sm" wrap="nowrap">
            <Avatar radius="xl">
              {esServidor ? <IconUser size={16} /> : <IconUsers size={16} />}
            </Avatar>
            <Stack gap={0}>
              <Text size="sm" fw={600}>
                {nombre.trim() || '—'}
              </Text>
              {detalle ?? (
                <Text size="xs" c="dimmed">
                  {esServidor ? 'Servidor' : 'Familiar'}
                </Text>
              )}
            </Stack>
          </Group>
          {etiqueta && <StatusBadge>{etiqueta}</StatusBadge>}
          {accion}
        </Group>
        {children}
      </Stack>
    </Card>
  )
}
