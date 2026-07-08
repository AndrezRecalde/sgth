'use client'

import { useState } from 'react'
import {
  Stack, Group, Badge, Text, Button,
} from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import '@mantine/dates/styles.css'
import {
  IconPill, IconCheck, IconEye,
} from '@tabler/icons-react'
import { useDisclosure } from '@mantine/hooks'
import { PageHeader } from '@/components/ui/PageHeader'
import { SgthTable } from '@/components/ui/SgthTable'
import { TableActions } from '@/components/ui/TableActions'
import { EmptyState } from '@/components/ui/EmptyState'
import { DespacharRecetaModal } from
  '@/features/dispensario/components/DespacharRecetaModal'
import { useRecetasFarmacia } from
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
  pendiente:           { label: 'Pendiente',  color: 'orange' },
  despachada_parcial:  { label: 'Parcial',    color: 'blue'   },
  despachada_completa: { label: 'Completada', color: 'emerald'},
  anulada:             { label: 'Anulada',    color: 'red'    },
}

export default function DespachoPage() {
  const [recetaSel, setRecetaSel] =
    useState<RecetaMedica | null>(null)
  const [modalOpened,
    { open: abrirModal, close: cerrarModal }] = useDisclosure(false)
  const [rango, setRango] =
    useState<[Date | null, Date | null]>([null, null])
  const [filtro, setFiltro] =
    useState<{ fecha_desde?: string; fecha_hasta?: string } | undefined>(
      undefined
    )

  const { data: recetas = [], isLoading } = useRecetasFarmacia(filtro)

  const pendientes  = recetas.filter(r => r.estado === 'pendiente').length
  const parciales   = recetas.filter(r => r.estado === 'despachada_parcial').length
  const completadas = recetas.filter(r => r.estado === 'despachada_completa').length

  const handleFiltrar = () => {
    const [inicio, fin] = rango
    const desde = fromDate(inicio as Date | string | null)
    if (desde) {
      setFiltro({
        fecha_desde: desde,
        fecha_hasta: fromDate(
          (fin ?? inicio) as Date | string | null
        ) ?? desde,
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
      title:    'Ítems',
      width:    80,
      render: (r) => (
        <Text size="sm" ta="center">{r.items.length}</Text>
      ),
    },
    {
      accessor: 'estado',
      title:    'Estado',
      width:    130,
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
      render: (r) => {
        const esCompletada = r.estado === 'despachada_completa'
        const esAnulada    = r.estado === 'anulada'

        return (
          <TableActions actions={[
            ...((!esCompletada && !esAnulada) ? [{
              label:   'Despachar',
              icon:    <IconCheck size={14} />,
              color:   'emerald',
              onClick: () => {
                setRecetaSel(r)
                abrirModal()
              },
            }] : []),
            ...((esCompletada || esAnulada) ? [{
              label:   'Ver detalle',
              icon:    <IconEye size={14} />,
              color:   'blue',
              onClick: () => {
                setRecetaSel(r)
                abrirModal()
              },
            }] : []),
          ]} />
        )
      },
    },
  ]

  return (
    <Stack gap="md">
      <PageHeader
        title="Despacho de recetas"
        subtitle="Gestión de recetas médicas"
        icon={<IconPill size={24} />}
      />

      <Group gap="sm" wrap="wrap" justify="space-between">
        <Group gap="sm">
          <DatePickerInput
            type="range"
            placeholder="Filtrar por rango de fechas"
            valueFormat="DD/MM/YYYY"
            clearable
            value={rango}
            onChange={(v) =>
              setRango(v as [Date | null, Date | null])}
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
        </Group>

        <Group gap="xs">
          {pendientes > 0 && (
            <Badge size="sm" variant="light" color="orange">
              {pendientes} pendiente{pendientes !== 1 ? 's' : ''}
            </Badge>
          )}
          {parciales > 0 && (
            <Badge size="sm" variant="light" color="blue">
              {parciales} parcial{parciales !== 1 ? 'es' : ''}
            </Badge>
          )}
          {completadas > 0 && (
            <Badge size="sm" variant="light" color="emerald">
              {completadas} completada{completadas !== 1 ? 's' : ''}
            </Badge>
          )}
        </Group>
      </Group>

      {recetas.length === 0 && !isLoading ? (
        <EmptyState
          icon={IconPill}
          title="Sin recetas"
          description="No hay recetas registradas para
            el período seleccionado."
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
