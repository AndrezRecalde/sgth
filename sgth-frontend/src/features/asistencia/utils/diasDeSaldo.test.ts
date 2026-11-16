import { describe, expect, it } from 'vitest'
import { diasDeSaldo } from './diasDeSaldo'

describe('diasDeSaldo', () => {
  it('escribe los decimales con punto', () => {
    expect(diasDeSaldo(29.75)).toBe('29.75 días')
    expect(diasDeSaldo(0.5)).toBe('0.5 días')
  })

  it('no agrega decimales a un saldo entero', () => {
    expect(diasDeSaldo(30)).toBe('30 días')
    expect(diasDeSaldo(0)).toBe('0 días')
  })

  it('usa el singular solo con un día', () => {
    expect(diasDeSaldo(1)).toBe('1 día')
    expect(diasDeSaldo(1.5)).toBe('1.5 días')
  })

  it('corta en dos decimales lo que deja la suma de horas', () => {
    expect(diasDeSaldo(29.749999999)).toBe('29.75 días')
    expect(diasDeSaldo(2.3333333)).toBe('2.33 días')
  })
})
