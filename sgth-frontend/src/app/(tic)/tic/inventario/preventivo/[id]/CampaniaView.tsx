'use client'

import { useState } from 'react'
import { Button, Group, SegmentedControl, SimpleGrid, Skeleton, Text } from '@mantine/core'
import {
  IconAlertTriangle, IconCalendarCheck, IconCircleCheck, IconClockPause, IconEdit, IconListCheck, IconLock, IconPlus,
} from '@tabler/icons-react'
import {
  EmptyState, MotivoModal, PageHeader, PageShell, SectionCard, SgthTable, StatCard, StatusBadge, Toolbar, confirmar,
} from '@/components/ui'
import { SEMANTIC_COLOR } from '@/config/design.tokens'
import { ROUTES } from '@/config/routes'
import { useAuth } from '@/hooks/useAuth'
import { formatFecha } from '@/lib/fecha'
import { plural } from '@/lib/plural'
import { AgregarEquiposCampaniaModal } from '@/features/inventario-ti/components/AgregarEquiposCampaniaModal'
import { CampaniaModal } from '@/features/inventario-ti/components/CampaniaModal'
import { AvanceCampania, columnasFilasCampania } from '@/features/inventario-ti/components/campanias.columns'
import { RealizarCampaniaModal } from '@/features/inventario-ti/components/RealizarCampaniaModal'
import { SITUACION_CAMPANIA } from '@/features/inventario-ti/constants/campania'
import { useCampania, useCampaniaMutations } from '@/features/inventario-ti/hooks/useCampanias'
import type { FilaCampania } from '@/features/inventario-ti/services/campaniasService'

type Filtro = 'todos' | 'pendientes' | 'hechos' | 'omitidos'

const FILTROS: Record<Filtro, (f: FilaCampania) => boolean> = {
  todos: () => true,
  pendientes: (f) => f.estado === 'pendiente' || f.estado === 'no_realizado',
  hechos: (f) => f.estado === 'hecho',
  omitidos: (f) => f.estado === 'omitido',
}
const esFiltro = (v: string): v is Filtro => v in FILTROS

/**
 * Una campaña de preventivo: el avance arriba y, debajo, los equipos. Se eligen
 * los que se atendieron y se marcan como hechos de una vez (cada uno queda con
 * su mantenimiento en la ficha), o se omiten con el motivo. Al cerrarla, lo
 * pendiente queda como no realizado.
 */
