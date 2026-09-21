'use client'

import { Avatar, Group, Stack, Text } from '@mantine/core'
import { StatusBadge } from '@/components/ui'
import { REGIMEN_LABELS } from '@/lib/regimen'
import { etiquetaNombramiento } from '../utils/tipoNombramientoOptions'
import type { ServidorConRelaciones } from '@/types/api'
import classes from './ServidorEncabezado.module.css'

interface Props {
  servidor: ServidorConRelaciones
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
 * Dónde trabaja la persona y bajo qué figura.
 *
 * El nombre, la cédula y la situación viven en el título de la página, así
 * que aquí no se repiten: esto responde lo siguiente que pregunta Talento
 * Humano —qué puesto ocupa, en qué unidad y con qué vínculo—.
 */
export function ServidorEncabezado({ servidor }: Props) {
  const anios = servidor.anios_servicio
  const cargo = servidor.contrato_vigente?.puesto?.cargo?.nombre
    ?? servidor.puesto?.cargo?.nombre
  const unidad = servidor.contrato_vigente?.unidad_administrativa?.nombre
    ?? servidor.unidad_administrativa?.nombre

  return (
    <Group p="md" className={classes.encabezado} wrap="nowrap">
      <Avatar size={52} radius="xl" fw={700}>{iniciales(servidor)}</Avatar>

      <Stack gap={4} style={{ flex: 1, minWidth: 0 }}>
        <Text fw={600}>
          {cargo ?? 'Sin puesto asignado'}
        </Text>
        <Text size="sm" c="dimmed">
          {unidad ?? 'Sin unidad administrativa'}
        </Text>

        <Group gap="xs" mt={4}>
          {servidor.regimen_laboral && (
            <StatusBadge size="xs">
              {REGIMEN_LABELS[servidor.regimen_laboral] ?? servidor.regimen_laboral}
            </StatusBadge>
          )}
          {etiquetaNombramiento(servidor.contrato_vigente?.tipo_nombramiento) && (
            <StatusBadge size="xs">
              {etiquetaNombramiento(servidor.contrato_vigente?.tipo_nombramiento)}
            </StatusBadge>
          )}
          {anios != null && (
            <StatusBadge size="xs" variant="dot">
              {anios} {anios === 1 ? 'año de servicio' : 'años de servicio'}
            </StatusBadge>
          )}
        </Group>
      </Stack>
    </Group>
  )
}
