import { CAMPOS_CARACTERISTICA, CAMPOS_POR_FICHA, CONSERVADAS_POR_FICHA, type ClaveCaracteristica } from '../constants/fichas'
import type { BienInformatico, FichaTipo, GuardarBienData } from '../services/tipos'
import type { BienFormData } from '../schemas/bien.schema'

/** Todas las características que conoce el formulario; cada ficha usa las suyas. */
const CARACTERISTICAS = Object.keys(CAMPOS_CARACTERISTICA) as ClaveCaracteristica[]

export function valoresDeBien(bien: BienInformatico | null): BienFormData {
  const c = bien?.caracteristicas_tecnicas ?? {}

  return {
    codigo_institucional: bien?.codigo_institucional ?? '',
    codigo_anterior: bien?.codigo_anterior ?? '',
    numero_serie: bien?.numero_serie ?? '',
    tipo_bien_id: bien?.tipo_bien_id ?? Number.NaN,
    subtipo_bien_id: bien?.subtipo_bien_id ?? null,
    marca_id: bien?.marca_id ?? Number.NaN,
    modelo: bien?.modelo ?? '',
    fecha_compra: bien?.fecha_compra ?? '',
    condicion_fisica: bien?.condicion_fisica ?? 'bueno',
    estado_operativo: bien?.estado_operativo ?? 'disponible',
    ubicacion_id: bien?.ubicacion_id ?? null,
    unidad_administrativa_id: bien?.unidad_administrativa_id ?? null,
    bien_padre_id: bien?.bien_padre_id ?? null,
    contrato_vigencia_id: bien?.contrato_vigencia_id ?? null,
    prestable: bien?.prestable ?? false,
    nombre_red: bien?.nombre_red ?? '',
    usuario_dominio: bien?.usuario_dominio ?? '',
    direccion_ip: bien?.direccion_ip ?? '',
    ...(Object.fromEntries(CARACTERISTICAS.map((k) => [k, c[k] ?? ''])) as Record<ClaveCaracteristica, string>),
    observaciones: bien?.observaciones ?? '',
  }
}

/**
 * Para registrar otro igual: lo que comparten los equipos de un mismo lote
 * —tipo, marca, modelo, compra, ubicación, unidad, contrato, características—
 * y en blanco lo que es de cada uno: códigos, serie, red y observaciones.
 */
export function valoresParaOtro(v: BienFormData): BienFormData {
  return {
    ...v,
    codigo_institucional: '', codigo_anterior: '', numero_serie: '',
    nombre_red: '', usuario_dominio: '', direccion_ip: '',
    observaciones: '', bien_padre_id: null, estado_operativo: 'disponible',
    // Lo que la ficha ya no pide es de cada equipo: no pasa al siguiente.
    consumible: '',
  }
}

/** El formulario de un equipo nuevo a partir de otro ya registrado. */
export const valoresDeCopia = (bien: BienInformatico): BienFormData => valoresParaOtro(valoresDeBien(bien))

const oNull = (v: string) => (v.trim() === '' ? null : v.trim())

/**
 * Lo que viaja al backend: vacíos como null y, de las características, solo
 * las de la ficha del tipo y las que ya no pide pero conserva (el consumible
 * de la impresora). Si el tipo cambió, las de la ficha anterior se van.
 */
export function aDatosDeBien(v: BienFormData, editando: boolean, ficha: FichaTipo): GuardarBienData {
  const claves = [...CAMPOS_POR_FICHA[ficha].caracteristicas, ...(CONSERVADAS_POR_FICHA[ficha] ?? [])]
  const caracteristicas = Object.fromEntries(
    claves.map((k) => [k, v[k].trim()]).filter(([, valor]) => valor !== ''),
  )

  return {
    codigo_institucional: v.codigo_institucional.trim(),
    codigo_anterior: oNull(v.codigo_anterior),
    numero_serie: oNull(v.numero_serie),
    tipo_bien_id: v.tipo_bien_id,
    subtipo_bien_id: v.subtipo_bien_id,
    marca_id: v.marca_id,
    modelo: oNull(v.modelo),
    fecha_compra: oNull(v.fecha_compra),
    condicion_fisica: v.condicion_fisica,
    // Al registrar, el equipo nace disponible: el estado se cambia después. Uno
    // dado de baja ya no cambia, y la baja no entra por aquí.
    ...(editando && v.estado_operativo !== 'dado_de_baja' ? { estado_operativo: v.estado_operativo } : {}),
    ubicacion_id: v.ubicacion_id,
    unidad_administrativa_id: v.unidad_administrativa_id,
    bien_padre_id: v.bien_padre_id,
    contrato_vigencia_id: v.contrato_vigencia_id,
    prestable: v.prestable,
    nombre_red: oNull(v.nombre_red),
    usuario_dominio: oNull(v.usuario_dominio),
    direccion_ip: oNull(v.direccion_ip),
    caracteristicas_tecnicas: Object.keys(caracteristicas).length ? caracteristicas : null,
    observaciones: oNull(v.observaciones),
  }
}
