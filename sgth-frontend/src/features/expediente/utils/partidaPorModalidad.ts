/**
 * Qué partida presupuestaria paga cada modalidad de vinculación.
 *
 * Espejo de `App\Enums\PartidaPorModalidad` en el backend, que es la
 * autoridad. Esto existe para no ofrecerle a Talento Humano las veinte
 * partidas del catálogo cuando solo una o dos aplican.
 *
 * Correspondencia confirmada por la Dirección Financiera del GAD Provincial de
 * Esmeraldas en agosto de 2026.
 */
export const PARTIDAS_POR_MODALIDAD: Record<string, string[]> = {
  nombramiento_permanente:     ['510105'],
  nombramiento_provisional:    ['510105'],
  libre_nombramiento_remocion: ['510105'],
  eleccion_popular:            ['510105'],
  servicios_ocasionales:       ['510510'],
  // El obrero se imputa a inversión. Se ofrece también la de gasto corriente
  // porque el clasificador la contempla con la misma denominación y está
  // pendiente de confirmar si el GAD la usa.
  codigo_trabajo:              ['710106', '510106'],
  // Corriente o inversión según qué fondo financie el contrato: un dato que
  // vive en el convenio, no en el expediente. Por eso se ofrecen las dos.
  servicios_profesionales:     ['530606', '730606'],
}

/** Códigos que aplican a una modalidad, en orden de preferencia. */
export function codigosDePartida(modalidad?: string | null): string[] {
  return modalidad ? (PARTIDAS_POR_MODALIDAD[modalidad] ?? []) : []
}

/** ¿La modalidad admite más de una partida y exige que alguien elija? */
export function exigeElegirPartida(modalidad?: string | null): boolean {
  return codigosDePartida(modalidad).length > 1
}

interface VinculoConPartida {
  partida_presupuestaria?: { codigo?: string | null } | null
  puesto?: { partida_presupuestaria?: { codigo?: string | null } | null } | null
}

/**
 * El código de la partida que paga un vínculo. Sale del contrato, no del
 * puesto: un ocasional y un permanente sobre la misma plaza se imputan a
 * partidas distintas. La del puesto solo respalda a los vínculos anteriores a
 * esa distinción. Es la misma regla con que el backend llena la
 * `partida_origen` de una acción de personal.
 */
export function partidaDelVinculo(vinculo?: VinculoConPartida | null): string | null {
  return vinculo?.partida_presupuestaria?.codigo
    ?? vinculo?.puesto?.partida_presupuestaria?.codigo
    ?? null
}
