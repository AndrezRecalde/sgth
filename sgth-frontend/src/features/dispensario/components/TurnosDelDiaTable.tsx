'use client'

import { ActionIcon, Button, Stack } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { IconStethoscope, IconX } from '@tabler/icons-react'
import { useState } from 'react'
import { EmptyState, SgthTable, StatusBadge, Toolbar } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useTurnosDelDia, useAccionesTurno } from '../hooks/useAgenda'
import { getConsultasTurnosColumns } from './consultasTurnos.columns'
import { fromDateValueOrUndefined } from '@/lib/fecha'
import type { AgendaMedica } from '../services/agendaService'

interface Props {
  onAtender:     (turno: AgendaMedica) => void
  onVerConsulta: (turno: AgendaMedica) => void
}

export function TurnosDelDiaTable({ onAtender, onVerConsulta }: Props) {
  const contained = useContainedInput('sm')
  const [rango, setRango] = useState<[Date | null, Date | null]>([null, null])
  const [filtroActivo, setFiltroActivo] =
    useState<{ fecha_desde?: string; fecha_hasta?: string } | undefined>(undefined)

  const { data: turnos = [], isLoading } = useTurnosDelDia(filtroActivo)
  const { noPresentado, reactivar } = useAccionesTurno()

  const atendidos = turnos.filter(t => t.estado === 'atendido').length
  const enEspera  = turnos.filter(t =>
    ['en_espera', 'en_sala', 'en_consulta'].includes(t.estado)
  ).length

  const filtrar = () => {
    const [inicio, fin] = rango
    const desde = fromDateValueOrUndefined(inicio as Date | string | null)
    if (desde) {
      setFiltroActivo({
        fecha_desde: desde,
        fecha_hasta: fromDateValueOrUndefined((fin ?? inicio) as Date | string | null)
          ?? desde,
      })
    }
  }

  const limpiar = () => {
    setRango([null, null])
    setFiltroActivo(undefined)
  }

  const columns = getConsultasTurnosColumns({
    onAtender,
    onVerConsulta,
    onNoPresentado: (id) => noPresentado.mutate(id),
    onReactivar:    (id) => reactivar.mutate(id),
  })

  return (
    <Stack gap="md">
      <Toolbar
        actions={
          <>
            <StatusBadge tone="success">
              {atendidos} atendido{atendidos !== 1 ? 's' : ''}
            </StatusBadge>
            <StatusBadge tone="warning">
              {enEspera} en espera
            </StatusBadge>
          </>
        }
      >
        <DatePickerInput
          type="range"
          label="Rango de fechas"
          placeholder="Todo el día de hoy"
          valueFormat="DD/MM/YYYY"
          clearable
          {...contained}
          value={rango}
          onChange={(v) => setRango(v as [Date | null, Date | null])}
          w={260}
        />
        <Button variant="light" onClick={filtrar} disabled={!rango[0]}>
          Filtrar
        </Button>
        {filtroActivo && (
          <ActionIcon
            variant="subtle"
            aria-label="Quitar el filtro de fechas"
            onClick={limpiar}
          >
            <IconX size={14} />
          </ActionIcon>
        )}
      </Toolbar>

      {turnos.length === 0 && !isLoading ? (
        <EmptyState
          icon={IconStethoscope}
          title="Sin turnos"
          description={filtroActivo
            ? "No hay turnos en el rango seleccionado."
            : "No tienes pacientes asignados para hoy."}
        />
      ) : (
        <SgthTable
          records={turnos}
          columns={columns}
          fetching={isLoading}
          minHeight={200}
        />
      )}
    </Stack>
  )
}
