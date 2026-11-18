import { describe, expect, it } from 'vitest'
import { faltaLaCuenta } from './cuenta'

/*
| Sin cuenta principal de viáticos, Gestión Financiera no tiene a dónde
| transferir. Antes solo se enteraba quien abría el PDF de la solicitud, que es
| el día del pago.
*/
describe('faltaLaCuenta', () => {
  it('avisa durante toda la vida del viático, con anticipo y sin él', () => {
    const estados = [
      'solicitado', 'aprobado', 'con_anticipo', 'en_comision',
      'pendiente_liquidacion', 'liquidado', 'contabilizado',
    ] as const

    for (const estado of estados) {
      expect(faltaLaCuenta({ estado, sin_cuenta_bancaria: true }), estado).toBe(true)
    }
  })

  it('calla en los que ya no se le pagan a nadie', () => {
    expect(faltaLaCuenta({ estado: 'cancelado', sin_cuenta_bancaria: true })).toBe(false)
    expect(faltaLaCuenta({ estado: 'rechazado', sin_cuenta_bancaria: true })).toBe(false)
  })

  it('calla si la cuenta está, y si el backend no dijo nada', () => {
    expect(faltaLaCuenta({ estado: 'solicitado', sin_cuenta_bancaria: false })).toBe(false)
    expect(faltaLaCuenta({ estado: 'solicitado' })).toBe(false)
  })
})
