'use client'

import { Text } from '@mantine/core'
import { motivoDeEstado } from '../utils/motivoDeEstado'
import type { PermisoServidor } from '@/types/api'

/**
 * El motivo del rechazo, la anulación o la reversión, debajo de la etiqueta de
 * estado en las tablas de permisos. Dos líneas como mucho; el resto, al pasar
 * el cursor.
 */
export function MotivoDeEstadoTexto({ permiso }: { permiso: PermisoServidor }) {
  const motivo = motivoDeEstado(permiso)

  if (!motivo) return null

  return (
    <Text size="xs" c="dimmed" lineClamp={2} title={motivo.enTabla}>
      {motivo.enTabla}
    </Text>
  )
}
