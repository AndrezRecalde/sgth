import { NextResponse } from 'next/server'
import type { NextRequest } from 'next/server'

const PUBLIC_ROUTES = ['/login']
const CAMBIAR_PASSWORD_ROUTE = '/cambiar-password'

export function proxy(request: NextRequest) {
  const pathname     = request.nextUrl.pathname
  console.log(`[Proxy Middleware] Request path: ${pathname}`)

  // Si se solicita cerrar sesión explícitamente, borrar las cookies en el servidor y dejar pasar
  if (pathname.startsWith('/login') && request.nextUrl.searchParams.get('logout') === 'true') {
    console.log(`[Proxy Middleware] Explicit logout requested, clearing cookies`)
    const response = NextResponse.next()
    response.cookies.set('sgth_token', '', { maxAge: 0, path: '/' })
    response.cookies.set('sgth_primer_login', '', { maxAge: 0, path: '/' })
    return response
  }

  const tokenRaw     = request.cookies.get('sgth_token')?.value
  const token        = tokenRaw && tokenRaw !== 'undefined' && tokenRaw !== 'null' && tokenRaw.trim() !== '' ? tokenRaw : null
  const primerLogin  = request.cookies.get('sgth_primer_login')?.value

  console.log(`[Proxy Middleware] Checked token: ${token ? 'VALID/PRESENT' : 'NONE'}`)

  const isPublic          = PUBLIC_ROUTES.some(r => pathname.startsWith(r))
  const isCambiarPassword = pathname.startsWith(CAMBIAR_PASSWORD_ROUTE)

  // Sin token → solo puede ver rutas públicas
  if (!token && !isPublic) {
    console.log(`[Proxy Middleware] No token, redirecting to /login`)
    return NextResponse.redirect(new URL('/login', request.url))
  }

  // Con token en ruta pública → va al dashboard o cambiar-password
  if (token && isPublic) {
    if (primerLogin === 'true') {
      console.log(`[Proxy Middleware] Token present, primer login, redirecting to cambiar-password`)
      return NextResponse.redirect(new URL(CAMBIAR_PASSWORD_ROUTE, request.url))
    }
    console.log(`[Proxy Middleware] Token present, redirecting to /`)
    return NextResponse.redirect(new URL('/', request.url))
  }

  // Con token y primer_login pendiente → solo puede ir a cambiar-password
  if (token && primerLogin === 'true' && !isCambiarPassword) {
    console.log(`[Proxy Middleware] Token present, primer login pending, redirecting to cambiar-password`)
    return NextResponse.redirect(new URL(CAMBIAR_PASSWORD_ROUTE, request.url))
  }

  console.log(`[Proxy Middleware] Allowing request to proceed`)
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
