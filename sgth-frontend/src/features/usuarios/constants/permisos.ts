/**
 * Los permisos viajan como slug (`gestionar-inventario-med`). El panel los
 * mostraba tal cual, que es legible para quien programó el enum y para nadie
 * más.
 */
export const etiquetaPermiso = (nombre: string): string => {
  const texto = nombre.replace(/-/g, ' ')
  return texto.charAt(0).toUpperCase() + texto.slice(1)
}
