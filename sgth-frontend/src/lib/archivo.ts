/**
 * Entregar al navegador un archivo que llegó como `Blob` desde el backend.
 *
 * Había una copia de estas líneas en cada pantalla que descargaba algo, y
 * algunas revocaban la URL en el acto: en Firefox eso cancela la descarga.
 */

/** Descarga el archivo con el nombre dado. */
export function guardarArchivo(blob: Blob, nombre: string): void {
  const url = URL.createObjectURL(blob)
  const enlace = document.createElement('a')
  enlace.href = url
  enlace.download = nombre
  document.body.appendChild(enlace)
  enlace.click()
  enlace.remove()
  setTimeout(() => URL.revokeObjectURL(url), 60_000)
}

/**
 * Abre el archivo en otra pestaña y, si el navegador la bloquea, lo descarga.
 * Pasa cuando se abre después de esperar al backend, ya fuera del gesto del
 * clic: con `noopener` no hay forma de saberlo, así que la pestaña se abre sin
 * él y se suelta el `opener` después. Devuelve si se abrió.
 */
export function abrirOGuardarArchivo(blob: Blob, nombre: string): boolean {
  const url = URL.createObjectURL(blob)
  const ventana = window.open(url, '_blank')

  if (!ventana) {
    URL.revokeObjectURL(url)
    guardarArchivo(blob, nombre)
    return false
  }

  ventana.opener = null
  setTimeout(() => URL.revokeObjectURL(url), 60_000)
  return true
}

/** Abre el archivo en otra pestaña (un PDF para leerlo, no para guardarlo). */
export function abrirArchivo(blob: Blob): void {
  const url = URL.createObjectURL(blob)
  window.open(url, '_blank', 'noopener')
  setTimeout(() => URL.revokeObjectURL(url), 60_000)
}
