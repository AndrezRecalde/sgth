'use client'

import { Group, Text } from '@mantine/core'
import { IconPinned } from '@tabler/icons-react'
import { StatusBadge } from '@/components/ui'
import type { ComunicadoPortal } from '../services/tipos'
import classes from './EtiquetasComunicado.module.css'

/**
 * Lo que se dice de una publicación antes de abrirla: el tipo (una categoría,
 * sin tono), si espera su confirmación y si está fijada. Lo usan la tarjeta del
 * inicio, el listado del Portal y la campana.
 */
export function EtiquetasComunicado({ c }: { c: Pick<ComunicadoPortal, 'tipo_etiqueta' | 'por_confirmar' | 'fijado'> }) {
  return (
    <Group gap={6} wrap="wrap">
      <StatusBadge>{c.tipo_etiqueta}</StatusBadge>
      {c.por_confirmar && <StatusBadge tone="warning">Por confirmar</StatusBadge>}
      {c.fijado && (
        <Group gap={3} wrap="nowrap" className={classes.fijada}>
          <IconPinned size={13} />
          <Text size="xs" fw={600}>Fijada</Text>
        </Group>
      )}
    </Group>
  )
}
