'use client'

import { Tooltip, UnstyledButton } from '@mantine/core'
import { CONDICION_LABELS } from '@/features/dispensario/services/odontogramaService'
import type { OdontogramaPieza } from '@/features/dispensario/services/odontogramaService'
import classes from './Tooth.module.css'

export type TipoDiente = 'incisivo' | 'canino' | 'premolar' | 'molar'
export type Arcada = 'superior' | 'inferior'

interface CrownShape {
  crown: string
  groove?: string
}

const CROWNS: Record<TipoDiente, CrownShape> = {
  incisivo: {
    crown: 'M 6 14 L 24 14 Q 26 14 26 18 L 26 30 Q 26 36 15 37 Q 4 36 4 30 L 4 18 Q 4 14 6 14 Z',
  },
  canino: {
    crown: 'M 7 14 L 23 14 Q 25 14 25 19 L 25 27 Q 25 30 21 31 L 17 34 Q 15 38 13 34 L 9 31 Q 5 30 5 27 L 5 19 Q 5 14 7 14 Z',
  },
  premolar: {
    crown: 'M 6 14 L 24 14 Q 26 14 26 19 L 26 28 Q 26 33 20 34 Q 17 35 15 33 Q 13 35 10 34 Q 4 33 4 28 L 4 19 Q 4 14 6 14 Z',
  },
  molar: {
    crown: 'M 4 14 L 26 14 Q 28 14 28 19 L 28 29 Q 28 34 22 35 Q 19 36.5 17 35 Q 15 36.5 13 35 Q 11 36.5 8 35 Q 2 34 2 29 L 2 19 Q 2 14 4 14 Z',
    groove: 'M 15 20 L 15 30 M 9 25 L 21 25',
  },
}

const ROOTS: Record<TipoDiente, string[]> = {
  incisivo: ['M 12 1 Q 15 -1 18 1 L 18 14 L 12 14 Z'],
  canino: ['M 12 1 Q 15 -1 18 1 L 19 14 L 11 14 Z'],
  premolar: [
    'M 9 1 Q 11.5 -1 14 1 L 14 14 L 9.5 14 Z',
    'M 16 1 Q 18.5 -1 21 1 L 20.5 14 L 16 14 Z',
  ],
  molar: [
    'M 4 2 Q 6 0 8 2 L 9 14 L 4.5 14 Z',
    'M 22 2 Q 24 0 26 2 L 25.5 14 L 22 14 Z',
  ],
}

function getTipoDiente(numeroPieza: number, esTemporal: boolean): TipoDiente {
  const posicion = numeroPieza % 10

  if (esTemporal) {
    if (posicion <= 2) return 'incisivo'
    if (posicion === 3) return 'canino'
    return 'molar'
  }

  if (posicion <= 2) return 'incisivo'
  if (posicion === 3) return 'canino'
  if (posicion <= 5) return 'premolar'
  return 'molar'
}

function getArcada(numeroPieza: number): Arcada {
  const cuadrante = Math.floor(numeroPieza / 10)
  return [1, 2, 5, 6].includes(cuadrante) ? 'superior' : 'inferior'
}

interface Props {
  pieza:   OdontogramaPieza
  color:   string
  onClick: (pieza: OdontogramaPieza) => void
}

export function Tooth({ pieza, color, onClick }: Props) {
  const esTemporal = pieza.denticion === 'temporal'
  const tipo = getTipoDiente(pieza.numero_pieza, esTemporal)
  const arcada = getArcada(pieza.numero_pieza)
  const forma = CROWNS[tipo]
  const raices = ROOTS[tipo]

  const label = CONDICION_LABELS[pieza.condicion]

  return (
    <Tooltip label={`Pieza ${pieza.numero_pieza} — ${label}`} withArrow>
      <UnstyledButton
        className={classes.diente}
        onClick={() => onClick(pieza)}
        aria-label={`Pieza ${pieza.numero_pieza}, ${label}`}
      >
        <svg
          viewBox="0 0 30 40"
          width={38}
          height={50}
          style={{
            transform: arcada === 'inferior' ? 'scaleY(-1)' : undefined,
          }}
        >
          {raices.map((d, i) => (
            <path
              key={i}
              d={d}
              className={classes.raiz}
            />
          ))}
          <path
            d={forma.crown}
            style={{
              fill: `var(--mantine-color-${color}-light)`,
              stroke: `var(--mantine-color-${color}-6)`,
            }}
            className={classes.corona}
          />
          {forma.groove && (
            <path
              d={forma.groove}
              className={classes.surco}
              style={{ stroke: `var(--mantine-color-${color}-7)` }}
            />
          )}
        </svg>
        <span className={classes.numero}>
          {pieza.numero_pieza}
        </span>
      </UnstyledButton>
    </Tooltip>
  )
}
