import { describe, expect, it } from 'vitest'
import { nivelSugerido, textoExperiencia } from './perfilPuesto'
import { descripcionPuestoSchema } from '../schemas/descripcionPuesto.schema'

/*
| El nivel con que se propone una competencia nueva (D5 del diseño del Manual
| de Puestos): manda el del grado, que calcula el backend.
*/
describe('nivelSugerido', () => {
  it('toma el del grado antes que el de la complejidad', () => {
    // SP6: complejidad alta, pero el manual lo pone en medio.
    expect(nivelSugerido('medio', 'alto')).toBe('medio')
  })

  it('sin grado, el de la complejidad; sin ninguno, medio', () => {
    expect(nivelSugerido(null, 'bajo')).toBe('bajo')
    expect(nivelSugerido(undefined, null)).toBe('medio')
  })
})

describe('textoExperiencia', () => {
  it('escribe la experiencia como el manual', () => {
    expect(textoExperiencia(30)).toBe('2 años y 6 meses')
    expect(textoExperiencia(72)).toBe('6 años')
    expect(textoExperiencia(1)).toBe('1 mes')
    expect(textoExperiencia(0)).toBe('sin experiencia')
  })
})

describe('descripcionPuestoSchema', () => {
  const base = {
    mision: '', relaciones: '', subproceso: '', nivel: null,
    area_conocimiento: '', especificidad_experiencia: '', capacitacion: '',
  }
  const campos = (requisitos: object[]) =>
    (descripcionPuestoSchema.safeParse({ ...base, requisitos }).error?.issues ?? []).map((i) => i.path.join('.'))

  it('los estudios en curso piden qué está aprobado, como el backend', () => {
    expect(campos([{ nivel_instruccion: 'estudios_superiores_en_curso', detalle: '', anios: 0, meses: 0 }]))
      .toEqual(['requisitos.0.detalle'])
    expect(campos([{ nivel_instruccion: 'estudios_superiores_en_curso', detalle: 'Tercer año', anios: 0, meses: 0 }]))
      .toEqual([])
    expect(campos([{ nivel_instruccion: 'tercer_nivel', detalle: '', anios: 2, meses: 12 }]))
      .toEqual(['requisitos.0.meses'])
  })
})
