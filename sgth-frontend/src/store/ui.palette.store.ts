import { create } from 'zustand'

interface PaletteState {
  opened: boolean
  open: () => void
  close: () => void
  toggle: () => void
}

/**
 * Estado del buscador de pantallas (Ctrl+K).
 *
 * Vive en un store y no en el AppShell porque lo abren dos sitios distintos:
 * el atajo de teclado global y el botón de la barra superior. Pasarlo por
 * props obligaría a atravesar el shell entero.
 */
export const usePaletteStore = create<PaletteState>((set) => ({
  opened: false,
  open: () => set({ opened: true }),
  close: () => set({ opened: false }),
  toggle: () => set((s) => ({ opened: !s.opened })),
}))
