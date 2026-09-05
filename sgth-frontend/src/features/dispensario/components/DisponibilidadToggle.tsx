'use client'

import { Button, Skeleton, Text, Tooltip, Box } from '@mantine/core'
import {
  useMiDisponibilidad,
  useAlternarDisponibilidad,
} from '../hooks/useDisponibilidad'
import { useAuth } from '@/hooks/useAuth'

const ROLES_CLINICOS = ['medico', 'odontologo']

/**
 * El estado de disponibilidad de quien atiende, en la barra superior.
 *
 * Vivía en la página de inicio del dispensario, y eso obligaba a volver allí
 * para cambiarlo. Pero marcarse disponible no es una tarea: es un estado que se
 * cambia mientras se está haciendo otra cosa —al salir a almorzar, con la
 * pantalla de consultas delante—. Ese rodeo es justo lo que hace que nadie lo
 * mantenga al día, y Recepción asigna turnos mirando esta lista: una que nadie
 * actualiza es peor que no tenerla.
 *
 * Solo lo ve quien atiende pacientes. El resto del personal del dispensario no
 * aparece en esa lista, así que el control no le dice nada.
 */
export function DisponibilidadToggle() {
  const { usuario } = useAuth()
  const roles = (usuario?.roles as string[]) ?? []
  const esClinico = roles.some(r => ROLES_CLINICOS.includes(r))

  const { data, isLoading } = useMiDisponibilidad(esClinico)
  const alternar = useAlternarDisponibilidad()

  if (!esClinico) return null

  if (isLoading) {
    return <Skeleton height={30} width={120} radius="xl" />
  }

  const disponible = data?.disponible ?? false

  return (
    <Tooltip
      label={disponible
        ? 'Apareces disponible para atención. Pulsa para dejar de estarlo.'
        : 'No apareces disponible. Pulsa para marcarte disponible.'}
      withArrow
    >
      <Button
        size="compact-sm"
        radius="xl"
        variant="light"
        color={disponible ? 'emerald' : 'gray'}
        loading={alternar.isPending}
        onClick={() => alternar.mutate()}
        aria-label={disponible
          ? 'Disponible para atención'
          : 'No disponible para atención'}
        leftSection={
          <Box
            w={8}
            h={8}
            style={{
              borderRadius: '50%',
              backgroundColor: disponible
                ? 'var(--mantine-color-emerald-6)'
                : 'var(--mantine-color-gray-5)',
            }}
          />
        }
      >
        {/* En pantallas estrechas queda solo el punto de color: la barra
            superior no tiene sitio para más, y el color ya dice el estado. */}
        <Text size="xs" fw={500} visibleFrom="sm">
          {disponible ? 'Disponible' : 'No disponible'}
        </Text>
      </Button>
    </Tooltip>
  )
}
