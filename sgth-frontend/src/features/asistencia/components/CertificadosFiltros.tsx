'use client'

import { Chip, Group, Select, TextInput } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { Toolbar } from '@/components/ui'
import { SEMANTIC_COLOR, type SemanticTone } from '@/config/design.tokens'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useTodasUnidades } from '@/features/estructura/hooks/useUnidades'
import { fromDateValueOrNull, toDateValue } from '@/lib/fecha'

export type EstadoCertificado = 'pendiente' | 'aprobado' | 'anulado' | 'todos'

export interface FiltrosCertificado {
  estado:     EstadoCertificado
  unidadId:   string | null
  fechaDesde: string | null
  fechaHasta: string | null
  folio:      string
}

/** Abre en los pendientes: es el trabajo de quien entra. */
export const FILTROS_CERTIFICADO_INICIALES: FiltrosCertificado = {
  estado: 'pendiente', unidadId: null, fechaDesde: null, fechaHasta: null, folio: '',
}

/** Con el mismo tono que la etiqueta de cada estado en la tabla. */
const ESTADOS: { valor: EstadoCertificado; etiqueta: string; tono: SemanticTone }[] = [
  { valor: 'pendiente', etiqueta: 'Pendientes', tono: 'warning' },
  { valor: 'aprobado',  etiqueta: 'Aprobados',  tono: 'success' },
  { valor: 'anulado',   etiqueta: 'Anulados',   tono: 'neutral' },
  { valor: 'todos',     etiqueta: 'Todos',      tono: 'neutral' },
]

interface Props {
  filtros:   FiltrosCertificado
  onCambiar: (cambio: Partial<FiltrosCertificado>) => void
}

/** Los filtros de la viñeta: folio, unidad, días de reposo y estado. */
export function CertificadosFiltros({ filtros, onCambiar }: Props) {
  const contained = useContainedInput('sm')
  // La lista pública de unidades: Trabajo Social no tiene `ver-estructura`.
  const { data: unidades = [] } = useTodasUnidades({ nivel: 2 })
  const unidadOptions = unidades.map((u) => ({ value: String(u.id), label: u.nombre ?? `Unidad ${u.id}` }))

  return (
    <Toolbar>
      <TextInput
        label="Folio"
        placeholder="Ej: CERT-2026-00001"
        {...contained}
        value={filtros.folio}
        onChange={(e) => onCambiar({ folio: e.currentTarget.value })}
        style={{ minWidth: 220 }}
      />

      <Select
        label="Unidad administrativa"
        placeholder="Todas"
        data={unidadOptions}
        searchable
        clearable
        {...contained}
        value={filtros.unidadId}
        onChange={(v) => onCambiar({ unidadId: v })}
        style={{ minWidth: 240 }}
      />

      {/* Un certificado entra si alguno de sus días de reposo cae en el rango. */}
      <Group gap="sm" wrap="nowrap" align="flex-end">
        <DatePickerInput
          label="Reposo desde"
          placeholder="Sin límite"
          valueFormat="YYYY-MM-DD"
          clearable
          {...contained}
          value={toDateValue(filtros.fechaDesde)}
          onChange={(d) => onCambiar({ fechaDesde: fromDateValueOrNull(d ?? null) })}
          style={{ minWidth: 150 }}
        />
        <DatePickerInput
          label="Hasta"
          placeholder="Sin límite"
          valueFormat="YYYY-MM-DD"
          clearable
          minDate={toDateValue(filtros.fechaDesde) ?? undefined}
          {...contained}
          value={toDateValue(filtros.fechaHasta)}
          onChange={(d) => onCambiar({ fechaHasta: fromDateValueOrNull(d ?? null) })}
          style={{ minWidth: 150 }}
        />
      </Group>

      <Group gap="xs">
        {ESTADOS.map(({ valor, etiqueta, tono }) => (
          <Chip
            key={valor}
            color={SEMANTIC_COLOR[tono]}
            checked={filtros.estado === valor}
            onChange={() => onCambiar({ estado: valor })}
          >
            {etiqueta}
          </Chip>
        ))}
      </Group>
    </Toolbar>
  )
}
