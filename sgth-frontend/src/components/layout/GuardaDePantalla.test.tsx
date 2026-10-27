// @vitest-environment jsdom
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MantineProvider } from '@mantine/core'
import permisosPorRol from '@/test/permisos-por-rol.json'
import { theme } from '@/config/mantine.theme'
import { ROUTES, type Subsistema } from '@/config/routes'
import { useAuthStore } from '@/store/auth.store'
import { GuardaDePantalla } from './GuardaDePantalla'

/*
| La guarda de pantalla del shell (docs/plan-roles-y-accesos.md, PR 9): abre lo
| que el menú le ofrece al usuario, dice que no tiene acceso a lo demás y lleva
| del `home` de un subsistema a la pantalla por la que se entra.
*/

const navegacion = vi.hoisted(() => ({
  pathname: '/',
  replace:  vi.fn(),
}))

vi.mock('next/navigation', () => ({
  usePathname: () => navegacion.pathname,
  useRouter:   () => ({ replace: navegacion.replace, push: vi.fn(), prefetch: vi.fn() }),
}))

// Sin el router de la aplicación montado, `next/link` no tiene a quién avisar.
vi.mock('next/link', () => ({
  default: ({ href, children, ...resto }: { href: string; children: React.ReactNode }) => (
    <a href={href} {...resto}>{children}</a>
  ),
}))

type Rol = keyof typeof permisosPorRol

function entrarComo(rol: Rol) {
  useAuthStore.setState({
    isAuthenticated: true,
    usuario: { id: 1, email: `prueba.${rol}@gadpe.test`, roles: [rol], permisos: permisosPorRol[rol] },
  })
}

function abrir(subsistema: Subsistema, pathname: string) {
  navegacion.pathname = pathname

  return render(
    <MantineProvider theme={theme}>
      <GuardaDePantalla subsistema={subsistema}>
        <p>Contenido de la pantalla</p>
      </GuardaDePantalla>
    </MantineProvider>,
  )
}

beforeEach(() => {
  navegacion.replace.mockReset()
})

describe('GuardaDePantalla', () => {
  it('pinta la pantalla que el menú le ofrece, también en sus detalles', () => {
    entrarComo('medico')
    abrir('salud', ROUTES.SALUD.CONSULTA_TURNO('T-0042'))

    expect(screen.getByText('Contenido de la pantalla')).toBeTruthy()
    expect(navegacion.replace).not.toHaveBeenCalled()
  })

  it('dice que no tiene acceso a la que no está en su menú, con el camino a su inicio', () => {
    entrarComo('enfermera')
    abrir('salud', ROUTES.SALUD.CONSULTAS)

    expect(screen.queryByText('Contenido de la pantalla')).toBeNull()
    expect(screen.getByText('No tiene acceso a esta pantalla')).toBeTruthy()
    expect(screen.getByRole('link', { name: 'Ir al inicio' }).getAttribute('href'))
      .toBe(ROUTES.SALUD.ENFERMERIA)
  })

  it('sin ninguna pantalla en el subsistema, lo dice y lleva a otro', () => {
    entrarComo('servidor')
    abrir('salud', ROUTES.SALUD.FARMACIA)

    expect(screen.getByText('No tiene acceso a Dispensario Médico')).toBeTruthy()
    expect(screen.getByRole('link', { name: 'Ir al inicio' }).getAttribute('href'))
      .toBe(ROUTES.PORTAL.HOME)
  })

  it('no deja ver el home de un subsistema sin pantallas en él', () => {
    entrarComo('servidor')
    abrir('sgth', ROUTES.SGTH.HOME)

    expect(screen.getByText('No tiene acceso a Talento Humano')).toBeTruthy()
    expect(navegacion.replace).not.toHaveBeenCalled()
  })

  it('lleva del home del SGTH a la primera pantalla del menú', () => {
    entrarComo('recepcion')
    abrir('sgth', ROUTES.SGTH.HOME)

    expect(screen.queryByText('Contenido de la pantalla')).toBeNull()
    expect(navegacion.replace).toHaveBeenCalledWith(ROUTES.SGTH.ASISTENCIA_PERMISOS)
  })

  it('lleva del tablero del Dispensario a la pantalla de trabajo de quien no lo tiene', () => {
    entrarComo('medico')
    abrir('salud', ROUTES.SALUD.HOME)

    expect(navegacion.replace).toHaveBeenCalledWith(ROUTES.SALUD.CONSULTAS)
  })

  it('deja en el tablero a quien lo tiene en el menú', () => {
    entrarComo('admin-dispensario')
    abrir('salud', ROUTES.SALUD.HOME)

    expect(screen.getByText('Contenido de la pantalla')).toBeTruthy()
    expect(navegacion.replace).not.toHaveBeenCalled()
  })

  it('pinta la portada del Portal, que no cuelga de ninguna entrada', () => {
    entrarComo('servidor')
    abrir('portal', ROUTES.PORTAL.HOME)

    expect(screen.getByText('Contenido de la pantalla')).toBeTruthy()
  })
})
