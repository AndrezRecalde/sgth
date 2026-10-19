'use client'

import { Button, Select, TextInput } from '@mantine/core'
import { IconFileSpreadsheet } from '@tabler/icons-react'
import { Toolbar } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import type { FiltroEstadoLectura, FiltrosLecturas } from '../services/tipos'

interface Props {
  requiereConfirmacion: boolean
  direcciones: { value: string; label: string }[]
  filtros: FiltrosLecturas
  busqueda: string
  onBusqueda: (texto: string) => void
  onCambiar: (cambio: Partial<FiltrosLecturas>) => void
  /** Sin pendientes no hay nada que descargar. */
  hayPendientes: boolean
  descargando: boolean
  onDescargar: () => void
}

/** Con confirmación, «pendiente» es quien no confirmó; sin ella, es lo mismo que «sin leer» y no se repite. */
function opcionesEstado(requiereConfirmacion: boolean): { value: FiltroEstadoLectura; label: string }[] {
  return requiereConfirmacion
    ? [
        { value: 'pendiente',  label: 'Pendientes de confirmar' },
        { value: 'sin_leer',   label: 'Sin leer' },
        { value: 'leido',      label: 'Leído, sin confirmar' },
        { value: 'confirmado', label: 'Confirmado' },
      ]
    : [
        { value: 'sin_leer', label: 'Sin leer' },
        { value: 'leido',    label: 'Leído' },
      ]
}

const ESTADOS: readonly FiltroEstadoLectura[] = ['sin_leer', 'leido', 'confirmado', 'pendiente']
const esEstado = (v: string | null): v is FiltroEstadoLectura => ESTADOS.some((e) => e === v)

export function LecturasFiltros({
  requiereConfirmacion, direcciones, filtros, busqueda, onBusqueda, onCambiar, hayPendientes, descargando, onDescargar,
}: Props) {
  const compacto = useContainedInput('sm')

  const excel = (
    <Button
      variant="light"
      leftSection={<IconFileSpreadsheet size={16} />}
      loading={descargando}
      disabled={!hayPendientes}
      onClick={onDescargar}
    >
      {requiereConfirmacion ? 'Excel de quién no confirmó' : 'Excel de quién no la leyó'}
    </Button>
  )

  return (
    <Toolbar actions={excel}>
      <TextInput
        label="Buscar"
        placeholder="Nombre o cédula"
        {...compacto}
        value={busqueda}
        onChange={(e) => onBusqueda(e.currentTarget.value)}
      />
      <Select
        label="Estado"
        placeholder="Todos"
        data={opcionesEstado(requiereConfirmacion)}
        clearable
        {...compacto}
        value={filtros.estado ?? null}
        onChange={(v) => onCambiar({ estado: esEstado(v) ? v : undefined })}
        style={{ minWidth: 200 }}
      />
      {direcciones.length > 1 && (
        <Select
          label="Dirección"
          placeholder="Todas"
          data={direcciones}
          searchable
          clearable
          {...compacto}
          value={filtros.unidad_administrativa_id ? String(filtros.unidad_administrativa_id) : null}
          onChange={(v) => onCambiar({ unidad_administrativa_id: v ? Number(v) : undefined })}
          style={{ minWidth: 260 }}
        />
      )}
    </Toolbar>
  )
}
