type NombreDeServidor = {
  nombre?: string | null
  segundo_nombre?: string | null
  apellido?: string | null
  segundo_apellido?: string | null
}

/**
 * El nombre de quien viaja, con sus dos apellidos y sus dos nombres, en ese
 * orden: «Recalde Solano Cristhian Andrés». Es el mismo que imprime la
 * solicitud en PDF, bajo «Apellidos y Nombres».
 *
 * Antes las pantallas mostraban solo el primer nombre y el primer apellido,
 * porque el backend no mandaba los segundos. En una institución los primeros
 * se repiten, y la bandeja de Financiero es justamente donde hay que
 * distinguir a dos personas.
 */
export function nombreDelServidor(s?: NombreDeServidor | null): string {
  if (!s) return '—'

  return [s.apellido, s.segundo_apellido, s.nombre, s.segundo_nombre]
    .map((parte) => parte?.trim())
    .filter(Boolean)
    .join(' ') || '—'
}
