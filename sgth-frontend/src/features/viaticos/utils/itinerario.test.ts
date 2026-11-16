import { describe, expect, it } from 'vitest'
import { faltasDelItinerario } from './itinerario'

/*
| Con algo que le falte al itinerario, el backend no aprueba el viático
| (`ItinerarioViaticoService::problemas`). El aviso lo cuenta y el botón de
| aprobar se deshabilita: antes se ofrecía y solo podía terminar en error.
*/
describe('faltasDelItinerario', () => {
  const faltas = ['El viático no tiene itinerario: registre al menos un tramo.']

  it('cuenta lo que falta mientras el itinerario se puede corregir', () => {
    for (const estado of ['solicitado', 'aprobado', 'con_anticipo'] as const) {
      expect(faltasDelItinerario({ estado, itinerario_problemas: faltas }), estado).toEqual(faltas)
    }
  })

  it('no cuenta nada una vez que el viático salió, ni si está completo', () => {
    expect(faltasDelItinerario({ estado: 'en_comision', itinerario_problemas: faltas })).toEqual([])
    expect(faltasDelItinerario({ estado: 'solicitado', itinerario_problemas: [] })).toEqual([])
    expect(faltasDelItinerario({ estado: 'solicitado' })).toEqual([])
  })
})
