/*
| Colores escritos a mano en las hojas CSS de `src/`. ESLint no lee CSS, así
| que esto completa la regla de colores de `eslint.config.mjs` (regla 03):
|
|   - hexadecimales y rgb()/hsl()/oklch(): se ven bien en un esquema y se rompen
|     en el otro;
|   - colores con nombre (white, gray, red…);
|   - escalas de Mantine fuera de la paleta (`--mantine-color-gray-3`…).
|
| En CSS se usan los tokens --sgth-* o las escalas de la paleta. El gris fijo
| de las raíces del odontograma salía casi blanco en modo oscuro.
|
| Se ejecuta con `npm run lint`.
*/
import { readdirSync, readFileSync } from 'node:fs'
import { join, relative } from 'node:path'

const RAIZ = new URL('../src/', import.meta.url).pathname.replace(/^\/([A-Za-z]:)/, '$1')

const REGLAS = [
  {
    patron: /#[0-9a-fA-F]{3,8}\b/,
    motivo: 'hexadecimal: use un token --sgth-* o una escala de la paleta',
  },
  {
    patron: /\b(rgba?|hsla?|oklch|oklab|lab|lch|hwb)\(/,
    motivo: 'color literal: use un token --sgth-* o una escala de la paleta',
  },
  {
    patron: /(?<![-\w])(white|black|gray|grey|silver|red|blue|green|orange|yellow|purple|pink|teal|cyan|violet)(?![-\w])/,
    motivo: 'color con nombre: use var(--mantine-color-white) o un token --sgth-*',
  },
  {
    patron: /--mantine-color-(gray|blue|orange|teal|yellow|violet|grape|cyan|green|indigo|pink|lime)-/,
    motivo: 'escala fuera de la paleta: use primario, emerald, ocean, amethyst, amber, red o slate, o un token --sgth-*',
  },
]

function hojas(dir) {
  return readdirSync(dir, { withFileTypes: true }).flatMap((e) => {
    const ruta = join(dir, e.name)
    if (e.isDirectory()) return hojas(ruta)
    return e.name.endsWith('.css') ? [ruta] : []
  })
}

const errores = []
for (const archivo of hojas(RAIZ)) {
  // Los comentarios se vacían conservando los saltos de línea, para no mover
  // los números de línea.
  const texto = readFileSync(archivo, 'utf8').replace(/\/\*[\s\S]*?\*\//g, (c) => c.replace(/[^\n]/g, ' '))
  const lineas = texto.split('\n')
  lineas.forEach((linea, i) => {
    if (!linea.trim() || linea.trimEnd().endsWith('{')) return
    const declaracion = /^\s*(--?[\w-]+|[a-z-]+)\s*:\s*(.+?);?\s*$/.exec(linea)
    for (const { patron, motivo } of REGLAS) {
      // Los colores con nombre, solo en el valor de una declaración: `white-space`
      // es una propiedad. El resto, también en la continuación de un valor
      // partido en varias líneas (un color-mix()).
      const conNombre = patron === REGLAS[2].patron
      const revisado = conNombre ? (declaracion?.[2] ?? '') : linea
      if (patron.test(revisado)) {
        errores.push(`${relative(join(RAIZ, '..'), archivo).replace(/\\/g, '/')}:${i + 1}  ${linea.trim()}\n    ${motivo} (regla 03)`)
      }
    }
  })
}

if (errores.length) {
  console.error(errores.join('\n'))
  console.error(`\n✖ ${errores.length} color(es) escritos a mano en CSS`)
  process.exit(1)
}
