import type { PerfilDeLaConvocatoria } from '../services/convocatoriaService'

/** Lo que una convocatoria no debería anunciar sin tener (P7, PR 16). */
const PARTES: Record<PerfilDeLaConvocatoria['faltan'][number], string> = {
  mision:       'la misión',
  requisitos:   'los requisitos de instrucción y experiencia',
  competencias: 'las competencias',
}

/** «la misión ni las competencias», para «no tiene …». */
function enumerar(partes: string[]): string {
  if (partes.length <= 1) return partes.join('')
  return `${partes.slice(0, -1).join(', ')} ni ${partes[partes.length - 1]}`
}

/** Lo que le falta al perfil, para «El perfil del puesto no tiene …»; `null` si está completo. */
export function faltaDelPerfil(perfil: PerfilDeLaConvocatoria | null | undefined): string | null {
  if (!perfil || perfil.faltan.length === 0) return null
  return enumerar(perfil.faltan.map((f) => PARTES[f]))
}
