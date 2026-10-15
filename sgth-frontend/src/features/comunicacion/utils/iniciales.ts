/**
 * Iniciales para un avatar sin foto: primer nombre y primer apellido.
 *
 * Los nombres ecuatorianos suelen ser «Nombre1 Nombre2 Apellido1 Apellido2»:
 * con tres palabras o más, el primer apellido es la tercera, no la segunda.
 * Estaba en el menú de usuario; ahora lo comparte con la foto de cada servidor.
 */
export function inicialesDe(nombre: string): string {
  const partes = nombre.trim().split(/\s+/).filter(Boolean)
  if (partes.length === 0) return 'US'
  if (partes.length >= 3) return (partes[0][0] + partes[2][0]).toUpperCase()
  if (partes.length === 2) return (partes[0][0] + partes[1][0]).toUpperCase()
  return partes[0].slice(0, 2).toUpperCase()
}
