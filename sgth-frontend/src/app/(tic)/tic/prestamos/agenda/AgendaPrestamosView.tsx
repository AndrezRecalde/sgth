'use client'

import { useState } from 'react'
import { ActionIcon, Group, SegmentedControl, Text } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { IconChevronLeft, IconChevronRight, IconPresentation } from '@tabler/icons-react'
import { DataState, PageHeader, PageShell, SectionCard, Toolbar } from '@/components/ui'
import { ROUTES } from '@/config/routes'
import { useContainedInput } from '@/hooks/useContainedInput'
import { fromDateValueOrNull, toDateValue } from '@/lib/fecha'
import { AgendaEquipos } from '@/features/prestamos-ti/components/AgendaEquipos'
import { useAgendaTic, useReglasPrestamoTic } from '@/features/prestamos-ti/hooks/usePrestamosTic'
import { isoDia, semanaDe, sumarDiasIso } from '@/features/prestamos-ti/utils/agenda'

/**
 * La agenda de TIC: la ocupación de cada equipo prestable por día o por
 * semana. A diferencia del Portal, cada bloque dice el folio y quién lo tiene.
 */
export function AgendaPrestamosView() {
  const compacto = useContainedInput('sm')
  const { data: reglas } = useReglasPrestamoTic()
  const [vista, setVista] = useState<'dia' | 'semana'>('semana')
  const [dia, setDia] = useState(() => isoDia(new Date()))

  const dias = vista === 'dia' ? [dia] : semanaDe(dia, reglas?.presta_fines_de_semana ?? false)
  const { data: equipos = [], isLoading, error } = useAgendaTic(dias[0], dias[dias.length - 1])
  const mover = (n: number) => setDia((d) => sumarDiasIso(d, vista === 'dia' ? n : n * 7))

  return (
    <PageShell>
      <PageHeader title="Agenda de préstamos" description="La ocupación de cada equipo prestable" backHref={ROUTES.TIC.PRESTAMOS} />
      <Toolbar>
        <SegmentedControl value={vista} onChange={(v) => setVista(v === 'dia' ? 'dia' : 'semana')}
          data={[{ value: 'dia', label: 'Día' }, { value: 'semana', label: 'Semana' }]} />
        <Group gap={4} wrap="nowrap">
          <ActionIcon variant="subtle" onClick={() => mover(-1)} aria-label="Anterior"><IconChevronLeft size={16} /></ActionIcon>
          <DatePickerInput aria-label="Día" valueFormat="DD/MM/YYYY" {...compacto}
            value={toDateValue(dia)} onChange={(d) => setDia(fromDateValueOrNull(d) ?? dia)} />
          <ActionIcon variant="subtle" onClick={() => mover(1)} aria-label="Siguiente"><IconChevronRight size={16} /></ActionIcon>
        </Group>
      </Toolbar>
      <DataState
        loading={isLoading || !reglas}
        error={error}
        empty={equipos.length === 0}
        emptyProps={{
          icon: IconPresentation,
          title: 'No hay equipos de préstamo',
          description: 'Marque un equipo como prestable desde su ficha, en Inventario › Equipos.',
        }}
      >
        {reglas && (
          <SectionCard title={vista === 'dia' ? 'Horario del día' : 'Horario de la semana'}>
            <Text size="xs" c="dimmed" mb="sm">Pase el cursor sobre lo ocupado para ver el folio y quién lo tiene.</Text>
            <AgendaEquipos equipos={equipos} dias={dias} reglas={reglas} />
          </SectionCard>
        )}
      </DataState>
    </PageShell>
  )
}
