import { describe, expect, it } from 'vitest'
import { avisoDelTipoDePuesto, rolesDelRegimen, tipoDePuestoPara } from '../constants/clasificacionPuesto'
import { puestoSchema } from './puesto.schema'

/*
| Las reglas que el formulario del puesto dice en el campo antes de enviar, las
| mismas de `CoherenciaDelPuesto` en el backend.
*/

const base = { cargo_id: 1, unidad_administrativa_id: 2, plazas: 1, regimen_laboral: 'losep' as const }
const camposConError = (datos: object) =>
  (puestoSchema.safeParse({ ...base, ...datos }).error?.issues ?? []).map((i) => i.path.join('.'))

describe('puestoSchema', () => {
  it('una jefatura tiene una sola plaza', () => {
    expect(camposConError({ es_jefe: true, plazas: 2 })).toEqual(['plazas'])
    expect(camposConError({ es_jefe: true, plazas: 1 })).toEqual([])
    expect(camposConError({ es_jefe: false, plazas: 5 })).toEqual([])
  })

  it('uno de plantilla tiene plazas; uno de contrato, ni plazas ni jefatura (P9)', () => {
    expect(camposConError({ plazas: null })).toEqual(['plazas'])
    expect(camposConError({ tipo: 'contrato', plazas: null })).toEqual([])
    expect(camposConError({ tipo: 'contrato', plazas: null, es_jefe: true })).toEqual(['es_jefe'])
    // Sin tipo, de plantilla, como nacían todos.
    expect(puestoSchema.parse(base).tipo).toBe('plantilla')
  })

  it('el rol «Código del Trabajo» va solo con ese régimen', () => {
    expect(camposConError({ rol_puesto: 'codigo_trabajo' })).toEqual(['rol_puesto'])
    expect(camposConError({ regimen_laboral: 'codigo_trabajo', rol_puesto: 'administrativo' })).toEqual(['rol_puesto'])
    expect(camposConError({ regimen_laboral: 'codigo_trabajo', rol_puesto: 'codigo_trabajo' })).toEqual([])
    expect(camposConError({ regimen_laboral: 'codigo_trabajo', rol_puesto: null })).toEqual([])
  })
})

describe('rolesDelRegimen', () => {
  it('ofrece a cada régimen solo los roles que le caben', () => {
    expect(rolesDelRegimen('codigo_trabajo').map((r) => r.value)).toEqual(['codigo_trabajo'])
    expect(rolesDelRegimen('losep').map((r) => r.value)).not.toContain('codigo_trabajo')
    expect(rolesDelRegimen('losep')).toHaveLength(5)
  })
})

describe('tipoDePuestoPara', () => {
  it('espeja TipoNombramiento::admitePuesto() (P9)', () => {
    expect(tipoDePuestoPara('servicios_ocasionales')).toBe('contrato')
    expect(tipoDePuestoPara('servicios_profesionales')).toBe('contrato')
    expect(tipoDePuestoPara('nombramiento_permanente')).toBe('plantilla')
    expect(tipoDePuestoPara('codigo_trabajo')).toBe('plantilla')
    expect(tipoDePuestoPara('eleccion_popular')).toBe('plantilla')
    // El reemplazo entra al puesto del titular, de cualquier tipo [TH D13].
    expect(tipoDePuestoPara('servicios_ocasionales', true)).toBeNull()
    // Sin modalidad no se filtra.
    expect(tipoDePuestoPara(null)).toBeNull()
  })

  it('avisa solo si el puesto elegido no es del tipo admitido', () => {
    expect(avisoDelTipoDePuesto('plantilla', 'contrato')).toContain('plantilla')
    expect(avisoDelTipoDePuesto('contrato', 'plantilla')).toContain('contrato')
    expect(avisoDelTipoDePuesto('contrato', 'contrato')).toBeUndefined()
    expect(avisoDelTipoDePuesto('plantilla', null)).toBeUndefined()
    expect(avisoDelTipoDePuesto(undefined, 'contrato')).toBeUndefined()
  })
})
