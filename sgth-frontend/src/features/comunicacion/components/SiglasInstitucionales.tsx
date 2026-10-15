'use client'

import { useIdentidadPublica } from '../hooks/useIdentidadPublica'

/**
 * Las siglas de la institución, de Identidad institucional, para textos de
 * pantallas que no son de cliente (el pie del login). Mientras llegan, las de
 * siempre, para que el texto no salte.
 */
export function SiglasInstitucionales() {
  const { data } = useIdentidadPublica()

  return <>{data?.datos.siglas || 'GADPE'}</>
}
