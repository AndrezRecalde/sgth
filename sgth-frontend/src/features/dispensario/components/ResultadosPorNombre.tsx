'use client'

import { Card, Group, Stack, Text, UnstyledButton } from '@mantine/core'
import { IconChevronRight, IconUser, IconUsers } from '@tabler/icons-react'
import { StatusBadge } from '@/components/ui'
import type { PacienteEncontrado } from '../services/pacienteService'
import classes from './ResultadosPorNombre.module.css'

interface Props {
  pacientes: PacienteEncontrado[]
  onElegir:  (paciente: PacienteEncontrado) => void
}

/**
 * Los pacientes que coinciden con el nombre escrito, para elegir uno. Cada
 * fila es un botón: se llega con Tab y se elige con Enter.
 */
export function ResultadosPorNombre({ pacientes, onElegir }: Props) {
  return (
    <Card withBorder radius="lg" p={0}>
      <Text size="xs" c="dimmed" px="md" pt="sm" pb={4}>
        {pacientes.length === 1 ? '1 coincidencia' : `${pacientes.length} coincidencias`} — elija al paciente
      </Text>
      <Stack gap={0}>
        {pacientes.map((p) => {
          const esServidor = p.tipo === 'servidor'
          return (
            <UnstyledButton
              key={`${p.tipo}-${p.id}`}
              className={`${classes.fila} mantine-focus-auto`}
              onClick={() => onElegir(p)}
            >
              <Group justify="space-between" wrap="nowrap" gap="sm">
                <Group gap="sm" wrap="nowrap" miw={0}>
                  {esServidor ? <IconUser size={16} title="Servidor" /> : <IconUsers size={16} title="Familiar" />}
                  <Stack gap={0} miw={0}>
                    <Text size="sm" fw={500} truncate>{p.nombre_completo}</Text>
                    <Text size="xs" c="dimmed" truncate>
                      CI {p.cedula}
                      {esServidor
                        ? p.unidad_administrativa && ` · ${p.unidad_administrativa}`
                        : p.servidor_titular && ` · familiar de ${p.servidor_titular}`}
                    </Text>
                  </Stack>
                </Group>
                <Group gap="xs" wrap="nowrap">
                  {!esServidor && p.tipo_familiar && <StatusBadge size="xs">{p.tipo_familiar}</StatusBadge>}
                  <IconChevronRight size={16} />
                </Group>
              </Group>
            </UnstyledButton>
          )
        })}
      </Stack>
    </Card>
  )
}
