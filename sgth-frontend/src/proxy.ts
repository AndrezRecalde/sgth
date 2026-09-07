import { NextResponse } from 'next/server'
import type { NextRequest } from 'next/server'
import { destinoSeguro } from '@/lib/destino'

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
 * El QR del permiso llegó a estar aquí, cuando se pensaba para que un guardia
 * comprobara la autenticidad del papel. No es ese su uso: lo escanea Talento
 * Humano para confirmar o rechazar el documento, así que va al sistema con
 * sesión como cualquier otra pantalla.
 */
const RUTAS_ABIERTAS = ['/assist', '/psicosocial', '/organigrama']

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

  // Sin token → solo puede ver rutas públicas.
  //
  // Se recuerda a dónde iba. Antes se perdía, y con el inicio de sesión
  // siempre aterrizando en el portal daba igual: se entraba por la pantalla
  // principal. Deja de dar igual con el QR del permiso, que se escanea desde
  // el celular —donde casi nunca hay sesión abierta— y cuya única razón de ser
  // es llevar a ese permiso concreto. Sin esto, quien escanea inicia sesión y
  // acaba en el portal, sin el folio y sin saber qué pasó.
  if (!token && !esAutenticacion) {
    const login = new URL('/login', request.url)
    const destino = pathname + request.nextUrl.search

    if (destino !== '/') {
      login.searchParams.set('next', destino)
    }

    return NextResponse.redirect(login)
  }

  // Con token en ruta pública → va al dashboard o cambiar-password
  if (token && esAutenticacion) {
    if (primerLogin === 'true') {
      return NextResponse.redirect(new URL(RUTA_CAMBIAR_PASSWORD, request.url))
    }

    // Si venía de un enlace concreto —el QR de un permiso, por ejemplo— y ya
    // tiene sesión, se le lleva ahí y no a la pantalla principal.
    return NextResponse.redirect(
      new URL(destinoSeguro(request.nextUrl.searchParams.get('next')), request.url)
    )
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
