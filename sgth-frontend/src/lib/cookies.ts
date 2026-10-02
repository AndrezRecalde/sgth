/**
 * Las cookies de sesión que lee `proxy.ts`: `sgth_token` y `sgth_primer_login`.
 *
 * Estaban escritas tres veces —el store, el login y el cambio de contraseña—,
 * y no de la misma forma: solo el store sabía borrarlas también con dominio.
 * Ninguna llevaba `SameSite` ni `Secure`, así que viajaban en peticiones
 * iniciadas desde otros sitios y, fuera de HTTPS, en claro.
 *
 * `SameSite=Lax` y no `Strict`: el QR del permiso y los enlaces de correo
 * abren el sistema desde fuera, y con `Strict` el proxy no vería la cookie en
 * esa primera navegación y mandaría al login a quien ya tiene sesión.
 *
 * No pueden ser `HttpOnly` porque las escribe el navegador.
 */

const atributos = (): string => {
  const seguro = window.location.protocol === 'https:' ? '; Secure' : ''
  return `; path=/; SameSite=Lax${seguro}`
}

export const escribirCookie = (nombre: string, valor: string, dias: number): void => {
  if (typeof document === 'undefined') return

  const expira = new Date(Date.now() + dias * 864e5).toUTCString()
  document.cookie = `${nombre}=${encodeURIComponent(valor)}; expires=${expira}${atributos()}`
}

export const leerCookie = (nombre: string): string | null => {
  if (typeof document === 'undefined') return null

  const valor = document.cookie
    .split('; ')
    .find(c => c.startsWith(`${nombre}=`))
    ?.slice(nombre.length + 1)

  return valor && valor !== 'undefined' && valor !== 'null' && valor.trim() !== ''
    ? decodeURIComponent(valor)
    : null
}

/**
 * Se borra también con el dominio explícito, por si alguna versión anterior
 * la escribió así: una cookie solo se borra con los mismos `path` y `domain`.
 */
export const borrarCookie = (nombre: string): void => {
  if (typeof document === 'undefined') return

  const caducada = `${nombre}=; expires=Thu, 01 Jan 1970 00:00:00 GMT; max-age=0`
  const { hostname } = window.location

  document.cookie = `${caducada}${atributos()}`
  document.cookie = `${caducada}; domain=${hostname}${atributos()}`
  document.cookie = `${caducada}; domain=.${hostname}${atributos()}`
}
