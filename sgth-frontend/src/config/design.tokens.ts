/**
 * SGTH — Tokens de diseño (fuente única de verdad)
 * ------------------------------------------------
 * Este archivo NO importa nada de Mantine. Define las escalas crudas.
 * `mantine.theme.ts` las consume y las publica como variables CSS.
 *
 * Regla: ningún componente importa este archivo. Los componentes consumen
 * siempre las variables CSS (`var(--mantine-color-emerald-6)`, `var(--sgth-accent-6)`).
 */

// ── Escalas de color ───────────────────────────────────────────
// Cada escala tiene 10 pasos (0 = más claro, 9 = más oscuro).
// El paso 6 es el "filled" en modo claro; el 8 en modo oscuro.

/** Institucional GADPE — primario global y acento del subsistema SGTH. */
export const EMERALD = [
  '#ECFDF5', '#D1FAE5', '#A7F3D0', '#6EE7B7', '#34D399',
  '#10B981', '#059669', '#047857', '#065F46', '#064E3B',
] as const

/** Acento del subsistema Dispensario Médico. */
export const OCEAN = [
  '#EFF6FF', '#DBEAFE', '#BFDBFE', '#93C5FD', '#60A5FA',
  '#3B82F6', '#2563EB', '#1D4ED8', '#1E40AF', '#1E3A8A',
] as const

/** Acento del subsistema Portal del Servidor. */
export const AMETHYST = [
  '#F5F3FF', '#EDE9FE', '#DDD6FE', '#C4B5FD', '#A78BFA',
  '#8B5CF6', '#7C3AED', '#6D28D9', '#5B21B6', '#4C1D95',
] as const

/** Advertencia — el único ámbar del sistema. */
export const AMBER = [
  '#FFFBEB', '#FEF3C7', '#FDE68A', '#FCD34D', '#FBBF24',
  '#F59E0B', '#D97706', '#B45309', '#92400E', '#78350F',
] as const

/** Neutral cálido-frío para superficies, bordes y texto. */
export const SLATE = [
  '#F8FAFC', '#F1F5F9', '#E2E8F0', '#CBD5E1', '#94A3B8',
  '#64748B', '#475569', '#334155', '#1E293B', '#0F172A',
] as const

/**
 * Escala de modo oscuro. Sustituye a la `dark` por defecto de Mantine, que es
 * demasiado azulada y aplana las tres superficies (canvas/surface/raised).
 * Índices que Mantine usa por convención:
 *   [0-3] texto  ·  [4] borde  ·  [5] raised  ·  [6] surface  ·  [7] canvas  ·  [8-9] fondos profundos
 */
export const NIGHT = [
  '#C1C7CF', '#A9B1BC', '#8B95A3', '#5F6875', '#3A424E',
  '#2A313B', '#212730', '#181D24', '#12161C', '#0C0F14',
] as const

// ── Semánticos ─────────────────────────────────────────────────
// Nombres de color de Mantine, NUNCA hex. Se consumen como
// `color="var(--sgth-color-danger)"` o vía el mapa SEMANTIC_COLOR.

export const SEMANTIC_COLOR = {
  success: 'emerald',
  warning: 'amber',
  danger:  'red',
  info:    'ocean',
  neutral: 'slate',
} as const

export type SemanticTone = keyof typeof SEMANTIC_COLOR

// ── Tipografía ─────────────────────────────────────────────────
// Poppins para títulos (carácter de marca), Inter para UI y datos
// (legible a 12-13px y con cifras tabulares para tablas y montos).

export const FONT_SIZES = {
  xs: '0.75rem',   // 12px — captions, badges, labels de tabla
  sm: '0.8125rem', // 13px — texto denso, celdas
  md: '0.875rem',  // 14px — cuerpo por defecto
  lg: '1rem',      // 16px — texto destacado
  xl: '1.125rem',  // 18px — subtítulos
} as const

export const LINE_HEIGHTS = {
  xs: '1.4', sm: '1.45', md: '1.55', lg: '1.55', xl: '1.5',
} as const

export const HEADING_SIZES = {
  h1: { fontSize: '1.75rem', lineHeight: '1.25', fontWeight: '700' }, // 28px — título de página
  h2: { fontSize: '1.375rem', lineHeight: '1.3', fontWeight: '650' }, // 22px — sección
  h3: { fontSize: '1.125rem', lineHeight: '1.35', fontWeight: '600' }, // 18px — subsección
  h4: { fontSize: '1rem', lineHeight: '1.4', fontWeight: '600' },      // 16px — card
  h5: { fontSize: '0.875rem', lineHeight: '1.45', fontWeight: '600' }, // 14px
  h6: { fontSize: '0.75rem', lineHeight: '1.5', fontWeight: '600' },   // 12px
} as const

// ── Espaciado ──────────────────────────────────────────────────
export const SPACING = {
  xs: '0.25rem', // 4
  sm: '0.5rem',  // 8
  md: '1rem',    // 16
  lg: '1.5rem',  // 24
  xl: '2rem',    // 32
} as const

// ── Radios ─────────────────────────────────────────────────────
export const RADIUS = {
  xs: '0.25rem',  // 4  — badges, chips
  sm: '0.375rem', // 6  — inputs pequeños
  md: '0.5rem',   // 8  — botones, inputs (defecto)
  lg: '0.75rem',  // 12 — cards, paneles
  xl: '1rem',     // 16 — modales, drawers
} as const

// ── Elevación ──────────────────────────────────────────────────
// La jerarquía en la página se expresa con BORDES, no con sombras.
// Las sombras se reservan para capas flotantes (menús, modales, popovers),
// donde sí hay separación física real respecto al contenido.
export const SHADOWS = {
  none: 'none',
  xs: '0 1px 2px rgba(15, 23, 42, 0.04)',
  sm: '0 1px 3px rgba(15, 23, 42, 0.06), 0 1px 2px rgba(15, 23, 42, 0.04)',
  md: '0 4px 12px rgba(15, 23, 42, 0.08), 0 1px 3px rgba(15, 23, 42, 0.04)',
  lg: '0 12px 28px rgba(15, 23, 42, 0.12), 0 2px 6px rgba(15, 23, 42, 0.05)',
  xl: '0 24px 48px rgba(15, 23, 42, 0.16), 0 4px 12px rgba(15, 23, 42, 0.06)',
} as const

// ── Medidas del shell ──────────────────────────────────────────
export const LAYOUT = {
  /** Alto de la barra superior. */
  headerHeight: 56,
  /** Ancho del sidebar expandido. */
  navbarWidth: 264,
  /** Ancho del sidebar colapsado (solo iconos). */
  navbarWidthCollapsed: 64,
  /** Ancho máximo del contenido de una página. */
  contentMaxWidth: 1600,
} as const

// ── Movimiento ─────────────────────────────────────────────────
export const MOTION = {
  fast: '120ms',
  base: '180ms',
  slow: '280ms',
  easing: 'cubic-bezier(0.4, 0, 0.2, 1)',
} as const
