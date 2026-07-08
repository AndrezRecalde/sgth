'use client'

import { useState } from 'react'
import {
  Stack, Group, Badge, Text,
  Button, Card,
} from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import '@mantine/dates/styles.css'
import { IconPill, IconCheck } from '@tabler/icons-react'
import { useDisclosure } from '@mantine/hooks'
import { PageHeader } from '@/components/ui/PageHeader'
import { SgthTable } from '@/components/ui/SgthTable'
import { TableActions } from '@/components/ui/TableActions'
import { EmptyState } from '@/components/ui/EmptyState'
import { DespacharRecetaModal } from
  '@/features/dispensario/components/DespacharRecetaModal'
import { useRecetasPendientes } from
  '@/features/dispensario/hooks/useReceta'
import type { RecetaMedica } from
  '@/features/dispensario/services/recetaService'
import type { DataTableColumn } from 'mantine-datatable'

function getNombrePaciente(r: RecetaMedica): string {
  const historia = r.consulta_medica?.historia_clinica
  if (historia?.servidor) {
    return `${historia.servidor.nombre} ${historia.servidor.apellido}`
  }
  if (historia?.carga_familiar) {
    return `${historia.carga_familiar.nombres} ${historia.carga_familiar.apellidos}`
  }
  return '—'
}

function fromDate(d: Date | string | null): string | undefined {
  if (!d) return undefined
  if (typeof d === 'string') return d.slice(0, 10)
  if (!(d instanceof Date) || isNaN(d.getTime())) return undefined
  return [
    d.getFullYear(),
    String(d.getMonth() + 1).padStart(2, '0'),
    String(d.getDate()).padStart(2, '0'),
  ].join('-')
}

const ESTADO_CONFIG: Record<string, { label: string; color: string }> = {
  pendiente:          { label: 'Pendiente',   color: 'orange' },
  despachada_parcial: { label: 'Parcial',     color: 'blue'   },
  despachada_completa:{ label: 'Completada',  color: 'emerald'},
  anulada:            { label: 'Anulada',     color: 'red'    },
}

export default function DespachoPage() {
  const [recetaSel, setRecetaSel] =
    useState<RecetaMedica | null>(null)
  const [modalOpened,
    { open: abrirModal, close: cerrarModal }] = useDisclosure(false)
  const [rango, setRango] =
    useState<[Date | null, Date | null]>([null, null])
  const [filtro, setFiltro] =
    useState<{ fecha_desde?: string; fecha_hasta?: string } | undefined>(undefined)

  const { data: recetas = [], isLoading } = useRecetasPendientes(filtro)

  const handleFiltrar = () => {
    const [inicio, fin] = rango
    const desde = fromDate(inicio as Date | string | null)
    if (desde) {
      setFiltro({
        fecha_desde: desde,
        fecha_hasta: fromDate((fin ?? inicio) as Date | string | null) ?? desde,
      })
    }
  }

  const handleLimpiar = () => {
    setRango([null, null])
    setFiltro(undefined)
  }

  const columns: DataTableColumn<RecetaMedica>[] = [
    {
      accessor: 'fecha_emision',
      title:    'Fecha emisión',
      width:    130,
      render: (r) => (
        <Text size="sm">
          {new Date(r.fecha_emision).toLocaleDateString('es-EC', {
            day: '2-digit', month: 'short', year: 'numeric',
          })}
        </Text>
      ),
    },
    {
      accessor: 'paciente',
      title:    'Paciente',
      render: (r) => (
        <Stack gap={0}>
          <Text size="sm" fw={500}>{getNombrePaciente(r)}</Text>
          <Text size="xs" c="dimmed">
            {r.consulta_medica?.historia_clinica?.servidor
              ? 'Servidor' : 'Familiar'}
          </Text>
        </Stack>
      ),
    },
    {
      accessor: 'medico',
      title:    'Médico',
      width:    160,
      render: (r) => {
        const medico = r.consulta_medica?.medico?.servidor
        return (
          <Text size="sm">
            {medico
              ? `Dr. ${medico.nombre} ${medico.apellido}`
              : '—'}
          </Text>
        )
      },
    },
    {
      accessor: 'items',
      title:    'Medicamentos',
      width:    100,
      render: (r) => (
        <Text size="sm" ta="center">
          {r.items.length} ítem{r.items.length !== 1 ? 's' : ''}
        </Text>
      ),
    },
    {
      accessor: 'estado',
      title:    'Estado',
      width:    120,
      render: (r) => {
        const cfg = ESTADO_CONFIG[r.estado]
          ?? { label: r.estado, color: 'gray' }
        return (
          <Badge size="sm" variant="light" color={cfg.color}>
            {cfg.label}
          </Badge>
        )
      },
    },
    {
      accessor: 'acciones',
      title:    '',
      width:    50,
      render: (r) => (
        <TableActions actions={[
          {
            label:   'Despachar',
            icon:    <IconCheck size={14} />,
            color:   'emerald',
            onClick: () => {
              setRecetaSel(r)
              abrirModal()
            },
          },
        ]} />
      ),
    },
  ]

  return (
    <Stack gap="md">
      <PageHeader
        title="Despacho de recetas"
        subtitle="Recetas pendientes de despacho"
        icon={<IconPill size={24} />}
      />

      <Group gap="sm" wrap="wrap">
        <DatePickerInput
          type="range"
          placeholder="Filtrar por rango de fechas"
          valueFormat="DD/MM/YYYY"
          clearable
          value={rango}
          onChange={(v) => setRango(v as [Date | null, Date | null])}
          style={{ width: 280 }}
        />
        <Button
          variant="light"
          onClick={handleFiltrar}
          disabled={!rango[0]}
        >
          Filtrar
        </Button>
        {filtro && (
          <Button
            variant="subtle"
            color="gray"
            onClick={handleLimpiar}
          >
            Limpiar
          </Button>
        )}
        <Badge size="sm" variant="light" color="orange">
          {recetas.length} receta{recetas.length !== 1 ? 's' : ''} pendiente{recetas.length !== 1 ? 's' : ''}
        </Badge>
      </Group>

      {recetas.length === 0 && !isLoading ? (
        <EmptyState
          icon={IconPill}
          title="Sin recetas pendientes"
          description="No hay recetas pendientes de despacho."
        />
      ) : (
        <SgthTable
          records={recetas}
          columns={columns}
          fetching={isLoading}
          minHeight={200}
        />
      )}

      <DespacharRecetaModal
        opened={modalOpened}
        onClose={() => { cerrarModal(); setRecetaSel(null) }}
        receta={recetaSel}
      />
    </Stack>
  )
}
