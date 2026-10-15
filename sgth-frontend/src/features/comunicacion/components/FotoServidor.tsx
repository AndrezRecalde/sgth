'use client'

import { Avatar, type AvatarProps } from '@mantine/core'
import { useImagenFotografia } from '../hooks/useImagenFotografia'
import { inicialesDe } from '../utils/iniciales'

interface Props extends Omit<AvatarProps, 'src' | 'children' | 'alt'> {
  /** La foto vigente del servidor; sin ella, las iniciales. */
  fotografiaId?: number | null
  /** Para el texto alternativo («Foto de Ana Pérez») y las iniciales. */
  nombre: string
  /** Si no se dan, salen del nombre: primer nombre y primer apellido. */
  iniciales?: string
}

/**
 * El avatar de un servidor con su foto (Comunicación Social, fase 1.2).
 *
 * Es el mismo `Avatar` que antes mostraba solo las iniciales: si no tiene
 * foto, todavía no llegó, o quien mira no puede verla, se ven las iniciales,
 * con el mismo tamaño y color. Lo usan el menú de usuario, la cabecera del
 * expediente y la pantalla Fotografías.
 */
export function FotoServidor({ fotografiaId, nombre, iniciales, ...avatar }: Props) {
  const { data: src } = useImagenFotografia(fotografiaId, 'miniatura')

  return (
    <Avatar src={src ?? null} alt={`Foto de ${nombre}`} {...avatar}>
      {iniciales ?? inicialesDe(nombre)}
    </Avatar>
  )
}
