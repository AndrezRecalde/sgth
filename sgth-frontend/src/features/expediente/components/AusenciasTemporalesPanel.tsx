'use client'

import { useState } from 'react'
import { Alert, Badge, Box, Group, Select, Text, Tooltip } from '@mantine/core'
import { IconInfoCircle, IconUserOff } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import { SgthTable } from '@/components/ui/SgthTable'
import { EmptyState } from '@/components/ui/EmptyState'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useAusenciasTemporales } from '../hooks/useAusenciasTemporales'
import type { AusenciaTemporal } from '../services/ausenciaTemporalService'

const COBERTURA_OPTIONS = [
  { value: 'pendientes', label: 'Sin cubrir' },
  { value: 'cubiertas', label: 'Ya cubiertas' },
]

function fecha(f?: string | null): string {
  if (!f) return '—'
  return new Date(f).toLocaleDateString('es-EC', {
    day: '2-digit', month: '2-digit', year: 'numeric', timeZone: 'UTC',
  })
}

/** El plazo que resta, con el matiz de que una ausencia puede no tener fin. */
function Restante({ dias }: { dias: number | null }) {
  if (dias === null) {
    return <Badge color="gray" variant="light" size="sm">Sin fecha de fin</Badge>
  }

  const color = dias <= 30 ? 'orange' : 'gray'

  return (
    <Badge color={color} variant="light" size="sm">
      {dias} día{dias === 1 ? '' : 's'}
    </Badge>
  )
}

/**
 * Quién está temporalmente fuera y qué huecos quedan por cubrir.
 *
 * La ausencia no es un estado guardado: se deriva del período de la comisión o
 * la licencia, así que al vencer sale sola de este listado. El contrato del
 * titular sigue vigente todo el tiempo — no se libera la plaza, se autoriza un
 * apoyo temporal encima de ella.
 */
export function AusenciasTemporalesPanel() {
  const contained = useContainedInput()
  const [cobertura, setCobertura] = useState<string | null>(null)

  const { data: ausencias = [], isLoading } = useAusenciasTemporales(
    cobertura ? { cubiertas: cobertura === 'cubiertas' } : {},
  )

  const columns: DataTableColumn<AusenciaTemporal>[] = [
    {
      accessor: 'servidor',
      title: 'Servidor ausente',
      render: (a) => (
        <div>
          <Text size="sm" fw={500}>{a.servidor.nombre || '—'}</Text>
          <Text size="xs" c="dimmed">{a.servidor.cedula ?? '—'}</Text>
        </div>
      ),
    },
    {
      accessor: 'puesto',
      title: 'Puesto y unidad',
      render: (a) => (
        <div>
          <Text size="sm">{a.puesto ?? '—'}</Text>
          <Text size="xs" c="dimmed">{a.unidad ?? '—'}</Text>
        </div>
      ),
    },
    {
      accessor: 'etiqueta',
      title: 'Motivo',
      render: (a) => (
        <div>
          <Text size="sm">{a.etiqueta ?? '—'}</Text>
          {a.codigo_registro && (
            <Text size="xs" c="dimmed" ff="monospace">{a.codigo_registro}</Text>
          )}
        </div>
      ),
    },
    {
      accessor: 'periodo',
      title: 'Período',
      width: 190,
      render: (a) => (
        <div>
          <Text size="sm">{fecha(a.desde)} – {a.hasta ? fecha(a.hasta) : 'sin fin'}</Text>
          <Restante dias={a.dias_restantes} />
        </div>
      ),
    },
    {
      accessor: 'reemplazo',
      title: 'Cobertura',
      render: (a) => {
        if (!a.reemplazo) {
          return (
            <Tooltip label="Registre un Ingreso y Vinculación enlazado a esta ausencia" withArrow>
              <Badge color="orange" variant="light" size="sm">Sin cubrir</Badge>
            </Tooltip>
          )
        }

        return (
          <div>
            <Text size="sm" fw={500}>{a.reemplazo.servidor.nombre || '—'}</Text>
            <Text size="xs" c="dimmed" ff="monospace">
              {a.reemplazo.numero_contrato ?? 'sin número'}
            </Text>
          </div>
        )
      },
    },
  ]

  if (!isLoading && ausencias.length === 0 && cobertura === null) {
    return (
      <EmptyState
        icon={IconUserOff}
        title="Nadie está temporalmente ausente"
        description="Aquí aparecen las comisiones de servicios y licencias sin remuneración vigentes hoy, para cubrir el hueco con personal de apoyo."
      />
    )
  }

  return (
    <Box>
      <Alert variant="light" color="blue" icon={<IconInfoCircle size={16} />} mb="md">
        El titular conserva su vínculo y su plaza mientras dura la ausencia. El
        reemplazo se contrata por Servicios Ocasionales o Profesionales, encima
        de esa plaza y sin pasar de la fecha en que el titular regresa.
      </Alert>

      <Group justify="space-between" mb="md">
        <Select
          label="Filtrar por cobertura"
          placeholder="Todas"
          data={COBERTURA_OPTIONS}
          value={cobertura}
          onChange={setCobertura}
          clearable
          {...contained}
          style={{ minWidth: 260 }}
        />
        <Text size="sm" c="dimmed">
          {ausencias.length} ausencia(s) vigente(s)
        </Text>
      </Group>

      <SgthTable
        records={ausencias}
        columns={columns}
        fetching={isLoading}
        minHeight={200}
      />
    </Box>
  )
}
