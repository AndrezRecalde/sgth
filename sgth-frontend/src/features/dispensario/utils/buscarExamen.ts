/** Sin tildes ni mayúsculas: «glucosa» encuentra «Glucosa» y «acido» a «Ácido úrico». */
export function normalizar(texto: string): string {
  return texto.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase()
}

/** Los exámenes cuyo nombre contiene lo buscado; todos si no se busca nada. */
export function filtrarPorNombre<T extends { nombre: string }>(examenes: T[], filtro: string): T[] {
  const buscado = normalizar(filtro.trim())
  return buscado ? examenes.filter((e) => normalizar(e.nombre).includes(buscado)) : examenes
}
