// @vitest-environment jsdom
import { describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MantineProvider } from '@mantine/core'
import permisosPorRol from '@/test/permisos-por-rol.json'
import { theme } from '@/config/mantine.theme'
import { ROUTES } from '@/config/routes'
import { useAuthStore } from '@/store/auth.store'
import { RequierePermiso } from './RequierePermiso'

/*
| Las pantallas de alta o edición que heredan del menú la guarda de su listado
| (docs/plan-roles-y-accesos.md, PR 14): el auditor consulta el inventario y
| abría «Registrar equipo» por la dirección.
*/

vi.mock('next/link', () => ({
  default: ({ href, children, ...resto }: { href: string; children: React.ReactNode }) => (
    <a href={href} {...resto}>{children}</a>
  ),
}))

type Rol = keyof typeof permisosPorRol

function abrirComo(rol: Rol, permiso: string) {
  useAuthStore.setState({
    isAuthenticated: true,
    usuario: { id: 1, email: `prueba.${rol}@gadpe.test`, roles: [rol], permisos: permisosPorRol[rol] },
  })

  return render(
    <MantineProvider theme={theme}>
      <RequierePermiso permiso={permiso} volverA={ROUTES.TIC.EQUIPOS}>
        <p>Formulario de alta</p>
      </RequierePermiso>
    </MantineProvider>,
  )
}

describe('RequierePermiso', () => {
  it('pinta la pantalla a quien tiene el permiso', () => {
    abrirComo('tecnico-dtic', 'gestionar-inventario-ti')

    expect(screen.getByText('Formulario de alta')).toBeTruthy()
  })

  it('a quien solo consulta le dice que no tiene acceso y lo devuelve al listado', () => {
    abrirComo('auditor', 'gestionar-inventario-ti')

    expect(screen.queryByText('Formulario de alta')).toBeNull()
    expect(screen.getByText('No tiene acceso a esta pantalla')).toBeTruthy()
    expect(screen.getByRole('link', { name: 'Volver al listado' }).getAttribute('href')).toBe(ROUTES.TIC.EQUIPOS)
  })

  it('el auditor sigue las lecturas de los comunicados, pero no redacta', () => {
    abrirComo('auditor', 'redactar-comunicados')

    expect(screen.queryByText('Formulario de alta')).toBeNull()
  })
})
