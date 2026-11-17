'use client'

import { Select } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { Toolbar } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useTodasUnidades } from '@/features/estructura/hooks/useUnidades'
import { fromDateValueOrNull, hoyIso, toDateValue } from '@/lib/fecha'
import type { RegimenServidor } from '@/lib/regimen'
import type { FilaMasaSalarial, TipoNombramiento } from '@/types/api'
import { esRegimen, esTipoNombramiento } from '../utils/masaSalarial'

export interface FiltrosMasa {
  corte:    string
  regimen:  RegimenServidor | null
  tipo:     TipoNombramiento | null
  unidadId: number | null
}

/** Abre en hoy y en toda la institución. */
export const FILTROS_MASA_INICIALES = (): FiltrosMasa => ({ corte: hoyIso(), regimen: null, tipo: null, unidadId: null })

interface Props {
  filtros:   FiltrosMasa
  onCambiar: (cambio: Partial<FiltrosMasa>) => void
  /** Los casos los da la respuesta, con sus etiquetas, también los que están en cero. */
  regimenes: FilaMasaSalarial[]
  tipos:     FilaMasaSalarial[]
}

/** La fecha de corte y los filtros: régimen, tipo de nombramiento y unidad con lo que cuelga de ella. */
export function MasaSalarialFiltros({ filtros, onCambiar, regimenes, tipos }: Props) {
  const contained = useContainedInput('sm')
  // La lista pública: Financiero y el auditor no tienen `ver-estructura`.
  const { data: unidades = [] } = useTodasUnidades()
  const opciones = (filas: FilaMasaSalarial[]) => filas.map((f) => ({ value: f.clave, label: f.etiqueta }))

  return (
    <Toolbar>
      <DatePickerInput
        label="Fecha de corte"
        valueFormat="DD/MM/YYYY"
        maxDate={new Date()}
        {...contained}
        value={toDateValue(filtros.corte)}
        onChange={(d) => onCambiar({ corte: fromDateValueOrNull(d ?? null) ?? hoyIso() })}
        style={{ minWidth: 150 }}
      />
      <Select
        label="Régimen"
        placeholder="Todos"
        data={opciones(regimenes)}
        clearable
        {...contained}
        value={filtros.regimen}
        onChange={(v) => onCambiar({ regimen: esRegimen(v) ? v : null })}
        style={{ minWidth: 190 }}
      />
      <Select
        label="Tipo de nombramiento"
        placeholder="Todos"
        data={opciones(tipos)}
        clearable
        {...contained}
        value={filtros.tipo}
        onChange={(v) => onCambiar({ tipo: esTipoNombramiento(v) ? v : null })}
        style={{ minWidth: 240 }}
      />
      <Select
        label="Unidad administrativa"
        placeholder="Toda la institución"
        data={unidades.map((u) => ({ value: String(u.id), label: u.nombre ?? `Unidad ${u.id}` }))}
        searchable
        clearable
        {...contained}
        value={filtros.unidadId != null ? String(filtros.unidadId) : null}
        onChange={(v) => onCambiar({ unidadId: v ? Number(v) : null })}
        style={{ minWidth: 260 }}
      />
    </Toolbar>
  )
}
