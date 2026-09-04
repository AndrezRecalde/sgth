'use client'

import { useState } from 'react'
import { Button, Group, Select } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { IconPill } from '@tabler/icons-react'
import { useDisclosure } from '@mantine/hooks'
import { useContainedInput } from '@/hooks/useContainedInput'
import { DespacharRecetaModal } from
  '@/features/dispensario/components/DespacharRecetaModal'
import { getRecetasColumns } from
  '@/features/dispensario/components/recetas.columns'
import { usePersonalMedico } from '@/features/dispensario/hooks/useAgenda'
import {
  useRecetasFarmacia, useAnularReceta,
} from '@/features/dispensario/hooks/useReceta'
import {
  AnularRegistroModal, MOTIVOS_ANULAR_RECETA,
} from '@/features/dispensario/components/AnularRegistroModal'
import type { RecetaMedica } from
  '@/features/dispensario/services/recetaService'
import {
  DataState, PageHeader, PageShell, SgthTable, StatusBadge, Toolbar,
} from '@/components/ui'

const ESTADO_OPTIONS = [
  { value: 'pendiente',           label: 'Pendiente'  },
  { value: 'despachada_parcial',  label: 'Parcial'    },
  { value: 'despachada_completa', label: 'Completada' },
  { value: 'anulada',             label: 'Anulada'    },
]

/** `Date` de Mantine → 'YYYY-MM-DD' sin pasar por UTC, que restaría un día. */
function aIso(d: Date | string | null): string | undefined {
  if (!d) return undefined
  if (typeof d === 'string') return d.slice(0, 10)
  if (isNaN(d.getTime())) return undefined
  return [
    d.getFullYear(),
    String(d.getMonth() + 1).padStart(2, '0'),
    String(d.getDate()).padStart(2, '0'),
  ].join('-')
}

function rangoHoy(): [Date, Date] {
  const hoy = new Date()
  return [hoy, hoy]
}

function rangoSemana(): [Date, Date] {
  const hoy = new Date()
  const lunes = new Date(hoy)
  lunes.setDate(hoy.getDate() - hoy.getDay() + 1)
  const viernes = new Date(lunes)
  viernes.setDate(lunes.getDate() + 4)
  return [lunes, viernes]
}

function rangoMes(): [Date, Date] {
  const hoy = new Date()
  return [
    new Date(hoy.getFullYear(), hoy.getMonth(), 1),
    new Date(hoy.getFullYear(), hoy.getMonth() + 1, 0),
  ]
}

export function DespachoView() {
  const contained = useContainedInput('sm')

  const [medicoId, setMedicoId] = useState<string | null>(null)
  const [rango, setRango] = useState<[Date | null, Date | null]>([null, null])
  const [estado, setEstado] = useState<string | null>(null)

  const [recetaSel, setRecetaSel] = useState<RecetaMedica | null>(null)
  const [modalOpened, { open: abrirModal, close: cerrarModal }] =
    useDisclosure(false)
  const [anularOpened, { open: abrirAnular, close: cerrarAnular }] =
    useDisclosure(false)

  const anular = useAnularReceta()

  const { data: medicos = [] } = usePersonalMedico('medico')

  const { data: recetas = [], isLoading, error } = useRecetasFarmacia({
    medico_id:   medicoId ? Number(medicoId) : undefined,
    fecha_desde: aIso(rango[0]),
    fecha_hasta: aIso(rango[1]),
    estado:      estado ?? undefined,
  })

  const pendientes  = recetas.filter(r => r.estado === 'pendiente').length
  const parciales   = recetas.filter(r => r.estado === 'despachada_parcial').length
  const completadas = recetas.filter(r => r.estado === 'despachada_completa').length

  const hayFiltros = !!(medicoId || rango[0] || estado)

  const limpiar = () => {
    setMedicoId(null)
    setRango([null, null])
    setEstado(null)
  }

  const columns = getRecetasColumns({
    onAbrir:  (r) => { setRecetaSel(r); abrirModal() },
    onAnular: (r) => { setRecetaSel(r); abrirAnular() },
  })

  return (
    <PageShell>
      <PageHeader
        title="Despacho de recetas"
        description="Recetas emitidas por el Dispensario Médico pendientes de entrega"
        actions={
          <Group gap="xs">
            {pendientes > 0 && (
              <StatusBadge tone="warning">
                {pendientes} pendiente{pendientes !== 1 ? 's' : ''}
              </StatusBadge>
            )}
            {parciales > 0 && (
              <StatusBadge tone="info">
                {parciales} parcial{parciales !== 1 ? 'es' : ''}
              </StatusBadge>
            )}
            {completadas > 0 && (
              <StatusBadge tone="success">
                {completadas} completada{completadas !== 1 ? 's' : ''}
              </StatusBadge>
            )}
          </Group>
        }
      />

      <Toolbar
        actions={
          <>
            <Button size="xs" variant="default"
              onClick={() => setRango(rangoHoy())}>
              Hoy
            </Button>
            <Button size="xs" variant="default"
              onClick={() => setRango(rangoSemana())}>
              Esta semana
            </Button>
            <Button size="xs" variant="default"
              onClick={() => setRango(rangoMes())}>
              Este mes
            </Button>
            {hayFiltros && (
              <Button size="xs" variant="subtle" onClick={limpiar}>
                Limpiar
              </Button>
            )}
          </>
        }
      >
        <Select
          label="Médico"
          placeholder="Todos los médicos"
          data={medicos.map(m => ({
            value: String(m.id),
            label: m.nombre_completo ?? `Médico ${m.id}`,
          }))}
          searchable
          clearable
          {...contained}
          value={medicoId}
          onChange={setMedicoId}
          style={{ minWidth: 220 }}
        />
        <DatePickerInput
          type="range"
          label="Fechas"
          placeholder="Todo el período"
          valueFormat="DD/MM/YYYY"
          clearable
          {...contained}
          value={rango}
          onChange={(v) => setRango(v as [Date | null, Date | null])}
          style={{ minWidth: 240 }}
        />
        <Select
          label="Estado"
          placeholder="Todos"
          data={ESTADO_OPTIONS}
          clearable
          {...contained}
          value={estado}
          onChange={setEstado}
          style={{ minWidth: 160 }}
        />
      </Toolbar>

      <DataState
        loading={isLoading}
        error={error}
        empty={!recetas.length}
        emptyProps={{
          icon: IconPill,
          title: 'Sin recetas',
          description: hayFiltros
            ? 'Ninguna receta coincide con los filtros aplicados.'
            : 'Aún no se han emitido recetas desde el Dispensario.',
        }}
      >
        <SgthTable
          records={recetas}
          columns={columns}
          minHeight={200}
        />
      </DataState>

      <DespacharRecetaModal
        opened={modalOpened}
        onClose={() => { cerrarModal(); setRecetaSel(null) }}
        receta={recetaSel}
      />

      <AnularRegistroModal
        opened={anularOpened}
        onClose={() => { cerrarAnular(); setRecetaSel(null) }}
        titulo="Anular receta"
        descripcion={
          recetaSel?.estado === 'despachada_parcial'
            ? 'Se cerrará lo que falta por entregar; lo ya despachado no se devuelve.'
            : 'La receta dejará de figurar pendiente de entrega.'
        }
        motivos={MOTIVOS_ANULAR_RECETA}
        loading={anular.isPending}
        onConfirmar={(motivo) => {
          if (!recetaSel) return
          anular.mutate(
            { id: recetaSel.id, motivo },
            { onSuccess: () => { cerrarAnular(); setRecetaSel(null) } }
          )
        }}
      />
    </PageShell>
  )
}
