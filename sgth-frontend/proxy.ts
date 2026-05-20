import { NextResponse } from 'next/server'
import type { NextRequest } from 'next/server'

const PUBLIC_ROUTES = ['/login']
const CAMBIAR_PASSWORD_ROUTE = '/cambiar-password'

export function proxy(request: NextRequest) {
  const token        = request.cookies.get('sgth_token')?.value
  const primerLogin  = request.cookies.get('sgth_primer_login')?.value
  const pathname     = request.nextUrl.pathname

  const isPublic          = PUBLIC_ROUTES.some(r => pathname.startsWith(r))
  const isCambiarPassword = pathname.startsWith(CAMBIAR_PASSWORD_ROUTE)

  // Sin token → solo puede ver rutas públicas
  if (!token && !isPublic) {
    return NextResponse.redirect(new URL('/login', request.url))
  }

  // Con token en ruta pública → va al dashboard o cambiar-password
  if (token && isPublic) {
    if (primerLogin === 'true') {
      return NextResponse.redirect(new URL(CAMBIAR_PASSWORD_ROUTE, request.url))
    }
    return NextResponse.redirect(new URL('/', request.url))
  }

  // Con token y primer_login pendiente → solo puede ir a cambiar-password
  if (token && primerLogin === 'true' && !isCambiarPassword) {
    return NextResponse.redirect(new URL(CAMBIAR_PASSWORD_ROUTE, request.url))
  }

  return NextResponse.next()
}

export const config = {
  matcher: [
    '/((?!api|_next/static|_next/image|favicon.ico|.*\\.svg|.*\\.png|.*\\.jpg|.*\\.ico).*)',
  ],
}
