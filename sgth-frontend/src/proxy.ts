import { NextResponse } from 'next/server'
import type { NextRequest } from 'next/server'

/**
 * Rutas abiertas a cualquiera, con sesión o sin ella.
 *
 * Los enlaces que se envían por correo a los servidores para responder una
 * campaña: quien los abre no tiene por qué tener usuario del sistema, y quien
 * sí lo tiene tampoco debe ser desviado al panel al hacer clic en ellos.
 *
 * Y el organigrama, que es información pública de la institución: se consulta
 * desde fuera, sin cuenta de por medio.
 *
 * `/verificar-permiso` es lo que abre el QR impreso en el formulario de
 * permiso. Quien lo escanea suele ser el guardia de la puerta o quien recibe
 * el papel: puede no tener usuario del sistema, y aunque lo tuviera no va a
 * iniciar sesión en el celular para comprobar un folio. La página no revela
 * el motivo ni la cédula completa — eso lo garantiza el endpoint, no esta
 * lista.
 */
const RUTAS_ABIERTAS = ['/assist', '/psicosocial', '/organigrama', '/verificar-permiso']

/**
 * Rutas de autenticación: se ven sin sesión, y CON sesión sobran.
 */
const RUTAS_AUTENTICACION = ['/login']

const RUTA_CAMBIAR_PASSWORD = '/cambiar-password'

const empiezaPor = (pathname: string, rutas: string[]) =>
  rutas.some(ruta => pathname === ruta || pathname.startsWith(`${ruta}/`))

export function proxy(request: NextRequest) {
  const pathname = request.nextUrl.pathname

  // Enlaces públicos de campañas: ni se mira la sesión.
  if (empiezaPor(pathname, RUTAS_ABIERTAS)) {
    return NextResponse.next()
  }

  // Si se solicita cerrar sesión explícitamente, borrar las cookies en el servidor y dejar pasar
  if (pathname.startsWith('/login') && request.nextUrl.searchParams.get('logout') === 'true') {
    const response = NextResponse.next()
    response.cookies.set('sgth_token', '', { maxAge: 0, path: '/' })
    response.cookies.set('sgth_primer_login', '', { maxAge: 0, path: '/' })
    return response
  }

  const tokenRaw     = request.cookies.get('sgth_token')?.value
  const token        = tokenRaw && tokenRaw !== 'undefined' && tokenRaw !== 'null' && tokenRaw.trim() !== '' ? tokenRaw : null
  const primerLogin  = request.cookies.get('sgth_primer_login')?.value

  const esAutenticacion   = empiezaPor(pathname, RUTAS_AUTENTICACION)
  const isCambiarPassword = pathname.startsWith(RUTA_CAMBIAR_PASSWORD)

  // Sin token → solo puede ver rutas públicas
  if (!token && !esAutenticacion) {
    return NextResponse.redirect(new URL('/login', request.url))
  }

  // Con token en ruta pública → va al dashboard o cambiar-password
  if (token && esAutenticacion) {
    if (primerLogin === 'true') {
      return NextResponse.redirect(new URL(RUTA_CAMBIAR_PASSWORD, request.url))
    }
    return NextResponse.redirect(new URL('/', request.url))
  }

  // Con token y primer_login pendiente → solo puede ir a cambiar-password
  if (token && primerLogin === 'true' && !isCambiarPassword) {
    return NextResponse.redirect(new URL(RUTA_CAMBIAR_PASSWORD, request.url))
  }

  return NextResponse.next()
}

export default proxy

/**
 * Se protege TODO menos lo que no puede protegerse.
 *
 * Antes esto era una lista de rutas —`/estructura`, `/expediente`,
 * `/usuarios`— que dejó de existir cuando las pantallas se movieron bajo
 * `/sgth`, `/salud` y `/portal`. El resultado es que el sistema entero quedó
 * fuera del proxy y solo lo defendía el cliente. Una lista blanca de módulos
 * hay que acordarse de actualizar cada vez que nace uno; una exclusión de lo
 * estático se mantiene sola.
 *
 * Quedan fuera los archivos de `public/` —el logo del formulario de acceso,
 * entre otros—: si se pidieran con sesión, nadie sin ella podría ver la
 * pantalla de inicio de sesión completa.
 */
export const config = {
  matcher: [
    '/((?!api|_next/static|_next/image|.*\\.(?:png|jpg|jpeg|gif|svg|webp|ico|txt|xml|webmanifest)$).*)',
  ],
}
