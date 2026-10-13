'use client'

import { useState } from 'react'
import { useRouter } from 'next/navigation'
import { ActionIcon, Button, Group, SegmentedControl, Select, Text } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { IconChevronLeft, IconChevronRight, IconListDetails, IconPresentation } from '@tabler/icons-react'
import { DataState, PageHeader, PageShell, SectionCard, Toolbar } from '@/components/ui'
import { ROUTES } from '@/config/routes'
import { useContainedInput } from '@/hooks/useContainedInput'
import { fromDateValueOrNull, toDateValue } from '@/lib/fecha'
import { AgendaEquipos } from '@/features/prestamos-ti/components/AgendaEquipos'
import { SolicitarPrestamoModal, type PropuestaSolicitud } from '@/features/prestamos-ti/components/SolicitarPrestamoModal'
import { useDisponibilidadPortal, useReglasPrestamoPortal } from '@/features/prestamos-ti/hooks/useMisPrestamos'
import type { EquipoPrestable } from '@/features/prestamos-ti/services/tipos'
import { esFinDeSemana, isoDia, semanaDe, sumarDiasIso } from '@/features/prestamos-ti/utils/agenda'

type Vista = 'dia' | 'semana'

/** Una hora después de `inicio` (`YYYY-MM-DDTHH:mm`), para proponer la devolución. */
function unaHoraDespues(inicio: string): string {
  const [dia, hhmm] = inicio.split('T')
  const [h, m] = hhmm.split(':').map(Number)
  return `${dia}T${String(Math.min(h + 1, 23)).padStart(2, '0')}:${String(m).padStart(2, '0')}`
}

/** Hoy, o el lunes si hoy es fin de semana: lo normal es que TIC no preste esos días. */
function primerDia(): string {
  let dia = isoDia(new Date())
  while (esFinDeSemana(dia)) dia = sumarDiasIso(dia, 1)
  return dia
}

/**
 * La disponibilidad de los equipos de préstamo: cómo está cada uno ahora y su
 * horario del día o de la semana. Se marcan los que se necesitan, o se hace
 * clic en un horario libre, y se pide. No se ve quién tiene cada equipo (D15).
 */
export function DisponibilidadView() {
  const router = useRouter()
  const compacto = useContainedInput('sm')
  const { data: reglas } = useReglasPrestamoPortal()
  const conFinDeSemana = reglas?.presta_fines_de_semana ?? false

  const [vista, setVista] = useState<Vista>('dia')
  const [dia, setDia] = useState(primerDia)
  const [tipo, setTipo] = useState<string | null>(null)
  const [seleccion, setSeleccion] = useState<number[]>([])
  const [propuesta, setPropuesta] = useState<PropuestaSolicitud | null>(null)

  const dias = vista === 'dia' ? [dia] : semanaDe(dia, conFinDeSemana)
  const { data: equipos = [], isLoading, error } = useDisponibilidadPortal(dias[0], dias[dias.length - 1])

  const tipos = [...new Map(equipos.map((e) => [String(e.tipo_bien_id), e.tipo ?? 'Sin tipo'])).entries()]
    .map(([value, label]) => ({ value, label }))
  const visibles = tipo ? equipos.filter((e) => String(e.tipo_bien_id) === tipo) : equipos

  const mover = (n: number) => setDia((d) => sumarDiasIso(d, vista === 'dia' ? n : n * 7))
  const alternar = (id: number) => setSeleccion((s) => (s.includes(id) ? s.filter((x) => x !== id) : [...s, id]))
  const elegir = (e: EquipoPrestable, inicio: string) =>
    setPropuesta({ bienes: [...new Set([...seleccion, e.id])], inicio, fin: unaHoraDespues(inicio) })

  return (
    <PageShell>
      <PageHeader
        title="Préstamo de equipos"
        description={reglas
          ? `Proyectores, parlantes y otros equipos de TIC. Se prestan de ${reglas.hora_apertura} a ${reglas.hora_cierre}, hasta ${reglas.plazo_maximo_dias_habiles} día(s) hábil(es).`
          : 'Proyectores, parlantes y otros equipos de TIC.'}
        actions={(
          <Group gap="xs">
            <Button variant="subtle" leftSection={<IconListDetails size={16} />} onClick={() => router.push(ROUTES.PORTAL.MIS_PRESTAMOS)}>
              Mis préstamos
            </Button>
            <Button leftSection={<IconPresentation size={16} />} disabled={seleccion.length === 0}
              onClick={() => setPropuesta({ bienes: seleccion })}>
              Pedir {seleccion.length > 0 ? `los ${seleccion.length} elegidos` : 'equipos'}
            </Button>
          </Group>
        )}
      />

      <Toolbar>
        <SegmentedControl value={vista} onChange={(v) => setVista(v === 'semana' ? 'semana' : 'dia')}
          data={[{ value: 'dia', label: 'Día' }, { value: 'semana', label: 'Semana' }]} />
        <Group gap={4} wrap="nowrap">
          <ActionIcon variant="subtle" onClick={() => mover(-1)} aria-label="Anterior"><IconChevronLeft size={16} /></ActionIcon>
          <DatePickerInput aria-label="Día" valueFormat="DD/MM/YYYY" {...compacto}
            value={toDateValue(dia)} onChange={(d) => setDia(fromDateValueOrNull(d) ?? dia)} />
          <ActionIcon variant="subtle" onClick={() => mover(1)} aria-label="Siguiente"><IconChevronRight size={16} /></ActionIcon>
        </Group>
        {tipos.length > 1 && (
          <Select aria-label="Tipo de equipo" placeholder="Todos los tipos" data={tipos} clearable {...compacto}
            value={tipo} onChange={setTipo} />
        )}
      </Toolbar>

      <DataState
        loading={isLoading || !reglas}
        error={error}
        empty={visibles.length === 0}
        emptyProps={{
          icon: IconPresentation,
          title: 'TIC no tiene equipos de préstamo',
          description: 'Cuando la Unidad de TIC marque equipos para prestar, aparecerán aquí.',
        }}
      >
        {reglas && (
          <SectionCard title={vista === 'dia' ? 'Horario del día' : 'Horario de la semana'}>
            <Text size="xs" c="dimmed" mb="sm">
              Marque los equipos que necesita, o haga clic en un horario libre de su barra. Lo pintado ya está ocupado.
            </Text>
            <AgendaEquipos equipos={visibles} dias={dias} reglas={reglas} seleccion={seleccion}
              onSeleccionar={alternar} onElegir={elegir} />
          </SectionCard>
        )}
      </DataState>

      <SolicitarPrestamoModal propuesta={propuesta} equipos={equipos} reglas={reglas}
        onClose={() => setPropuesta(null)} onEnviada={() => setSeleccion([])} />
    </PageShell>
  )
}
