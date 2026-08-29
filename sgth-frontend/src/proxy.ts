import { NextResponse } from 'next/server'
import type { NextRequest } from 'next/server'

const PUBLIC_ROUTES = ['/login']
const CAMBIAR_PASSWORD_ROUTE = '/cambiar-password'

export function proxy(request: NextRequest) {
  const pathname     = request.nextUrl.pathname

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

export default proxy

export const config = {
  matcher: [
    '/',
    '/login',
    '/cambiar-password',
    '/estructura/:path*',
    '/expediente/:path*',
    '/usuarios/:path*',
  ],
}
