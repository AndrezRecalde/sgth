/**
 * Un blob como `data:` URL. Se guarda en la caché de TanStack Query y no hay
 * que revocarla, a diferencia de una `blob:`, que en desarrollo React liberaba
 * mientras la imagen la seguía usando (monta dos veces).
 */
export function aDataUrl(blob: Blob): Promise<string> {
  return new Promise((resolver, rechazar) => {
    const lector = new FileReader()
    lector.onload = () => resolver(String(lector.result))
    lector.onerror = () => rechazar(lector.error)
    lector.readAsDataURL(blob)
  })
}
