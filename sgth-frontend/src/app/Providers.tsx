'use client'

import { MantineProvider, localStorageColorSchemeManager } from '@mantine/core'
import { DatesProvider, type DatesProviderSettings } from '@mantine/dates'
import { Notifications } from '@mantine/notifications'
import { ModalsProvider } from '@mantine/modals'
import { QueryClientProvider } from '@tanstack/react-query'
import { ReactQueryDevtools } from '@tanstack/react-query-devtools'
import 'dayjs/locale/es'
import { theme } from '@/config/mantine.theme'
import { queryClient } from '@/lib/queryClient'

const colorSchemeManager = localStorageColorSchemeManager({
  key: 'sgth-color-scheme',
})

/**
 * Calendarios en español. Sin esto los selectores de fecha salen en inglés
 * ("August 2026", "Mo Tu We"), que es como estuvieron hasta ahora.
 *
 * `dayjs/locale/es` se importa aquí porque el idioma se registra en la
 * instancia global de dayjs: basta una vez en toda la aplicación.
 *
 * La semana arranca en lunes y el fin de semana es sábado y domingo, que es
 * como se leen los calendarios en Ecuador y como se cuentan los días hábiles
 * de permisos y vacaciones.
 */
const AJUSTES_FECHAS: DatesProviderSettings = {
  locale: 'es',
  firstDayOfWeek: 1,
  weekendDays: [0, 6],
  consistentWeeks: true,
}

export function Providers({ children }: { children: React.ReactNode }) {
  return (
    <QueryClientProvider client={queryClient}>
      <MantineProvider
        theme={theme}
        colorSchemeManager={colorSchemeManager}
        defaultColorScheme="light"
      >
        <DatesProvider settings={AJUSTES_FECHAS}>
          <Notifications position="top-right" />
          <ModalsProvider>{children}</ModalsProvider>
        </DatesProvider>
      </MantineProvider>
      {process.env.NODE_ENV === 'development' && (
        <ReactQueryDevtools initialIsOpen={false} />
      )}
    </QueryClientProvider>
  )
}
