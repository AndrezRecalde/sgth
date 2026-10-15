'use client'

import Image from 'next/image'
import { Box, useComputedColorScheme } from '@mantine/core'
import { useIdentidadPublica, urlIdentidad } from '../hooks/useIdentidadPublica'

interface Props {
  /** Alto en píxeles: 72 en el login, menos en la credencial digital. */
  alto?: number
}

/**
 * El logo del inicio de sesión (fase 2.3), y de la credencial digital y su
 * verificación (3.2): el imagotipo horizontal, o el
 * negativo en modo oscuro (que cae en el horizontal si no se cargó). Ocupa su
 * alto desde el primer momento, para que el formulario no salte al llegar.
 * Si el backend no responde, el isotipo de siempre.
 */
export function LogoInstitucional({ alto = 72 }: Props) {
  const esquema = useComputedColorScheme('light')
  const { data, isError } = useIdentidadPublica()

  const recurso = data?.variantes[esquema === 'dark' ? 'imagotipo_negativo' : 'imagotipo_horizontal']
  const src = recurso ? urlIdentidad(recurso.ruta) : isError ? '/logo.png' : null
  const ancho = recurso ? Math.round((alto * recurso.ancho) / recurso.alto) : alto

  return (
    <Box h={alto}>
      {src && (
        <Image
          src={src}
          alt={data?.datos.nombre_oficial ?? 'Logo institucional'}
          width={ancho}
          height={alto}
          style={{ maxWidth: '100%', height: 'auto', maxHeight: alto, objectFit: 'contain' }}
          unoptimized
          priority
        />
      )}
    </Box>
  )
}
