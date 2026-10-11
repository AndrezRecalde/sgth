'use client'

import { useState } from 'react'
import { Alert, Button, Group, Stack, Text } from '@mantine/core'
import { IconBook2, IconInfoCircle, IconPencil, IconPlus } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import { DataState, SgthTable, StatusBadge, TableActions } from '@/components/ui'
import type { FaltaReglamentoInterno } from '@/types/api'
import { useFaltasReglamento } from '../hooks/useDisciplinario'
import { TIPO_SANCION_LABELS, sancionesDeLaFalta } from '../utils/etiquetas'
import { FaltaReglamentoModal } from './FaltaReglamentoModal'

const porcentaje = (v?: string | null) =>
  v != null ? `${Number(v).toLocaleString('es-EC', { maximumFractionDigits: 2 })} %` : '—'

/**
 * El catálogo de faltas del reglamento interno de los obreros (fase 4.4). Lo
 * carga Talento Humano con su copia del reglamento aprobado: sin él, a un
 * obrero no se le sanciona (Código del Trabajo, Art. 44 a).
 */
export function ReglamentoInternoTab() {
  const { data: faltas = [], isLoading, error, refetch } = useFaltasReglamento()
  const [editando, setEditando] = useState<FaltaReglamentoInterno | null>(null)
  const [abierto, setAbierto] = useState(false)

  const abrir = (falta: FaltaReglamentoInterno | null) => {
    setEditando(falta)
    setAbierto(true)
  }

  const columnas: DataTableColumn<FaltaReglamentoInterno>[] = [
    { accessor: 'articulo', title: 'Artículo', width: 140 },
    {
      accessor: 'descripcion',
      title: 'Falta',
      render: (f) => <Text size="sm" lineClamp={2}>{f.descripcion}</Text>,
    },
    { accessor: 'gravedad', title: 'Gravedad', width: 110, render: (f) => f.gravedad || '—' },
    {
      accessor: 'sanciones',
      title: 'Sanciones',
      width: 220,
      render: (f) => (
        <Text size="sm">
          {sancionesDeLaFalta(f).map((s) => TIPO_SANCION_LABELS[s]).join(', ')}
        </Text>
      ),
    },
    {
      accessor: 'porcentaje_maximo_multa',
      title: 'Multa hasta',
      width: 110,
      render: (f) => (f.admite_multa ? porcentaje(f.porcentaje_maximo_multa) : '—'),
    },
    {
      accessor: 'activo',
      title: 'Estado',
      width: 110,
      render: (f) => (
        <StatusBadge tone={f.activo ? 'success' : 'neutral'}>{f.activo ? 'Vigente' : 'Inactiva'}</StatusBadge>
      ),
    },
    {
      accessor: 'acciones',
      title: '',
      width: 50,
      render: (f) => (
        <TableActions actions={[{ label: 'Corregir', icon: <IconPencil size={14} />, onClick: () => abrir(f) }]} />
      ),
    },
  ]

  const agregar = (
    <Button leftSection={<IconPlus size={16} />} variant="light" onClick={() => abrir(null)}>
      Agregar falta
    </Button>
  )

  return (
    <Stack gap="md">
      <Alert variant="light" color="ocean" icon={<IconInfoCircle size={16} />}>
        A los obreros se les sanciona con su reglamento interno de trabajo, no con
        el sumario de la LOSEP: solo lo que el reglamento prevé, y la multa nunca
        pasa del 10 % de la remuneración (Código del Trabajo, Arts. 44 y 64). Sin
        faltas cargadas, no se les abre procedimiento. Una falta que ya se imputó
        no se reescribe: se desactiva y se carga la redacción nueva.
      </Alert>

      <Group justify="flex-end">{agregar}</Group>

      <DataState
        loading={isLoading}
        error={error}
        errorTitle="No se pudo cargar el reglamento interno"
        onRetry={refetch}
        empty={!faltas.length}
        emptyProps={{
          icon: IconBook2,
          title: 'El reglamento interno no tiene faltas cargadas',
          description: 'Cárguelas desde la copia del reglamento aprobado por el Ministerio del Trabajo. Sin ellas, a un obrero no se le sanciona.',
          action: agregar,
        }}
      >
        <SgthTable records={faltas} columns={columnas} minHeight={200} />
      </DataState>

      <FaltaReglamentoModal opened={abierto} onClose={() => setAbierto(false)} falta={editando} />
    </Stack>
  )
}
