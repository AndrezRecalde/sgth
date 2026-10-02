/**
 * A dónde se puede volver tras iniciar sesión.
 *
 * Lo usan el proxy y el formulario de acceso, así que vive aquí y no dentro de
 * `proxy.ts`: importar de ese archivo desde un componente de cliente arrastra
 * `next/server` al paquete del navegador.
 *
 * Solo rutas de esta aplicación, y no basta con mirar el texto. Se comprobaba
 * que empezara por `/` y no por `//`, pero el navegador lee la dirección con
 * más manga ancha que esa comprobación: cambia `\` por `/` y descarta los
 * tabuladores y saltos de línea. `/\otro-sitio.com` y `/<TAB>/otro-sitio.com`
 * pasaban el filtro y acababan en `//otro-sitio.com` — un redirect abierto
 * servido desde el dominio institucional, con la credibilidad que eso presta
 * a una página de phishing.
 *
 * Así que se resuelve la dirección igual que lo hará el navegador, contra un
 * origen cualquiera, y solo se acepta si sigue en ese mismo origen. Lo que se
 * devuelve es lo ya resuelto, no el texto de entrada.
 */
const ORIGEN_DE_REFERENCIA = 'http://sgth.invalid'

export const destinoSeguro = (destino: string | null | undefined): string => {
  if (!destino || !destino.startsWith('/')) return '/'

  try {
    const url = new URL(destino, ORIGEN_DE_REFERENCIA)

    return url.origin === ORIGEN_DE_REFERENCIA
      ? `${url.pathname}${url.search}${url.hash}`
      : '/'
  } catch {
    return '/'
  }
}
