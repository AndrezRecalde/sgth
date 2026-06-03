import '@mantine/core/styles.css';
import '@mantine/notifications/styles.css';
import 'mantine-datatable/styles.css';

import { ColorSchemeScript } from '@mantine/core';
import type { Metadata } from 'next';
import { Poppins } from 'next/font/google';
import { Providers } from './Providers';

const poppins = Poppins({
  subsets: ['latin'],
  weight: ['300', '400', '500', '600', '700'],
  variable: '--font-poppins',
});

export const metadata: Metadata = {
  title: {
    default:  'GADPE — Sistema de Gestión de Talento Humano',
    template: 'GADPE — %s',
  },
  description: 'Sistema Integral de Gestión de Talento Humano — GAD Provincial de Esmeraldas',
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="es" suppressHydrationWarning>
      <head>
        <ColorSchemeScript defaultColorScheme="light" />
      </head>
      <body className={poppins.variable}>
        <Providers>
          {children}
        </Providers>
      </body>
    </html>
  );
}
