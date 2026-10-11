'use client'

import { Alert } from '@mantine/core'
import { IconArrowBackUp } from '@tabler/icons-react'
import { formatFecha, sumarDias } from '@/lib/fecha'
import type { MovimientoPersonal } from '@/types/api'

/**
 * El acto con que se enlaza esta acción (fase 2.4): el reintegro dice qué
 * ausencia cierra y hasta cuándo dura, y la salida del reemplazo, de qué
 * reintegro nació. Sin enlace no pinta nada.
 */
export function AccionRelacionada({ m }: { m: MovimientoPersonal }) {
  const otro = m.relacionado
  if (!otro) return null

  const codigo = otro.codigo_registro ? ` ${otro.codigo_registro}` : ''

  return (
    <Alert variant="light" color="ocean" icon={<IconArrowBackUp size={16} />}>
      {m.clase === 'reintegro'
        ? `Cierra la ${otro.etiqueta ?? 'ausencia'}${codigo}`
          + (m.fecha_efectiva
            ? `: termina el ${formatFecha(sumarDias(m.fecha_efectiva, -1))}, la víspera del regreso.`
            : '.')
        : `Salida del reemplazo: la preparó el reintegro${codigo} del titular, que vuelve a su puesto.`}
    </Alert>
  )
}
