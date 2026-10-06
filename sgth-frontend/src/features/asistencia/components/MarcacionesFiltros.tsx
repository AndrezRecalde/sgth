'use client'

import { Button, Group, TextInput } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { IconSearch } from '@tabler/icons-react'
import { Toolbar } from '@/components/ui'
import { BuscarServidorSelect } from '@/features/expediente/components/BuscarServidorSelect'
import { useContainedInput } from '@/hooks/useContainedInput'
import { fromDateValueOrNull, toDateValue } from '@/lib/fecha'
import type { ServidorConRelaciones } from '@/types/api'

/**
 * Cómo se elige a quién consultar:
 * - `propio`: el servidor del usuario, fijo (sin `ver-asistencia-todos`, o en
 *   el portal).
 * - `buscar`: el buscador de servidores, que es de Talento Humano
 *   (`ServidorPolicy::verAny`).
 * - `cedula`: la máxima autoridad y auditoría ven la asistencia de todos pero
 *   no el listado de expedientes: escriben la cédula.
 */
export type ModoServidor = 'propio' | 'buscar' | 'cedula'

export interface FiltrosMarcaciones {
  servidorId:    number | null
  elegido:       ServidorConRelaciones | null
  cedulaEscrita: string
  fechaInicio:   string | null
  fechaFin:      string | null
}

interface Props {
  modo:           ModoServidor
  /** «cédula — apellido nombre» del usuario, para el modo `propio`. */
  servidorPropio: string
  filtros:        FiltrosMarcaciones
  onCambiar:      (cambio: Partial<FiltrosMarcaciones>) => void
  onConsultar:    () => void
  puedeConsultar: boolean
  consultando:    boolean
}

export function MarcacionesFiltros({
  modo, servidorPropio, filtros, onCambiar, onConsultar, puedeConsultar, consultando,
}: Props) {
  const contained = useContainedInput('sm')
  const cedulaIncompleta = filtros.cedulaEscrita !== '' && !/^\d{10}$/.test(filtros.cedulaEscrita)

  return (
    <Toolbar
      actions={
        <Button
          variant="light"
          leftSection={<IconSearch size={16} />}
          disabled={!puedeConsultar}
          loading={consultando}
          onClick={onConsultar}
        >
          Consultar
        </Button>
      }
    >
      {modo === 'buscar' && (
        <BuscarServidorSelect
          label="Servidor"
          size="sm"
          value={filtros.servidorId}
          onChange={(id) => onCambiar(id === null ? { servidorId: null, elegido: null } : { servidorId: id })}
          onSelect={(servidor) => onCambiar({ elegido: servidor })}
        />
      )}

      {modo === 'cedula' && (
        <TextInput
          label="Cédula del servidor"
          placeholder="10 dígitos"
          inputMode="numeric"
          maxLength={10}
          {...contained}
          value={filtros.cedulaEscrita}
          onChange={(e) => onCambiar({ cedulaEscrita: e.currentTarget.value.replace(/\D/g, '') })}
          error={cedulaIncompleta ? 'La cédula tiene 10 dígitos.' : undefined}
        />
      )}

      {modo === 'propio' && (
        <TextInput label="Servidor" {...contained} value={servidorPropio} readOnly style={{ minWidth: 260 }} />
      )}

      {/* Desde y Hasta son un solo filtro, un rango: van juntos. */}
      <Group gap="sm" wrap="nowrap" align="flex-end">
        <DatePickerInput
          label="Fecha inicio"
          placeholder="Desde"
          valueFormat="YYYY-MM-DD"
          {...contained}
          value={toDateValue(filtros.fechaInicio)}
          onChange={(d) => onCambiar({ fechaInicio: fromDateValueOrNull(d ?? null) })}
          style={{ minWidth: 150 }}
        />
        <DatePickerInput
          label="Fecha fin"
          placeholder="Hasta"
          valueFormat="YYYY-MM-DD"
          minDate={toDateValue(filtros.fechaInicio) ?? undefined}
          {...contained}
          value={toDateValue(filtros.fechaFin)}
          onChange={(d) => onCambiar({ fechaFin: fromDateValueOrNull(d ?? null) })}
          style={{ minWidth: 150 }}
        />
      </Group>
    </Toolbar>
  )
}
