'use client'

import { useState } from 'react'
import { useRouter } from 'next/navigation'
import { Button, Group, SegmentedControl, SimpleGrid, Skeleton } from '@mantine/core'
import { IconAlertTriangle, IconArrowsMove, IconCircleCheck, IconFileTypePdf, IconListCheck, IconLock, IconScan } from '@tabler/icons-react'
import { EmptyState, PageHeader, PageShell, SectionCard, SgthTable, StatCard, StatusBadge } from '@/components/ui'
import { ROUTES } from '@/config/routes'
import { useAuth } from '@/hooks/useAuth'
import { plural } from '@/lib/plural'
import { CerrarConstatacionModal } from '@/features/inventario-ti/components/CerrarConstatacionModal'
import { columnasFilasConstatacion } from '@/features/inventario-ti/components/constataciones.columns'
import { EscaneoConstatacion } from '@/features/inventario-ti/components/EscaneoConstatacion'
import { FirmasActaConstatacion } from '@/features/inventario-ti/components/FirmasActaConstatacion'
import { useConstatacion, useConstatacionMutations } from '@/features/inventario-ti/hooks/useConstataciones'
import type { FilaConstatacion } from '@/features/inventario-ti/services/constatacionesService'

type Filtro = 'todos' | 'pendientes' | 'encontrados' | 'revisar'

const FILTROS: Record<Filtro, (f: FilaConstatacion) => boolean> = {
  todos: () => true,
  pendientes: (f) => f.resultado === 'pendiente' || f.resultado === 'no_encontrado',
  encontrados: (f) => f.resultado === 'en_su_lugar' || f.resultado === 'nuevo',
  revisar: (f) => f.resultado === 'otra_ubicacion' || f.resultado === 'de_baja',
}
const esFiltro = (v: string): v is Filtro => v in FILTROS

/**
 * Una constatación: en el celular, escanear y ver el último equipo; debajo, lo
 * esperado y lo encontrado con su resultado. Al cerrarla, lo pendiente queda
 * como no encontrado y el acta en PDF da constancia.
 */
export function ConstatacionView({ id }: { id: number }) {
  const router = useRouter()
  const { hasPermiso } = useAuth()
  const { data: c, isLoading } = useConstatacion(id)
  const { acta } = useConstatacionMutations(id)
  const [filtro, setFiltro] = useState<Filtro>('todos')
  const [cerrando, setCerrando] = useState(false)

  if (isLoading) return <PageShell><Skeleton height={80} radius="lg" /><Skeleton height={360} radius="lg" /></PageShell>
  if (!c) {
    return (
      <PageShell>
        <PageHeader title="Constatación" backHref={ROUTES.TIC.CONSTATACIONES} />
        <EmptyState icon={IconScan} title="Constatación no encontrada" description="Vuelva a la lista de constataciones." />
      </PageShell>
    )
  }

  const enCurso = c.estado === 'en_curso'
  const gestiona = hasPermiso('gestionar-inventario-ti')
  const r = c.resumen
  const filas = c.filas.filter(FILTROS[filtro])

  return (
    <PageShell>
      <PageHeader
        title={c.nombre}
        description={`${c.ubicacion ?? ''}${c.unidad ? ` · ${c.unidad}` : ''}`}
        estado={<StatusBadge tone={enCurso ? 'info' : 'neutral'}>{enCurso ? 'En curso' : 'Cerrada'}</StatusBadge>}
        backHref={ROUTES.TIC.CONSTATACIONES}
        actions={(
          <Group gap="xs">
            <Button variant="subtle" leftSection={<IconFileTypePdf size={16} />} loading={acta.isPending} onClick={() => acta.mutate()}>Acta</Button>
            {enCurso && gestiona && (
              <Button variant="light" leftSection={<IconLock size={16} />} onClick={() => setCerrando(true)}>Cerrar</Button>
            )}
          </Group>
        )}
      />

      <SimpleGrid cols={{ base: 2, md: 4 }}>
        <StatCard label="Registrados aquí" value={r.esperados} icon={IconListCheck} />
        <StatCard label="En su lugar" value={r.en_su_lugar + r.nuevos} icon={IconCircleCheck} tone={r.en_su_lugar + r.nuevos ? 'success' : undefined}
          hint={r.nuevos ? plural(r.nuevos, 'registrado en sitio', 'registrados en sitio') : undefined} />
        <StatCard label="De otra ubicación" value={r.otra_ubicacion} icon={IconArrowsMove} tone={r.otra_ubicacion ? 'warning' : undefined} />
        <StatCard label={enCurso ? 'Pendientes' : 'No encontrados'} value={enCurso ? r.pendientes : r.no_encontrados} icon={IconAlertTriangle}
          tone={!enCurso && r.no_encontrados ? 'danger' : undefined} />
      </SimpleGrid>

      {enCurso && gestiona && (
        <EscaneoConstatacion constatacionId={c.id}
          onRegistrarEnSitio={(codigo) => router.push(ROUTES.TIC.EQUIPO_EN_SITIO(c.id, codigo, c.ubicacion_id))} />
      )}

      <SectionCard title="Equipos" description="Los que el sistema tiene en esta ubicación y los que se encontraron aquí.">
        <SegmentedControl mb="sm" value={filtro} onChange={(v) => setFiltro(esFiltro(v) ? v : 'todos')}
          data={[
            { value: 'todos', label: `Todos (${c.filas.length})` },
            { value: 'pendientes', label: enCurso ? 'Pendientes' : 'No encontrados' },
            { value: 'encontrados', label: 'En su lugar' },
            { value: 'revisar', label: 'A revisar' },
          ]} />
        <SgthTable idAccessor="bien_id" records={filas} columns={columnasFilasConstatacion}
          noRecordsText="No hay equipos con este filtro." />
      </SectionCard>

      <FirmasActaConstatacion constatacion={c} gestiona={gestiona} />

      <CerrarConstatacionModal constatacion={cerrando ? c : null} onClose={() => setCerrando(false)} />
    </PageShell>
  )
}
