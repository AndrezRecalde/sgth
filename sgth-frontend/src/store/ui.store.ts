import { create } from 'zustand'
import { persist } from 'zustand/middleware'

interface UiState {
  /** Sidebar plegado a solo iconos en escritorio. Se recuerda entre sesiones. */
  navbarCollapsed: boolean
  toggleNavbar: () => void
  setNavbarCollapsed: (v: boolean) => void
}

export const useUiStore = create<UiState>()(
  persist(
    (set) => ({
      navbarCollapsed: false,
      toggleNavbar: () =>
        set((state) => ({ navbarCollapsed: !state.navbarCollapsed })),
      setNavbarCollapsed: (v) => set({ navbarCollapsed: v }),
    }),
    { name: 'sgth-ui' },
  ),
)
