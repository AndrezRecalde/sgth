import { describe, expect, it } from 'vitest'
import { nivelSugerido } from './perfilPuesto'

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
