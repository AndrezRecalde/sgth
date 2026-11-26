import { describe, expect, it } from 'vitest'
import { causalesDisponibles } from './catalogoAcciones'
import type { CausalDelCatalogo, ClaseDelCatalogo } from '@/types/api'

/*
| La base legal de una causal depende del régimen (fase 4.1c): la renuncia y el
| fallecimiento son las dos figuras que la elección popular y el contrato civil
| comparten con el régimen LOSEP, y cada uno se rige por su norma. Hasta el
| 2026-11-26 el formulario le enseñaba a todos la de la LOSEP.
*/

const causal = (parcial: Partial<CausalDelCatalogo>): CausalDelCatalogo => ({
  codigo: 'renuncia',
  etiqueta: 'Renuncia',
  base_legal: 'LOSEP Art. 47 a; Reglamento Art. 102',
  base_legal_por_nombramiento: [],
  nombramientos_elegibles: ['nombramiento_permanente'],
  dictamen_medico_por_defecto: false,
  ...parcial,
} as CausalDelCatalogo)

const renuncia = causal({
  nombramientos_elegibles: ['nombramiento_permanente', 'eleccion_popular'],
  base_legal_por_nombramiento: [
    { nombramiento: 'eleccion_popular', base_legal: 'COOTAD Arts. 52 núm. 1 y 336' },
  ],
})

const jubilacion = causal({
  codigo: 'jubilacion',
  etiqueta: 'Jubilación',
  base_legal: 'LOSEP Art. 47 j',
})

const cesacion = { causales: [renuncia, jubilacion] } as ClaseDelCatalogo

describe('causalesDisponibles', () => {
  it('le da a cada régimen la base legal que lo rige', () => {
    const [primera] = causalesDisponibles(cesacion, 'eleccion_popular')

    expect(primera.codigo).toBe('renuncia')
    expect(primera.base_legal).toBe('COOTAD Arts. 52 núm. 1 y 336')
  })

  it('deja la de la LOSEP, que es el caso general', () => {
    const codigos = causalesDisponibles(cesacion, 'nombramiento_permanente')

    expect(codigos.map((c) => c.base_legal)).toEqual([
      'LOSEP Art. 47 a; Reglamento Art. 102', 'LOSEP Art. 47 j',
    ])
  })

  it('descarta las causales que no aplican al nombramiento', () => {
    expect(causalesDisponibles(cesacion, 'eleccion_popular').map((c) => c.codigo))
      .toEqual(['renuncia'])
  })

  it('sin nombramiento las devuelve tal como llegan, sin filtrar ni sustituir', () => {
    expect(causalesDisponibles(cesacion, null)).toBe(cesacion.causales)
  })
})
