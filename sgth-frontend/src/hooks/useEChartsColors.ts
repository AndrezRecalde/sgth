'use client'

import { useMemo } from 'react'
import { useComputedColorScheme } from '@mantine/core'

/** Lee una variable CSS del documento y devuelve su valor concreto. */
function leerVar(nombre: string, respaldo: string): string {
  if (typeof window === 'undefined') return respaldo
  const valor = getComputedStyle(document.documentElement)
    .getPropertyValue(nombre)
    .trim()
  return valor || respaldo
}

export interface EChartsColors {
  esquema: 'light' | 'dark'
  acento: string
  acentoTenue: string
  superficie: string
  superficieHundida: string
  borde: string
  texto: string
  textoTenue: string
  /** Paleta categórica, para series con varias categorías. */
  serie: string[]
}

/**
 * Colores concretos para ECharts.
 *
 * ECharts pinta sobre canvas: no entiende `var(--sgth-accent)`, necesita un
 * color resuelto. Esta es **la única excepción** a la regla de no manejar
 * colores literales fuera de `design.tokens.ts`, y se canaliza por aquí para
 * que las gráficas sigan al tema y al modo oscuro en vez de fijar su propia
 * paleta.
 *
 *   const c = useEChartsColors()
 *   const option = {
 *     backgroundColor: 'transparent',
 *     textStyle: { color: c.texto },
 *     series: [{ itemStyle: { color: c.acento } }],
 *   }
 *
 * Se recalcula al cambiar el esquema de color, así que la gráfica se repinta
 * sola al activar el modo oscuro.
 */
export function useEChartsColors(): EChartsColors {
  const esquema = useComputedColorScheme('light')

  return useMemo(
    () => ({
      esquema,
      acento: leerVar('--sgth-accent', '#059669'),
      acentoTenue: leerVar('--sgth-accent-light', '#D1FAE5'),
      superficie: leerVar('--sgth-surface', '#FFFFFF'),
      superficieHundida: leerVar('--sgth-surface-sunken', '#F1F5F9'),
      borde: leerVar('--sgth-border', '#E2E8F0'),
      texto: leerVar('--sgth-text', '#0F172A'),
      textoTenue: leerVar('--sgth-text-muted', '#64748B'),
      serie: [
        leerVar('--mantine-color-emerald-6', '#059669'),
        leerVar('--mantine-color-ocean-6', '#2563EB'),
        leerVar('--mantine-color-amethyst-6', '#7C3AED'),
        leerVar('--mantine-color-amber-6', '#D97706'),
        leerVar('--mantine-color-red-6', '#E03131'),
        leerVar('--mantine-color-slate-5', '#64748B'),
      ],
    }),
    [esquema],
  )
}
