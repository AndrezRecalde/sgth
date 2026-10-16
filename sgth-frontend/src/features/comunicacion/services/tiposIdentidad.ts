/**
 * La identidad institucional, como la devuelve el backend
 * (IdentidadInstitucionalController e IdentidadPublicaController;
 * docs/diseno-comunicacion-social.md, 4).
 */

export type VarianteIdentidad =
  | 'isotipo'
  | 'imagotipo_horizontal'
  | 'imagotipo_negativo'
  | 'logo_documentos'
  | 'logo_secundario_documentos'
  | 'hoja_membretada'
  | 'fondo_credencial_anverso'
  | 'fondo_credencial_reverso'

export type EstadoVersion = 'vigente' | 'programada' | 'anterior' | 'anulada'

export interface DatosInstitucionales {
  nombre_oficial: string
  siglas: string
  nombre_corto: string
  ruc: string
  direccion: string
  telefono: string
  sitio_web: string | null
}

export interface VersionIdentidad {
  id: number
  variante: VarianteIdentidad
  variante_etiqueta: string
  mime: string
  ancho: number
  alto: number
  peso: number
  vigente_desde: string
  estado: EstadoVersion
  origen: 'carga_inicial' | 'subida' | 'reutilizada'
  origen_etiqueta: string
  reutiliza_id: number | null
  nota: string | null
  subido_en: string | null
  subido_por?: { id: number; nombre: string } | null
  anulada_en: string | null
  anulada_por?: { id: number; nombre: string } | null
}

/** Una variante en la pantalla: su versión vigente, la que se ve hoy y las programadas. */
export interface EstadoVariante {
  variante: VarianteIdentidad
  etiqueta: string
  obligatoria: boolean
  usos: string
  requisito: string
  formatos: string[]
  respaldo: VarianteIdentidad | null
  vigente: VersionIdentidad | null
  en_uso: VarianteIdentidad | null
  programadas: VersionIdentidad[]
}

export interface EstadoIdentidad {
  variantes: EstadoVariante[]
  datos: DatosInstitucionales & {
    actualizado_en: string | null
    actualizado_por?: { id: number; nombre: string } | null
  }
}

/** Lo público, sin sesión: para el sidebar, el login y los íconos. */
export interface ManifiestoIdentidad {
  variantes: Record<VarianteIdentidad, { version: number; ruta: string; ancho: number; alto: number; respaldo: boolean } | null>
  iconos: Record<'32' | '180' | '192' | '512', string> | null
  datos: DatosInstitucionales
}
