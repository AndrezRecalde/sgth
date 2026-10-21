'use client'

import { ActionIcon, Button, Stack } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { IconStethoscope, IconX } from '@tabler/icons-react'
import { useState } from 'react'
import { DataState, SgthTable, StatusBadge, Toolbar } from '@/components/ui'
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

  const { data: turnos = [], isLoading, error, refetch } = useTurnosDelDia(filtroActivo)
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

      {/* Si la cola no carga, lo dice. Antes un fallo dejaba la lista vacía y
          la pantalla afirmaba «No tiene pacientes asignados para hoy». */}
      <DataState
        loading={isLoading}
        error={error}
        errorTitle="No se pudo cargar la cola"
        errorHint="No quiere decir que no tenga pacientes: no se pudo consultar."
        onRetry={() => refetch()}
        empty={turnos.length === 0}
        emptyProps={{
          icon: IconStethoscope,
          title: 'Sin turnos',
          description: filtroActivo
            ? 'No hay turnos en el rango seleccionado.'
            : 'No tiene pacientes asignados para hoy.',
        }}
        skeletonRows={4}
      >
        <SgthTable records={turnos} columns={columns} minHeight={200} />
      </DataState>
    </Stack>
  )
}
