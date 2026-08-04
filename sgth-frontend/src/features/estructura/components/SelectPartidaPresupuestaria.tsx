'use client'

import { Select } from '@mantine/core'
import { useContainedInput } from '@/hooks/useContainedInput'
import { usePartidasPresupuestarias } from '../hooks/usePartidasPresupuestarias'

interface Props {
  value?: number | null
  onChange: (value: number | null) => void
  label?: string
  placeholder?: string
  description?: string
  error?: string
  required?: boolean
  disabled?: boolean
  /**
   * Limita el listado a partidas con disponibilidad presupuestaria
   * verificada. Útil donde la selección compromete presupuesto (acciones de
   * personal con efecto económico, Art. 105 LOSEP); en el catálogo de
   * Puestos se deja en false para no ocultar partidas aún sin verificar.
   */
  soloDisponibles?: boolean
}

/**
 * Selector del catálogo de partidas presupuestarias, compartido por Puestos
 * y por las acciones de personal para no duplicar el fetch ni el formato de
 * la etiqueta.
 */
export function SelectPartidaPresupuestaria({
  value,
  onChange,
  label = 'Partida presupuestaria',
  placeholder = 'Seleccione una partida...',
  description,
  error,
  required,
  disabled,
  soloDisponibles = false,
}: Props) {
  const contained = useContainedInput()

  const { data: partidas = [], isLoading } = usePartidasPresupuestarias({
    activo: true,
    ...(soloDisponibles ? { disponible: true } : {}),
  })

  const options = partidas.map((p) => ({
    value: String(p.id),
    label: `${p.codigo} — ${p.descripcion}`,
  }))

  return (
    <Select
      label={label}
      placeholder={isLoading ? 'Cargando partidas...' : placeholder}
      description={description}
      data={options}
      value={value ? String(value) : null}
      onChange={(v) => onChange(v ? Number(v) : null)}
      error={error}
      required={required}
      disabled={disabled || isLoading}
      searchable
      clearable
      nothingFoundMessage="Sin partidas que coincidan"
      {...contained}
    />
  )
}
