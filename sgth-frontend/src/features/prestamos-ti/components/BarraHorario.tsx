'use client'

import { Tooltip } from '@mantine/core'
import type { BloqueOcupado } from '../services/tipos'
import { fraccionDeHora, hora, horaEnBarra, horasDeAtencion, posicionEnDia } from '../utils/agenda'
import classes from './BarraHorario.module.css'

interface Props {
  dia: string
  bloques: BloqueOcupado[]
  apertura: string
  cierre: string
  /** La barra chica de la vista por semana. */
  compacta?: boolean
  /** Sin préstamo posible ese día o con el equipo fuera de servicio. */
  inerte?: boolean
  /** El préstamo en curso del equipo ya debía volver: su bloque va en rojo. */
  vencido?: boolean
  /** Al hacer clic en un punto libre: la hora (`YYYY-MM-DDTHH:mm`) redondeada a la media hora. */
  onElegir?: (inicio: string) => void
}

/** Qué dice cada bloque al pasar el cursor; el folio y quién, solo si llegan (TIC). */
function textoBloque(b: BloqueOcupado): string {
  const quien = b.folio ? ` · ${b.folio}${b.servidor ? `, ${b.servidor}` : ''}` : ''
  return `Ocupado de ${hora(b.inicio)} a ${hora(b.fin)}${quien}`
}

/**
 * El horario de atención de un día como una barra: lo libre de fondo y lo
 * ocupado encima. Un clic en lo libre propone esa hora para pedir.
 */
export function BarraHorario({ dia, bloques, apertura, cierre, compacta, inerte, vencido: hayVencido, onElegir }: Props) {
  const pedible = !inerte && onElegir !== undefined

  const elegir = (e: React.MouseEvent<HTMLDivElement>) => {
    if (!pedible) return
    const caja = e.currentTarget.getBoundingClientRect()
    onElegir(horaEnBarra(dia, (e.clientX - caja.left) / caja.width, apertura, cierre))
  }

  return (
    <div
      className={[classes.barra, compacta && classes.compacta, pedible && classes.pedible, inerte && classes.inerte].filter(Boolean).join(' ')}
      onClick={elegir}
      role={pedible ? 'button' : undefined}
      aria-label={pedible ? `Pedir el ${dia} desde la hora en que haga clic` : undefined}
    >
      {!compacta && horasDeAtencion(apertura, cierre).map((h) => (
        <span key={h} className={classes.marca} style={{ left: `${fraccionDeHora(h, apertura, cierre) * 100}%` }} />
      ))}
      {bloques.map((b) => {
        const pos = posicionEnDia(b, dia, apertura, cierre)
        if (!pos) return null
        const vencido = hayVencido === true && b.estado === 'en_curso'
        return (
          <Tooltip key={`${b.inicio}-${b.fin}`} label={vencido ? `${textoBloque(b)} (vencido)` : textoBloque(b)} withArrow>
            <span
              className={[classes.bloque, vencido && classes.bloqueVencido].filter(Boolean).join(' ')}
              style={{ left: `${pos.izquierda}%`, width: `${pos.ancho}%` }}
              onClick={(e) => e.stopPropagation()}
            />
          </Tooltip>
        )
      })}
    </div>
  )
}

/** Las horas debajo de la barra del día. */
export function EscalaHoras({ apertura, cierre }: { apertura: string; cierre: string }) {
  return (
    <div className={classes.horas} aria-hidden>
      {horasDeAtencion(apertura, cierre).map((h) => (
        <span key={h} className={classes.hora} style={{ left: `${fraccionDeHora(h, apertura, cierre) * 100}%` }}>
          {String(h).padStart(2, '0')}
        </span>
      ))}
    </div>
  )
}
