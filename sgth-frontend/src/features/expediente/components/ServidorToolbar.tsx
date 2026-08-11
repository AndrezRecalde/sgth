'use client'

import { useState, useEffect } from 'react'
import { useDebouncedValue } from '@mantine/hooks'
import { TextInput, Select, Group, Stack } from '@mantine/core'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { useTodasUnidades } from '@/features/estructura/hooks/useUnidades'
import type { UnidadConRelaciones } from '@/types/api'

const CONTRATO_ESTADO_OPTIONS = [
  { value: 'vigente',   label: 'Vigente' },
  { value: 'terminado', label: 'Terminado' },
  { value: 'cancelado', label: 'Cancelado' },
]

const EN_FUNCIONES_OPTIONS = [
  { value: 'si', label: 'En funciones' },
  { value: 'no', label: 'Inactivos' },
]

const VINCULO_OPTIONS = [
  { value: 'con', label: 'Con vínculo registrado' },
  { value: 'sin', label: 'Pendientes de vinculación' },
]

const TIPO_NOMBRAMIENTO_OPTIONS = [
  { value: 'nombramiento_permanente',      label: 'Nombramiento Permanente' },
  { value: 'nombramiento_provisional',     label: 'Nombramiento Provisional' },
  { value: 'servicios_ocasionales',        label: 'Servicios Ocasionales' },
  { value: 'libre_nombramiento_remocion',  label: 'Libre Nombramiento y Remoción' },
  { value: 'codigo_trabajo',               label: 'Código del Trabajo' },
  { value: 'servicios_profesionales',      label: 'Servicios Profesionales' },
  { value: 'eleccion_popular',             label: 'Elección Popular' },
]

const ANIO_ACTUAL = new Date().getFullYear()
const ANIO_OPTIONS = Array.from({ length: ANIO_ACTUAL - 1979 }, (_, i) => {
  const anio = ANIO_ACTUAL - i
  return { value: String(anio), label: String(anio) }
})

interface Props {
  onSearch:               (v: string) => void
  onContratoEstadoChange: (v: string | null) => void
  onEnFuncionesChange?:   (v: boolean | null) => void
  onUnidadChange?:        (v: number | null) => void
  onTipoNombramientoChange?: (v: string | null) => void
  onAnioIngresoChange?:   (v: number | null) => void
  /** Fichas sin vínculo laboral registrado. */
  onPendienteVinculacionChange?: (v: boolean | null) => void
  /** Se controla desde fuera para que el contador pueda activar el filtro. */
  pendienteVinculacion?: boolean | null
}

export function ServidorToolbar({
  onSearch, onContratoEstadoChange, onEnFuncionesChange,
  onUnidadChange, onTipoNombramientoChange, onAnioIngresoChange,
  onPendienteVinculacionChange, pendienteVinculacion,
}: Props) {
  const contained    = useContainedInput()
  const { isMobile } = useMobileBreakpoint()

  const [localSearch, setLocalSearch] = useState('')
  const [debounced] = useDebouncedValue(localSearch, 400)

  const { data: unidadesRaw } = useTodasUnidades({ nivel: 2 })
  const unidades = (unidadesRaw ?? []) as UnidadConRelaciones[]
  const unidadOptions = unidades.map((u) => ({ value: String(u.id), label: u.nombre ?? `Unidad ${u.id}` }))

  useEffect(() => {
    onSearch(debounced)
  }, [debounced, onSearch])

  const fields = (
    <>
      <TextInput
        label="Buscar servidor"
        placeholder="Nombre o cédula"
        value={localSearch}
        onChange={(e) => setLocalSearch(e.currentTarget.value)}
        {...contained}
        style={{ flex: 1 }}
      />
      {onEnFuncionesChange && (
        <Select
          label="Estado del servidor"
          placeholder="Todos"
          data={EN_FUNCIONES_OPTIONS}
          onChange={(v) => onEnFuncionesChange(v === null ? null : v === 'si')}
          clearable
          {...contained}
          style={{ minWidth: 180 }}
        />
      )}
      {onPendienteVinculacionChange && (
        <Select
          label="Vínculo laboral"
          placeholder="Todos"
          data={VINCULO_OPTIONS}
          value={pendienteVinculacion === null || pendienteVinculacion === undefined
            ? null
            : (pendienteVinculacion ? 'sin' : 'con')}
          onChange={(v) => onPendienteVinculacionChange(v === null ? null : v === 'sin')}
          clearable
          {...contained}
          style={{ minWidth: 200 }}
        />
      )}
      <Select
        label="Estado contrato"
        placeholder="Todos"
        data={CONTRATO_ESTADO_OPTIONS}
        onChange={onContratoEstadoChange}
        clearable
        {...contained}
        style={{ minWidth: 180 }}
      />
      {onUnidadChange && (
        <Select
          label="Unidad administrativa"
          placeholder="Todas"
          data={unidadOptions}
          searchable
          clearable
          onChange={(v) => onUnidadChange(v ? Number(v) : null)}
          {...contained}
          style={{ minWidth: 220 }}
        />
      )}
      {onTipoNombramientoChange && (
        <Select
          label="Tipo de nombramiento"
          placeholder="Todos"
          data={TIPO_NOMBRAMIENTO_OPTIONS}
          searchable
          clearable
          onChange={onTipoNombramientoChange}
          {...contained}
          style={{ minWidth: 220 }}
        />
      )}
      {onAnioIngresoChange && (
        <Select
          label="Año de ingreso"
          placeholder="Todos"
          data={ANIO_OPTIONS}
          searchable
          clearable
          onChange={(v) => onAnioIngresoChange(v ? Number(v) : null)}
          {...contained}
          style={{ minWidth: 150 }}
        />
      )}
    </>
  )

  return isMobile
    ? <Stack gap="sm" mb="md">{fields}</Stack>
    : <Group gap="sm" mb="md">{fields}</Group>
}
