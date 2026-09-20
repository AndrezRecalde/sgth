'use client'

import { Avatar, Group, Stack, Text } from '@mantine/core'
import { StatusBadge } from '@/components/ui'
import { REGIMEN_LABELS } from '@/lib/regimen'
import type { ServidorConRelaciones } from '@/types/api'
import classes from './ServidorEncabezado.module.css'

interface Props {
  servidor: ServidorConRelaciones
  /** Régimen, antigüedad y estado del vínculo. Solo en el expediente. */
  conSituacion?: boolean
  /** Acciones a la derecha: el botón de editar del expediente. */
  actions?: React.ReactNode
}

export function nombreCompletoDe(servidor: ServidorConRelaciones): string {
  return [
    servidor.apellido,
    servidor.segundo_apellido,
    servidor.nombre,
    servidor.segundo_nombre,
  ].filter(Boolean).join(' ')
}

const iniciales = (servidor: ServidorConRelaciones): string =>
  [servidor.nombre?.charAt(0), servidor.apellido?.charAt(0)]
    .filter(Boolean).join('').toUpperCase() || '?'

/**
 * Quién es la persona que se está mirando. Encabeza los dos paneles del
 * expediente —la ficha y sus acciones de personal—, que lo dibujaban cada uno
 * por su cuenta.
 */
export function ServidorEncabezado({ servidor, conSituacion = false, actions }: Props) {
  const anios = servidor.anios_servicio
  const cargo = servidor.contrato_vigente?.puesto?.cargo?.nombre
    ?? servidor.puesto?.cargo?.nombre
  const unidad = servidor.contrato_vigente?.unidad_administrativa?.nombre
    ?? servidor.unidad_administrativa?.nombre

  return (
    <Group p="md" className={classes.encabezado}>
      <Avatar size={52} radius="xl" fw={700}>{iniciales(servidor)}</Avatar>

      <Stack gap={2} style={{ flex: 1, minWidth: 0 }}>
        <Text fw={700} size="md">{nombreCompletoDe(servidor)}</Text>
        <Text size="sm" c="dimmed" ff="monospace">CI: {servidor.cedula ?? '-'}</Text>
        {/* Cargo y unidad: lo primero que Talento Humano necesita ubicar, y
            no estaba en ninguno de los dos paneles. */}
        <Text size="sm" c="dimmed">
          {[cargo, unidad].filter(Boolean).join(' · ') || 'Sin vínculo laboral registrado'}
        </Text>

        {conSituacion && (
          <Group gap="xs" mt={4}>
            {servidor.regimen_laboral && (
              <StatusBadge size="xs">
                {REGIMEN_LABELS[servidor.regimen_laboral] ?? servidor.regimen_laboral}
              </StatusBadge>
            )}
            {anios != null && (
              <StatusBadge size="xs">
                {anios} {anios === 1 ? 'año de servicio' : 'años de servicio'}
              </StatusBadge>
            )}
            {servidor.contrato_vigente?.estado && (
              <StatusBadge
                tone={servidor.contrato_vigente.estado === 'vigente' ? 'success' : 'neutral'}
                variant="dot"
                size="xs"
              >
                {servidor.contrato_vigente.estado === 'vigente' ? 'Vínculo vigente' : 'Sin vínculo vigente'}
              </StatusBadge>
            )}
          </Group>
        )}
      </Stack>

      {actions}
    </Group>
  )
}
