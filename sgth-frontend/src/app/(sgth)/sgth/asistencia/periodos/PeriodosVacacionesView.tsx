'use client'

import { IconLock } from '@tabler/icons-react'
import { EmptyState, PageHeader, PageShell, SectionHeading } from '@/components/ui'
import { useAuth } from '@/hooks/useAuth'
import { ConsultaPeriodosServidor } from '@/features/asistencia/components/ConsultaPeriodosServidor'
import { GenerarPeriodosCard } from '@/features/asistencia/components/GenerarPeriodosCard'
import { TopeAcumulacionCard } from '@/features/asistencia/components/TopeAcumulacionCard'

export function PeriodosVacacionesView() {
  const { hasPermiso } = useAuth()
  const puedeGestionar = hasPermiso('gestionar-vacaciones')
  // El seguimiento del tope es institucional: lo ve quien ve la asistencia de
  // toda la institución, que es lo que exige `VacacionPolicy::verTodas`. Hoy
  // ningún rol tiene uno sin el otro, pero pedir la lista sin el permiso
  // devolvía un 403 que se pintaba como un error de la pantalla.
  const veLaInstitucion = hasPermiso('ver-asistencia-todos')

  return (
    <PageShell>
      <PageHeader
        title="Períodos de vacaciones"
        description="Generación de períodos, saldos por servidor y tope de acumulación"
      />

      {/* El menú ya filtra por `gestionar-vacaciones`, pero entrar por URL
          pintaba la pantalla entera con sus tres botones: el 403 del backend
          protege el dato, no la expectativa de quien pulsa. */}
      {!puedeGestionar && !veLaInstitucion ? (
        <EmptyState
          icon={IconLock}
          title="No tiene acceso a los períodos de vacaciones"
          description="Esta pantalla genera y recalcula saldos de vacaciones. Solicite el permiso a Talento Humano si necesita consultarla."
        />
      ) : (
        <>
          {puedeGestionar && <GenerarPeriodosCard />}

          {veLaInstitucion && <TopeAcumulacionCard />}

          <SectionHeading title="Consulta por servidor" />
          <ConsultaPeriodosServidor puedeGestionar={puedeGestionar} />
        </>
      )}
    </PageShell>
  )
}
