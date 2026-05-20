import '@mantine/core/styles.css';
import '@mantine/notifications/styles.css';

import { ColorSchemeScript } from '@mantine/core';
import type { Metadata } from 'next';
import { Providers } from './Providers';

export const metadata: Metadata = {
  title: 'SGTH | GAD Esmeraldas',
  description: 'Sistema de Gestión de Talento Humano - GAD Provincial de Esmeraldas',
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="es">
      <head>
        <ColorSchemeScript defaultColorScheme="light" />
      </head>
      <body>
        <Providers>
          {children}
        </Providers>
      </body>
    </html>
  );
}
