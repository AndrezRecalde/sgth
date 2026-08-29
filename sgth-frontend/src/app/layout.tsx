/**
 * Hojas de estilo de la aplicación — punto ÚNICO de importación.
 * El orden importa: primero los paquetes, luego nuestros tokens, y al final
 * `globals.css`, que debe poder ganar sobre lo anterior.
 *
 * Ningún componente vuelve a importar un `styles.css` de paquete.
 */
import '@mantine/core/styles.css'
import '@mantine/dates/styles.css'
import '@mantine/dropzone/styles.css'
import '@mantine/tiptap/styles.css'
import '@mantine/notifications/styles.css'
import 'mantine-datatable/styles.css'

import '@/styles/tokens.css'
import '@/styles/globals.css'

import { ColorSchemeScript, mantineHtmlProps } from '@mantine/core'
import type { Metadata, Viewport } from 'next'
import { fontVariables } from '@/config/mantine.theme'
import { Providers } from './Providers'

export const metadata: Metadata = {
  title: {
    default: 'GADPE — Sistema de Gestión de Talento Humano',
    // Cada página exporta SOLO el nombre de su módulo: 'Nómina' → 'GADPE — Nómina'.
    template: 'GADPE — %s',
  },
  description:
    'Sistema Integral de Gestión de Talento Humano — GAD Provincial de Esmeraldas',
  applicationName: 'SGTH',
  robots: { index: false, follow: false },
}

export const viewport: Viewport = {
  width: 'device-width',
  initialScale: 1,
  // El color de la barra del navegador acompaña al esquema activo.
  themeColor: [
    { media: '(prefers-color-scheme: light)', color: '#F8FAFC' },
    { media: '(prefers-color-scheme: dark)', color: '#0C0F14' },
  ],
}

export default function RootLayout({
  children,
}: {
  children: React.ReactNode
}) {
  return (
    <html lang="es" {...mantineHtmlProps}>
      <head>
        <ColorSchemeScript defaultColorScheme="light" />
      </head>
      <body className={fontVariables}>
        <Providers>{children}</Providers>
      </body>
    </html>
  )
}
