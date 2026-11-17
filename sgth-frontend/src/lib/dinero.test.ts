import { describe, expect, it } from 'vitest'
import { dinero, dineroConMiles } from './dinero'

describe('dineroConMiles', () => {
  it('pone coma de miles y deja el punto decimal, como el resto del sistema', () => {
    expect(dineroConMiles(1234567.891)).toBe('$ 1,234,567.89')
    expect(dineroConMiles('8500')).toBe('$ 8,500.00')
    expect(dineroConMiles(0)).toBe('$ 0.00')
  })

  it('dice con guion lo que no hay', () => {
    expect(dineroConMiles(null)).toBe('—')
    expect(dineroConMiles(undefined)).toBe('—')
  })

  it('no cambia el formato de siempre', () => {
    expect(dinero(1234567.891)).toBe('$ 1234567.89')
  })
})
