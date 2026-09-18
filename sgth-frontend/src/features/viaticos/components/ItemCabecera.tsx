'use client'

import { ActionIcon, Group, Text, Tooltip } from '@mantine/core'
import { IconTrash } from '@tabler/icons-react'

interface Props {
  titulo: string
  /** Sin esto no se puede quitar: el formulario pide al menos uno. */
  onEliminar?: () => void
  etiquetaEliminar: string
}

/**
 * La cabecera de cada comprobante o actividad de una lista que se captura.
 * Antes el comprobante decía «Comprobante #1» en gris pequeño y la actividad
 * «1 Actividad 1», con el número dos veces.
 */
export function ItemCabecera({ titulo, onEliminar, etiquetaEliminar }: Props) {
  return (
    <Group justify="space-between" mb="sm">
      <Text size="sm" fw={600}>{titulo}</Text>
      {onEliminar && (
        <Tooltip label={etiquetaEliminar}>
          <ActionIcon color="red" variant="subtle" aria-label={etiquetaEliminar} onClick={onEliminar}>
            <IconTrash size={16} />
          </ActionIcon>
        </Tooltip>
      )}
    </Group>
  )
}
