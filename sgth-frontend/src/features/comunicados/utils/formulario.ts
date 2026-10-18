import type { ComunicadoFormData } from '../schemas/comunicado.schema'
import type { Comunicado, ComunicadoPayload } from '../services/tipos'

/** Un borrador nuevo: comunicado oficial, para todos, sin fechas. */
export const COMUNICADO_VACIO: ComunicadoFormData = {
  tipo: 'comunicado',
  titulo: '',
  resumen: '',
  cuerpo: '',
  referencia: '',
  audiencia: 'todos',
  unidades: [],
  regimenes: [],
  publicado_desde: '',
  publicado_hasta: '',
  fijado: false,
  requiere_confirmacion: false,
}

/** Lo guardado, de vuelta al formulario para seguir editándolo. */
export function aFormulario(c: Comunicado): ComunicadoFormData {
  return {
    tipo: c.tipo,
    titulo: c.titulo,
    resumen: c.resumen ?? '',
    cuerpo: c.cuerpo ?? '',
    referencia: c.referencia ?? '',
    audiencia: c.audiencia,
    unidades: (c.unidades ?? []).map((u) => ({ id: u.id, incluye_dependencias: u.incluye_dependencias })),
    regimenes: c.regimenes,
    // El backend las devuelve en ISO con la zona de Ecuador: los diez primeros
    // caracteres son el día local.
    publicado_desde: c.publicado_desde?.substring(0, 10) ?? '',
    publicado_hasta: c.publicado_hasta?.substring(0, 10) ?? '',
    fijado: c.fijado,
    requiere_confirmacion: c.requiere_confirmacion,
  }
}

/**
 * Lo que se manda. Vacío viaja como `null`; la referencia y la confirmación de
 * una noticia, también, aunque el backend las descartaría igual.
 */
export function aPayload(f: ComunicadoFormData): ComunicadoPayload {
  const oficial = f.tipo === 'comunicado'
  const segmentada = f.audiencia === 'segmentada'

  return {
    tipo: f.tipo,
    titulo: f.titulo.trim(),
    resumen: f.resumen.trim() || null,
    cuerpo: f.cuerpo || null,
    referencia: oficial ? f.referencia.trim() || null : null,
    audiencia: f.audiencia,
    unidades: segmentada ? f.unidades : [],
    regimenes: segmentada ? f.regimenes : [],
    publicado_desde: f.publicado_desde || null,
    publicado_hasta: f.publicado_hasta || null,
    fijado: f.fijado,
    requiere_confirmacion: oficial && f.requiere_confirmacion,
  }
}
