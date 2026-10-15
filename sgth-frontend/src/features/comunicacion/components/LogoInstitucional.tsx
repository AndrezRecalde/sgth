'use client'

import Image from 'next/image'
import { Box, useComputedColorScheme } from '@mantine/core'
import { useIdentidadPublica, urlIdentidad } from '../hooks/useIdentidadPublica'

const ALTO = 72

/**
 * El logo del inicio de sesión (fase 2.3): el imagotipo horizontal, o el
 * negativo en modo oscuro (que cae en el horizontal si no se cargó). Ocupa su
 * alto desde el primer momento, para que el formulario no salte al llegar.
 * Si el backend no responde, el isotipo de siempre.
 */
export function LogoInstitucional() {
  const esquema = useComputedColorScheme('light')
  const { data, isError } = useIdentidadPublica()

  const recurso = data?.variantes[esquema === 'dark' ? 'imagotipo_negativo' : 'imagotipo_horizontal']
  const src = recurso ? urlIdentidad(recurso.ruta) : isError ? '/logo.png' : null
  const ancho = recurso ? Math.round((ALTO * recurso.ancho) / recurso.alto) : ALTO

  return (
    <Box h={ALTO}>
      {src && (
        <Image
          src={src}
          alt={data?.datos.nombre_oficial ?? 'Logo institucional'}
          width={ancho}
          height={ALTO}
          style={{ maxWidth: '100%', height: 'auto', maxHeight: ALTO, objectFit: 'contain' }}
          unoptimized
          priority
        />
      )}
    </Box>
  )
}
