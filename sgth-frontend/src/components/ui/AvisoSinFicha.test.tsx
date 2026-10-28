// @vitest-environment jsdom
import { describe, expect, it } from 'vitest'
import { render, renderHook, screen } from '@testing-library/react'
import { MantineProvider } from '@mantine/core'
import { theme } from '@/config/mantine.theme'
import { useAuthStore } from '@/store/auth.store'
import { useTieneFicha } from '@/hooks/useTieneFicha'
import { AvisoSinFicha } from './AvisoSinFicha'

/*
| Lo que ve en el Portal una cuenta sin ficha de servidor
| (docs/plan-roles-y-accesos.md, PR 15): por qué no hay nada y a quién
| pedírselo, igual en todas las pantallas.
*/

const pintar = (ui: React.ReactNode) => render(<MantineProvider theme={theme}>{ui}</MantineProvider>)

describe('AvisoSinFicha', () => {
  it('dice la causa, lo de la pantalla y a quién pedírselo', () => {
    pintar(<AvisoSinFicha motivo="Los préstamos de equipos se piden a nombre del servidor." />)

    expect(screen.getByText('Su usuario no está vinculado a un servidor')).toBeTruthy()
    expect(screen.getByText(/se piden a nombre del servidor\. Pídale a Talento Humano/)).toBeTruthy()
  })

  it('compacto, va como aviso sobre una pantalla que sigue sirviendo', () => {
    pintar(<AvisoSinFicha compacto motivo="Puede consultar la disponibilidad." />)

    expect(screen.getByRole('alert')).toBeTruthy()
  })
})

describe('useTieneFicha', () => {
  it('es la ficha de la sesión, no los roles', () => {
    useAuthStore.setState({
      isAuthenticated: true,
      usuario: { id: 1, email: 'tic@gadpe.test', roles: ['tecnico-dtic'], permisos: [], servidor_id: null },
    })
    expect(renderHook(() => useTieneFicha()).result.current).toBe(false)

    useAuthStore.setState({
      isAuthenticated: true,
      usuario: { id: 2, email: 'ana@gadpe.test', roles: ['servidor'], permisos: [], servidor_id: 7 },
    })
    expect(renderHook(() => useTieneFicha()).result.current).toBe(true)
  })
})
