import { createTheme, rem } from '@mantine/core'
import { Poppins, Inter } from 'next/font/google'
import {
  EMERALD, OCEAN, AMETHYST, AMBER, SLATE, NIGHT,
  FONT_SIZES, LINE_HEIGHTS, HEADING_SIZES,
  SPACING, RADIUS, SHADOWS,
} from './design.tokens'

/**
 * Tipografía dual:
 *  - Poppins  → títulos. Aporta el carácter de marca.
 *  - Inter    → UI y datos. Legible a 12-13px y con cifras tabulares,
 *               que es lo que necesitan las tablas de nómina, cédulas y montos.
 * `display: 'swap'` evita el bloqueo de render mientras carga la fuente.
 */
const poppins = Poppins({
  subsets: ['latin'],
  weight: ['500', '600', '700'],
  display: 'swap',
  variable: '--font-poppins',
})

const inter = Inter({
  subsets: ['latin'],
  weight: ['400', '500', '600', '700'],
  display: 'swap',
  variable: '--font-inter',
})

/** Se aplica en `<body>` para exponer ambas familias como variables CSS. */
export const fontVariables = `${inter.variable} ${poppins.variable}`

export const theme = createTheme({
  primaryColor: 'emerald',
  // Shade 6 en claro; 8 en oscuro, donde el 6 vibra demasiado sobre fondo negro.
  primaryShade: { light: 6, dark: 8 },

  fontFamily: `${inter.style.fontFamily}, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif`,
  fontFamilyMonospace: "'JetBrains Mono', ui-monospace, SFMono-Regular, Menlo, monospace",
  headings: {
    fontFamily: `${poppins.style.fontFamily}, ${inter.style.fontFamily}, sans-serif`,
    textWrap: 'balance',
    sizes: HEADING_SIZES,
  },

  fontSizes: FONT_SIZES,
  lineHeights: LINE_HEIGHTS,
  spacing: SPACING,
  radius: RADIUS,
  shadows: SHADOWS,
  defaultRadius: 'md',

  colors: {
    emerald: [...EMERALD],
    ocean: [...OCEAN],
    amethyst: [...AMETHYST],
    amber: [...AMBER],
    slate: [...SLATE],
    // Sustituye la escala `dark` de Mantine para dar tres superficies
    // distinguibles en modo oscuro en vez de un gris azulado plano.
    dark: [...NIGHT],
  },

  // El anillo de foco solo aparece navegando con teclado.
  focusRing: 'auto',
  cursorType: 'pointer',
  respectReducedMotion: true,

  components: {
    // ── Formulario ───────────────────────────────────────────
    // El alto y el radio de TODOS los inputs se define aquí una sola vez.
    // Los componentes nunca fijan `size` ni `radius` en cada input.
    TextInput:      { defaultProps: { size: 'md' } },
    PasswordInput:  { defaultProps: { size: 'md' } },
    NumberInput:    { defaultProps: { size: 'md' } },
    Textarea:       { defaultProps: { size: 'md' } },
    Select:         { defaultProps: { size: 'md', checkIconPosition: 'right' } },
    MultiSelect:    { defaultProps: { size: 'md', checkIconPosition: 'right' } },
    Autocomplete:   { defaultProps: { size: 'md' } },
    DateInput:      { defaultProps: { size: 'md' } },
    DatePickerInput:{ defaultProps: { size: 'md' } },
    FileInput:      { defaultProps: { size: 'md' } },

    Button: {
      defaultProps: { radius: 'md' },
    },
    ActionIcon: {
      defaultProps: { variant: 'subtle', color: 'gray' },
    },

    // ── Superficies ──────────────────────────────────────────
    // Borde sí, sombra no: la jerarquía dentro de la página es por borde.
    Card:  { defaultProps: { radius: 'lg', withBorder: true, shadow: 'none', padding: 'lg' } },
    Paper: { defaultProps: { radius: 'lg', shadow: 'none' } },

    // ── Capas flotantes ──────────────────────────────────────
    // Aquí sí hay sombra: están separadas físicamente del contenido.
    Modal: {
      defaultProps: { radius: 'xl', centered: true, shadow: 'lg', overlayProps: { blur: 2 } },
    },
    Drawer:   { defaultProps: { shadow: 'lg', position: 'right' } },
    Menu:     { defaultProps: { radius: 'md', shadow: 'md', withinPortal: true } },
    Popover:  { defaultProps: { radius: 'md', shadow: 'md', withinPortal: true } },
    Tooltip:  { defaultProps: { radius: 'sm', withArrow: true, openDelay: 300 } },

    // ── Señalización ─────────────────────────────────────────
    Badge:      { defaultProps: { radius: 'sm', variant: 'light' } },
    ThemeIcon:  { defaultProps: { variant: 'light', radius: 'md' } },
    Divider:    { defaultProps: { color: 'var(--sgth-border)' } },

    Tabs:       { defaultProps: { keepMounted: false } },
    Anchor:     { defaultProps: { underline: 'hover' } },
    Loader:     { defaultProps: { type: 'dots' } },
    Skeleton:   { defaultProps: { radius: 'md' } },
  },

  other: {
    /** Alto real de los inputs `size="md"`, para alinear controles a mano. */
    inputHeight: rem(42),
  },
})
