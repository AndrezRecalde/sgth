import { describe, expect, it } from 'vitest'
import type { UnidadMasaSalarial, VinculoMasaSalarial } from '@/types/api'
import {
  abiertasDeEntrada, esAgruparMasa, idsDelSubarbol, unidadesVisibles, vinculosDeLaSeleccion,
} from './masaSalarial'

function unidad(id: number, profundidad: number, padre_id: number | null, tiene_hijas = false): UnidadMasaSalarial {
  return {
    id, nombre: `Unidad ${id}`, nivel: profundidad + 1, profundidad, padre_id, tiene_hijas,
    personas: 1, mensual: 1000, anual: 12000, porcentaje: 10, promedio: 1000,
    propias: { personas: 1, mensual: 1000 },
  }
}

function vinculo(contrato_id: number, cambios: Partial<VinculoMasaSalarial> = {}): VinculoMasaSalarial {
  return {
    contrato_id, servidor_id: contrato_id, servidor: `Servidor ${contrato_id}`, cedula: null, puesto: null, grado: null,
    tipo_nombramiento: 'nombramiento_permanente', tipo_nombramiento_etiqueta: 'Nombramiento Permanente',
    regimen: 'losep', regimen_etiqueta: 'LOSEP', unidad_id: 2, unidad: 'Unidad 2', remuneracion: 1000, situacion: 'suma',
    ...cambios,
  }
}

// Prefectura (1) ─ Obras (2) ─ Vías (3)
//              └─ Administrativa (4)
const ARBOL = [unidad(1, 0, null, true), unidad(2, 1, 1, true), unidad(3, 2, 2), unidad(4, 1, 1)]

describe('el árbol de unidades en lista', () => {
  it('lo que cuelga de una unidad son las filas que la siguen con más profundidad', () => {
    expect(idsDelSubarbol(ARBOL, 2)).toEqual([2, 3])
    expect(idsDelSubarbol(ARBOL, 1)).toEqual([1, 2, 3, 4])
    expect(idsDelSubarbol(ARBOL, 4)).toEqual([4])
    expect(idsDelSubarbol(ARBOL, 99)).toEqual([])
  })

  it('se ven las de arriba y las hijas de una abierta, y abre de entrada la primera capa', () => {
    const deEntrada = abiertasDeEntrada(ARBOL)
    expect([...deEntrada]).toEqual([1])
    expect(unidadesVisibles(ARBOL, deEntrada).map((u) => u.id)).toEqual([1, 2, 4])
    expect(unidadesVisibles(ARBOL, new Set([1, 2])).map((u) => u.id)).toEqual([1, 2, 3, 4])
    // Cerrar la raíz esconde también a las nietas, aunque su madre siga abierta.
    expect(unidadesVisibles(ARBOL, new Set([2])).map((u) => u.id)).toEqual([1])
  })
})

describe('vinculosDeLaSeleccion', () => {
  const detalle = [
    vinculo(1),
    vinculo(2, { unidad_id: 3, tipo_nombramiento: 'servicios_ocasionales', situacion: 'sin_remuneracion_en_la_fecha' }),
    vinculo(3, { unidad_id: 4, regimen: 'codigo_trabajo', tipo_nombramiento: 'codigo_trabajo' }),
    vinculo(4, { unidad_id: 3, remuneracion: null, situacion: 'sin_remuneracion_registrada' }),
  ]
  const ids = (vs: VinculoMasaSalarial[]) => vs.map((v) => v.contrato_id)

  it('una fila de las vistas da solo lo que suma, como su cifra', () => {
    expect(ids(vinculosDeLaSeleccion(detalle, { por: 'regimen', clave: 'losep', titulo: '' }))).toEqual([1])
    expect(ids(vinculosDeLaSeleccion(detalle, { por: 'tipo_nombramiento', clave: 'servicios_ocasionales', titulo: '' }))).toEqual([])
    expect(ids(vinculosDeLaSeleccion(detalle, { por: 'unidad', ids: [2, 3], titulo: '' }))).toEqual([1])
  })

  it('lo que va aparte se abre por su situación', () => {
    expect(ids(vinculosDeLaSeleccion(detalle, { por: 'situacion', situacion: 'sin_remuneracion_en_la_fecha', titulo: '' }))).toEqual([2])
    expect(ids(vinculosDeLaSeleccion(detalle, { por: 'situacion', situacion: 'sin_remuneracion_registrada', titulo: '' }))).toEqual([4])
  })
})

it('esAgruparMasa reconoce solo las tres vistas', () => {
  expect(esAgruparMasa('unidad')).toBe(true)
  expect(esAgruparMasa('partida')).toBe(false)
  expect(esAgruparMasa(null)).toBe(false)
})
