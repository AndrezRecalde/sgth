import type { BienInformatico, GuardarBienData } from '../services/tipos'
import type { BienFormData } from '../schemas/bien.schema'

/** Las características que el Excel de TIC trae sueltas, guardadas juntas. */
const CARACTERISTICAS = ['procesador', 'memoria_ram', 'tipo_disco', 'capacidad_disco', 'sistema_operativo'] as const

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
    procesador: c.procesador ?? '',
    memoria_ram: c.memoria_ram ?? '',
    tipo_disco: c.tipo_disco ?? '',
    capacidad_disco: c.capacidad_disco ?? '',
    sistema_operativo: c.sistema_operativo ?? '',
    observaciones: bien?.observaciones ?? '',
  }
}

const oNull = (v: string) => (v.trim() === '' ? null : v.trim())

/** Lo que viaja al backend: vacíos como null y las características juntas. */
export function aDatosDeBien(v: BienFormData, editando: boolean): GuardarBienData {
  const caracteristicas = Object.fromEntries(
    CARACTERISTICAS.map((k) => [k, v[k].trim()]).filter(([, valor]) => valor !== ''),
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
