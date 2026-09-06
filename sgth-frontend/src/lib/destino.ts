/**
 * A dónde se puede volver tras iniciar sesión.
 *
 * Lo usan el proxy y el formulario de acceso, así que vive aquí y no dentro de
 * `proxy.ts`: importar de ese archivo desde un componente de cliente arrastra
 * `next/server` al paquete del navegador.
 *
 * Solo rutas de esta aplicación. Comprobar únicamente que empiece por `/` no
 * basta: `//otro-sitio.com` también lo cumple, y `new URL()` lo resuelve como
 * dominio externo — un redirect abierto de manual, servido desde el dominio
 * institucional y con la credibilidad que eso presta a una página de phishing.
 */
export const destinoSeguro = (destino: string | null | undefined): string =>
  destino && destino.startsWith('/') && !destino.startsWith('//')
    ? destino
    : '/'
