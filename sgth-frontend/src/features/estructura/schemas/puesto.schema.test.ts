import { describe, expect, it } from 'vitest'
import { rolesDelRegimen } from '../constants/clasificacionPuesto'
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
