'use client'

import { Alert } from '@mantine/core'
import { IconArrowBackUp } from '@tabler/icons-react'
import { formatFecha, sumarDias } from '@/lib/fecha'
import type { MovimientoPersonal } from '@/types/api'
import { ESTADO_LABELS } from '../utils/estadoAccionPersonal'

/**
 * El acto con que se enlaza esta acción:
 * - el reintegro dice qué ausencia cierra y hasta cuándo dura (fase 2.4);
 * - la salida del reemplazo, de qué reintegro nació;
 * - la restitución, qué cesación deja sin efecto por sentencia (fase 2.6);
 * - la remoción del provisional, de qué restitución salió;
 * - y la cesación, si una sentencia la dejó sin efecto.
 *
 * Sin enlace no pinta nada.
 */
export function AccionRelacionada({ m }: { m: MovimientoPersonal }) {
  const texto = textoDelEnlace(m)
  if (!texto) return null

  return (
    <Alert variant="light" color="ocean" icon={<IconArrowBackUp size={16} />}>
      {texto}
    </Alert>
  )
}

function textoDelEnlace(m: MovimientoPersonal): string | null {
  const restitucion = m.restituida_por
  if (restitucion) {
    return `Dejada sin efecto por sentencia: restitución ${restitucion.codigo_registro ?? 'en trámite'}`
      + ` (${ESTADO_LABELS[restitucion.estado].toLowerCase()}).`
  }

  const otro = m.relacionado
  if (!otro) return null

  const codigo = otro.codigo_registro ? ` ${otro.codigo_registro}` : ''

  if (m.clase === 'reintegro') {
    return `Cierra la ${otro.etiqueta ?? 'ausencia'}${codigo}`
      + (m.fecha_efectiva
        ? `: termina el ${formatFecha(sumarDias(m.fecha_efectiva, -1))}, la víspera del regreso.`
        : '.')
  }

  if (m.clase === 'restitucion') {
    return `Deja sin efecto por sentencia la ${otro.etiqueta?.toLowerCase() ?? 'cesación'}${codigo}`
      + (otro.fecha_efectiva ? `, que rigió desde el ${formatFecha(otro.fecha_efectiva)}.` : '.')
  }

  if (otro.clase === 'restitucion') {
    return `Remoción del provisional: la preparó la restitución${codigo} de quien vuelve a este puesto por sentencia.`
  }

  return `Salida del reemplazo: la preparó el reintegro${codigo} del titular, que vuelve a su puesto.`
}
