'use client'

import { useState } from 'react'
import {
  Stack, Group, Badge, Text,
} from '@mantine/core'
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
import { FiltroDespachoBar } from
  '@/features/dispensario/components/FiltroDespachoBar'
import type { FiltroDespacho } from
  '@/features/dispensario/components/FiltroDespachoBar'
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

  const [filtro, setFiltro] = useState<FiltroDespacho>({})
  const [filtroActivo, setFiltroActivo] =
    useState<Record<string, string | number | undefined>>({})

  const { data: recetas = [], isLoading } =
    useRecetasFarmacia(filtroActivo)

  const pendientes  = recetas.filter(r => r.estado === 'pendiente').length
  const parciales   = recetas.filter(r => r.estado === 'despachada_parcial').length
  const completadas = recetas.filter(r => r.estado === 'despachada_completa').length

  const handleSearch = () => {
    const params: Record<string, string | number | undefined> = {}
    if (filtro.medico_id)   params.medico_id   = filtro.medico_id
    if (filtro.fecha_desde) params.fecha_desde  = filtro.fecha_desde
    if (filtro.fecha_hasta) params.fecha_hasta  = filtro.fecha_hasta
    if (filtro.estado)      params.estado       = filtro.estado
    setFiltroActivo(params)
  }

  const handleReset = () => {
    setFiltro({})
    setFiltroActivo({})
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
            ...(!esCompletada && !esAnulada ? [{
              label:   'Despachar',
              icon:    <IconCheck size={14} />,
              color:   'emerald',
              onClick: () => { setRecetaSel(r); abrirModal() },
            }] : []),
            ...(esCompletada || esAnulada ? [{
              label:   'Ver detalle',
              icon:    <IconEye size={14} />,
              color:   'blue',
              onClick: () => { setRecetaSel(r); abrirModal() },
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

      <FiltroDespachoBar
        value={filtro}
        onChange={setFiltro}
        onSearch={handleSearch}
        onReset={handleReset}
      />

      <Group gap="xs" justify="flex-end">
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

      {recetas.length === 0 && !isLoading ? (
        <EmptyState
          icon={IconPill}
          title="Sin recetas"
          description="No hay recetas para los filtros seleccionados."
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
