/**
 * La credencial de los servidores, como la devuelve el backend
 * (CredencialResource, ServidorCredencialResource, MiCredencialResource y la
 * verificación pública; docs/diseno-comunicacion-social.md, 6).
 */

import type { PersonaQueActuo } from './tipos'

export type EstadoCredencial = 'vigente' | 'vencida' | 'revocada' | 'anulada'

export type MotivoRevocacion = 'perdida' | 'robo' | 'datos_erroneos' | 'otro'

export type FormatoImpresion = 'cr80' | 'a4'

/**
 * Lo que le toca hacer a Comunicación con cada servidor: tomarle la foto,
 * esperar a que nazca la credencial, imprimirla, reimprimirla o nada.
 */
export type SituacionCredencial =
  | 'sin_foto'
  | 'pendiente'
  | 'vencida'
  | 'sin_imprimir'
  | 'desactualizada'
  | 'al_dia'
  /** Ya no trabaja aquí y su tarjeta impresa no ha vuelto: solo con ese filtro. */
  | 'por_recibir'

export interface ImpresionCredencial {
  id: number
  formato: FormatoImpresion
  formato_etiqueta: string
  lote: string | null
  impresa_en: string | null
  impresa_por?: PersonaQueActuo | null
  datos_impresos: {
    nombres?: string
    apellidos?: string
    cargo?: string | null
    unidad?: string | null
    valida_hasta?: string | null
  }
}

/** Un servidor en la pantalla Credenciales, con su credencial viva si la tiene. */
export interface ServidorConCredencial {
  id: number
  cedula: string
  nombre_completo: string
  unidad: { id: number; nombre: string } | null
  cargo: string | null
  fotografia: { id: number } | null
  situacion: SituacionCredencial
  credencial: {
    id: number
    estado: EstadoCredencial
    estado_etiqueta: string
    vigente: boolean
    valida_hasta: string | null
    impresiones: number
    ultima_impresion: ImpresionCredencial | null
    /** Qué cambió desde que se imprimió: «Cargo», «Foto», «Contacto de emergencia»… */
    cambios_desde_impresion: string[]
    /** Lo que no saldrá en el reverso porque el expediente no lo tiene. */
    datos_faltantes: string[]
  } | null
}

/** Una credencial con su historia, para el historial de un servidor. */
export interface Credencial {
  id: number
  servidor_id: number
  estado: EstadoCredencial
  estado_etiqueta: string
  vigente: boolean
  valida_hasta: string | null
  url_verificacion: string
  creada_en: string | null
  reemplaza_a_id: number | null
  revocada_en: string | null
  revocada_por?: PersonaQueActuo | null
  motivo_revocacion: MotivoRevocacion | null
  motivo_etiqueta: string | null
  detalle_revocacion: string | null
  anulada_en: string | null
  devuelta_en: string | null
  devolucion_registrada_por?: PersonaQueActuo | null
  devolucion_nota: string | null
  impresiones?: ImpresionCredencial[]
}

export interface FiltrosCredenciales {
  buscar?: string
  unidad_administrativa_id?: number
  situacion?: Exclude<SituacionCredencial, 'pendiente'>
}

/** Lo que se imprime: unas credenciales elegidas o todas las de una unidad. */
export type PedidoImpresion =
  | { formato: FormatoImpresion; credenciales: number[] }
  | { formato: FormatoImpresion; unidad_administrativa_id: number }

export interface ContactoEmergencia {
  nombre: string
  parentesco: string | null
  telefono: string | null
}

/** Lo que lleva la tarjeta, del expediente, siempre actual. */
export interface DatosCredencial {
  nombres: string
  apellidos: string
  cedula: string | null
  cargo: string | null
  unidad: string | null
  tipo_sangre: string | null
  contacto_emergencia: ContactoEmergencia | null
  valida_hasta: string | null
  fotografia_id: number | null
}

/** «Mi credencial» del Portal. */
export interface MiCredencial {
  id: number
  estado: EstadoCredencial
  estado_etiqueta: string
  vigente: boolean
  valida_hasta: string | null
  datos: DatosCredencial
  /** Lo que no sale en la credencial porque falta en el expediente. */
  datos_faltantes: string[]
  /** El QR de verificación como imagen SVG en `data:` URL. */
  qr: string | null
  url_verificacion: string
}

/**
 * A dónde lleva el QR. `local` si solo la abre este equipo (desarrollo); en
 * producción, así no se imprime.
 */
export interface DireccionVerificacion {
  url_base: string
  local: boolean
  impresion_bloqueada: boolean
}

/**
 * Lo que enseña la verificación pública: si no está vigente, nada más. La
 * cédula, la sangre y el contacto no llegan nunca.
 */
export type CredencialVerificada =
  | { vigente: false }
  | { vigente: true; nombre: string; cargo: string | null; unidad: string | null; foto: string | null }
