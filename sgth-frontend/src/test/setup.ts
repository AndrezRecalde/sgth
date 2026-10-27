import { afterEach, vi } from 'vitest'

/*
| Arranque de cada archivo de pruebas.
|
| `next/font/google` solo existe cuando compila Next: fuera de él, el tema de
| Mantine reventaba al cargar sus dos fuentes. Aquí cada fuente es su nombre.
|
| En los que corren sobre jsdom: lo que Mantine pide al navegador y jsdom no
| trae (`matchMedia`, `ResizeObserver`), y el desmontaje entre pruebas, que
| Testing Library solo hace solo cuando el ejecutor expone `afterEach` como
| global, y aquí no se expone.
*/
vi.mock('next/font/google', () => {
  const fuente = (familia: string) => () => ({
    className: '',
    variable:  '',
    style:     { fontFamily: familia },
  })

  return { Inter: fuente('Inter'), Poppins: fuente('Poppins') }
})

if (typeof window !== 'undefined') {
  const { cleanup } = await import('@testing-library/react')
  afterEach(cleanup)

  window.matchMedia ??= (query: string) => ({
    matches:             false,
    media:               query,
    onchange:            null,
    addListener:         () => {},
    removeListener:      () => {},
    addEventListener:    () => {},
    removeEventListener: () => {},
    dispatchEvent:       () => false,
  })

  window.ResizeObserver ??= class {
    observe()    {}
    unobserve()  {}
    disconnect() {}
  }
}
