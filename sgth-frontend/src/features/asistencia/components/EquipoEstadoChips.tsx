'use client'

import { Chip, Group } from '@mantine/core'
import { SEMANTIC_COLOR, type SemanticTone } from '@/config/design.tokens'

interface Props {
  /** Los estados del filtro, con `todos` primero. */
  estados:   readonly string[]
  etiquetas: Record<string, string>
  tonos:     Record<string, SemanticTone>
  valor:     string
  onCambiar: (estado: string) => void
}

/**
 * El filtro por estado de «Mi equipo». Es el mismo de las pantallas de
 * Talento Humano: cada chip con el tono de la etiqueta de su fila, para que
 * filtro y resultado coincidan.
 */
export function EquipoEstadoChips({ estados, etiquetas, tonos, valor, onCambiar }: Props) {
  return (
    <Group gap="xs">
      {estados.map((estado) => (
        <Chip
          key={estado}
          color={SEMANTIC_COLOR[tonos[estado] ?? 'neutral']}
          checked={valor === estado}
          onChange={() => onCambiar(estado)}
        >
          {estado === 'todos' ? 'Todos' : etiquetas[estado]}
        </Chip>
      ))}
    </Group>
  )
}