export function CampaniaView({ id }: { id: number }) {
  const { hasPermiso } = useAuth()
  const { data: c, isLoading } = useCampania(id)
  const { quitar, omitir, cerrar } = useCampaniaMutations(id)
  const [filtro, setFiltro] = useState<Filtro>('todos')
  const [seleccion, setSeleccion] = useState<FilaCampania[]>([])
  const [realizando, setRealizando] = useState<FilaCampania[]>([])
  const [omitiendo, setOmitiendo] = useState(false)
  const [editando, setEditando] = useState(false)
  const [agregando, setAgregando] = useState(false)

  if (isLoading) return <PageShell><Skeleton height={80} radius="lg" /><Skeleton height={360} radius="lg" /></PageShell>
  if (!c) {
    return (
      <PageShell>
        <PageHeader title="Campaña" backHref={ROUTES.TIC.CAMPANIAS} />
        <EmptyState icon={IconCalendarCheck} title="Campaña no encontrada" description="Vuelva al plan preventivo." />
      </PageShell>
    )
  }

  const abierta = c.situacion !== 'cerrada'
  const gestiona = abierta && hasPermiso('gestionar-inventario-ti')
  const registra = abierta && hasPermiso('registrar-mantenimiento')
  const r = c.resumen
  const filas = c.filas.filter(FILTROS[filtro])
  const situacion = SITUACION_CAMPANIA[c.situacion]
  // Lo hecho ya tiene su mantenimiento: no se vuelve a marcar ni se omite.
  const elegibles = seleccion.filter((f) => f.estado !== 'hecho')

  const preguntarCierre = () => confirmar({
    title: `Cerrar «${c.nombre}»`,
    message: r.pendientes > 0
      ? <>Quedan <b>{plural(r.pendientes, 'equipo pendiente', 'equipos pendientes')}</b>: constarán como no realizados. Después la campaña ya no cambia.</>
      : 'Todos los equipos están hechos u omitidos. Después la campaña ya no cambia.',
    confirmLabel: 'Cerrar la campaña',
    destructiva: r.pendientes > 0,
    onConfirm: () => cerrar.mutate(),
  })

  const preguntarQuitar = (f: FilaCampania) => confirmar({
    title: 'Quitar de la campaña',
    message: <>El equipo <b>{f.codigo_institucional}</b> sale de esta campaña.</>,
    confirmLabel: 'Quitar',
    destructiva: true,
    onConfirm: () => quitar.mutate(f.item_id),
  })

  return (
    <PageShell>
      <PageHeader
        title={c.nombre}
        description={`${c.ubicacion ?? ''}${c.unidad ? ` · ${c.unidad}` : ''} · ${formatFecha(c.fecha_inicio)} – ${formatFecha(c.fecha_fin)} · ${c.tecnico ?? ''}`}
        estado={<StatusBadge tone={situacion.tono}>{situacion.etiqueta}</StatusBadge>}
        backHref={ROUTES.TIC.CAMPANIAS}
        actions={gestiona ? (
          <Group gap="xs">
            <Button variant="subtle" leftSection={<IconEdit size={16} />} onClick={() => setEditando(true)}>Editar</Button>
            <Button variant="light" leftSection={<IconLock size={16} />} loading={cerrar.isPending} onClick={preguntarCierre}>Cerrar</Button>
          </Group>
        ) : undefined}
      />

      <SimpleGrid cols={{ base: 2, md: 4 }}>
        <StatCard label="Equipos" value={r.total} icon={IconListCheck} />
        <StatCard label="Hechos" value={r.hechos} icon={IconCircleCheck} tone={r.hechos ? 'success' : undefined} />
        <StatCard label={abierta ? 'Pendientes' : 'No realizados'} value={abierta ? r.pendientes : r.no_realizados} icon={IconAlertTriangle}
          tone={!abierta && r.no_realizados ? 'danger' : c.situacion === 'atrasada' && r.pendientes ? 'warning' : undefined} />
        <StatCard label="Omitidos" value={r.omitidos} icon={IconClockPause} tone={r.omitidos ? 'warning' : undefined} />
      </SimpleGrid>

      <SectionCard title="Qué se hace" description={c.descripcion}>
        <AvanceCampania hechos={r.hechos} total={r.total} omitidos={r.omitidos} />
      </SectionCard>

      <Toolbar actions={(
        <Group gap="xs">
          {gestiona && (
            <Button variant="subtle" leftSection={<IconPlus size={16} />} onClick={() => setAgregando(true)}>Agregar equipos</Button>
          )}
          {registra && (
            <>
              <Button variant="subtle" color={SEMANTIC_COLOR.warning} disabled={!elegibles.length} onClick={() => setOmitiendo(true)}>Omitir</Button>
              <Button variant="light" leftSection={<IconCircleCheck size={16} />} disabled={!elegibles.length}
                onClick={() => setRealizando(elegibles)}>
                {elegibles.length ? `Marcar como hecho (${elegibles.length})` : 'Marcar como hecho'}
              </Button>
            </>
          )}
        </Group>
      )}>
        <SegmentedControl value={filtro} onChange={(v) => { setFiltro(esFiltro(v) ? v : 'todos'); setSeleccion([]) }}
          data={[
            { value: 'todos', label: `Todos (${r.total})` },
            { value: 'pendientes', label: abierta ? 'Pendientes' : 'No realizados' },
            { value: 'hechos', label: 'Hechos' },
            { value: 'omitidos', label: 'Omitidos' },
          ]} />
      </Toolbar>
      {seleccion.length > elegibles.length && (
        <Text size="xs" c="dimmed" mb="xs">Los ya hechos de la selección no se vuelven a marcar.</Text>
      )}
      <SgthTable idAccessor="item_id" records={filas}
        columns={columnasFilasCampania({ onQuitar: gestiona ? preguntarQuitar : undefined })}
        noRecordsText="No hay equipos con este filtro."
        {...(registra ? { selectedRecords: seleccion, onSelectedRecordsChange: setSeleccion } : {})} />

      <RealizarCampaniaModal campania={c} filas={realizando} onClose={() => setRealizando([])} onHecho={() => setSeleccion([])} />
      <MotivoModal
        opened={omitiendo}
        onClose={() => setOmitiendo(false)}
        title="Omitir equipos"
        descripcion={elegibles.length === 1
          ? <>El equipo <b>{elegibles[0].codigo_institucional}</b> queda omitido en esta campaña. Se puede marcar como hecho después.</>
          : <>Los <b>{elegibles.length} equipos elegidos</b> quedan omitidos en esta campaña. Se pueden marcar como hechos después.</>}
        confirmLabel="Omitir"
        placeholder="Ej.: El servidor estaba de vacaciones y el equipo, bajo llave"
        cargando={omitir.isPending}
        onConfirm={(motivo) => omitir.mutate({ items: elegibles.map((f) => f.item_id), motivo }, {
          onSuccess: () => { setOmitiendo(false); setSeleccion([]) },
        })}
      />
      <CampaniaModal opened={editando} campania={c} onClose={() => setEditando(false)} />
      <AgregarEquiposCampaniaModal opened={agregando} campaniaId={c.id} onClose={() => setAgregando(false)} />
    </PageShell>
  )
}
