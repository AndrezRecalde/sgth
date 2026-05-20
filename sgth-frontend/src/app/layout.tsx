import '@mantine/core/styles.css';
import '@mantine/notifications/styles.css';

import { ColorSchemeScript, MantineProvider, localStorageColorSchemeManager } from '@mantine/core';
import { Notifications } from '@mantine/notifications';
import { ModalsProvider } from '@mantine/modals';
import { QueryClientProvider } from '@tanstack/react-query';
import { ReactQueryDevtools } from '@tanstack/react-query-devtools';
import { theme } from '@/config/mantine.theme';
import { queryClient } from '@/lib/queryClient';
import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: 'SGTH | GAD Esmeraldas',
  description: 'Sistema de Gestión de Talento Humano - GAD Provincial de Esmeraldas',
};

const colorSchemeManager = localStorageColorSchemeManager({
  key: 'sgth-color-scheme',
});

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="es">
      <head>
        <ColorSchemeScript defaultColorScheme="light" />
      </head>
      <body>
        <QueryClientProvider client={queryClient}>
          <MantineProvider theme={theme} colorSchemeManager={colorSchemeManager} defaultColorScheme="light">
            <Notifications position="top-right" />
            <ModalsProvider>
              {children}
            </ModalsProvider>
          </MantineProvider>
          {process.env.NODE_ENV === 'development' && <ReactQueryDevtools initialIsOpen={false} />}
        </QueryClientProvider>
      </body>
    </html>
  );
}
