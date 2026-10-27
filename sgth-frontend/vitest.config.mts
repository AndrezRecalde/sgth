import { fileURLToPath } from 'node:url'
import { defineConfig } from 'vitest/config'

/**
 * Las pruebas de unidad del frontend (`npm test`).
 *
 * Corren en Node por defecto: el menú y sus ayudantes son funciones puras. Un
 * archivo que monta componentes pide el DOM con `// @vitest-environment jsdom`
 * en su primera línea, y `src/test/setup.ts` le pone lo que Mantine espera del
 * navegador.
 */
export default defineConfig({
  resolve: {
    alias: { '@': fileURLToPath(new URL('./src', import.meta.url)) },
  },
  test: {
    include: ['src/**/*.test.{ts,tsx}'],
    setupFiles: ['./src/test/setup.ts'],
  },
})
