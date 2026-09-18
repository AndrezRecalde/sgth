import { fromDateTimeValue } from '@/lib/fecha'
import type { TramoViatico, Viatico } from '@/types/api'
import type { TramoFormData } from '../schemas/viatico.schema'

/** De donde sale todo viático: la sede del GAD. */
export const CIUDAD_BASE = 'Esmeraldas'

type Lugar = { canton_id?: number | null; ciudad?: string | null }

const normalizar = (ciudad?: string | null) =>
  (ciudad ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/\s+/g, ' ').trim()

/**
 * Si dos lugares son el mismo: por el cantón cuando los dos lo tienen; si no,
 * por la ciudad escrita, sin mayúsculas ni tildes. Es la misma regla con la
 * que el backend decide si el último tramo es el regreso.
 */
export function mismoLugar(a: Lugar, b: Lugar): boolean {
  if (a.canton_id && b.canton_id) return a.canton_id === b.canton_id
  const ciudad = normalizar(a.ciudad)
  return ciudad !== '' && ciudad === normalizar(b.ciudad)
}

/**
 * Lo que ya se sabe de un tramo nuevo, para no pedirlo.
 *
 * El primero sale de Esmeraldas a la hora del viático —y tiene que salir
 * exactamente entonces—; los siguientes salen de donde llegó el anterior,
 * cuando llegó. Antes había que escribir todo, y el primer tramo se rechazaba
 * si la hora no coincidía al minuto.
 */
export function valoresNuevoTramo(viatico: Viatico | null | undefined, tramos: TramoViatico[]): Partial<TramoFormData> {
  const anterior = [...tramos].sort((a, b) => a.orden - b.orden).at(-1)

  if (!anterior) {
    return {
      origen_tipo: 'nacional',
      origen_ciudad: CIUDAD_BASE,
      datetime_salida: fromDateTimeValue((viatico?.datetime_salida as string | null) ?? null),
    }
  }

  return {
    origen_tipo: anterior.destino_tipo,
    origen_provincia_id: anterior.destino_provincia_id ?? null,
    origen_canton_id: anterior.destino_canton_id ?? null,
    origen_pais: anterior.destino_pais ?? null,
    origen_ciudad: anterior.destino_ciudad,
    datetime_salida: fromDateTimeValue(anterior.datetime_llegada),
  }
}

/** Un tramo vacío: lo que el formulario necesita para empezar. */
export const TRAMO_VACIO: TramoFormData = {
  tipo_tramo: null,
  origen_tipo: 'nacional',
  origen_provincia_id: null,
  origen_canton_id: null,
  origen_pais: null,
  origen_ciudad: '',
  destino_tipo: 'nacional',
  destino_provincia_id: null,
  destino_canton_id: null,
  destino_pais: null,
  destino_ciudad: '',
  catalogo_transporte_id: 0,
  empresa_transporte_id: null,
  con_empresas: false,
  datetime_salida: '',
  datetime_llegada: '',
}

/** Los valores de un tramo guardado, para corregirlo. */
export function desdeTramo(t: TramoViatico): TramoFormData {
  return {
    tipo_tramo: t.tipo_tramo ?? null,
    origen_tipo: t.origen_tipo,
    origen_provincia_id: t.origen_provincia_id ?? null,
    origen_canton_id: t.origen_canton_id ?? null,
    origen_pais: t.origen_pais ?? null,
    origen_ciudad: t.origen_ciudad,
    destino_tipo: t.destino_tipo,
    destino_provincia_id: t.destino_provincia_id ?? null,
    destino_canton_id: t.destino_canton_id ?? null,
    destino_pais: t.destino_pais ?? null,
    destino_ciudad: t.destino_ciudad,
    catalogo_transporte_id: t.catalogo_transporte_id,
    empresa_transporte_id: t.empresa_transporte_id ?? null,
    // Un tramo con empresa es de un tipo que las tiene.
    con_empresas: t.empresa_transporte_id != null,
    datetime_salida: fromDateTimeValue(t.datetime_salida),
    datetime_llegada: fromDateTimeValue(t.datetime_llegada),
  }
}
