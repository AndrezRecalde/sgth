'use client'

import { useState } from 'react'
import {
  Stack, Container, Chip,
  Group, ActionIcon,
} from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import '@mantine/dates/styles.css'
import { IconVaccine } from '@tabler/icons-react'
import { useDisclosure } from '@mantine/hooks'
import { TriajeForm } from '@/features/dispensario/components/TriajeForm'
import { AtencionesEnfermeriaDrawer } from '@/features/dispensario/components/AtencionesEnfermeriaDrawer'
import { ColaTurnosTable } from '@/features/dispensario/components/ColaTurnosTable'
import { TriajePendientesList } from '@/features/dispensario/components/TriajePendientesList'
import {
  useColaTurnos,
  useCancelarTurno,
} from '@/features/dispensario/hooks/useAgenda'
import { useTriajesPendientes } from '@/features/dispensario/hooks/useTriaje'
import type { AgendaMedica } from '@/features/dispensario/services/agendaService'

function formatFechaLocal(d: Date): string {
  const year = d.getFullYear()
  const month = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

type VistaMonitoreo = 'todos' | 'pendientes_triaje'

export function EnfermeriaColaMonitoreoView() {
  const [fecha, setFecha] = useState<Date | null>(new Date())
  const [vista, setVista] = useState<VistaMonitoreo>('todos')
  const [turnoTriaje, setTurnoTriaje] = useState<AgendaMedica | null>(null)
  const [drawerOpened, { open: abrirDrawer, close: cerrarDrawer }] =
    useDisclosure(false)

  const fechaStr = formatFechaLocal(fecha ?? new Date())

  const { data, isLoading } = useColaTurnos({ fecha: fechaStr })
  const { data: pendientesTriaje = [] } = useTriajesPendientes()
  const cancelar = useCancelarTurno()

  const turnos = data?.data ?? []

  // Si se eligió tomar el triaje de un turno
  // desde la lista de pendientes
  if (turnoTriaje) {
    return (
      <Container size="sm" px={0}>
        <TriajeForm
          turno={turnoTriaje}
          onCreado={() => setTurnoTriaje(null)}
          onCancelar={() => setTurnoTriaje(null)}
        />
      </Container>
    )
  }

  return (
    <Stack gap="md">
      <Group justify="space-between" wrap="wrap">
        <Group gap="xs">
          <Chip
            checked={vista === 'todos'}
            onChange={() => setVista('todos')}
            color="blue"
            size="sm"
          >
            Todos los turnos
          </Chip>
          <Chip
            checked={vista === 'pendientes_triaje'}
            onChange={() => setVista('pendientes_triaje')}
            color="orange"
            size="sm"
          >
            Pendientes de triaje
            {pendientesTriaje.length > 0 ? ` (${pendientesTriaje.length})` : ''}
          </Chip>
        </Group>
        <ActionIcon
          size="xl"
          variant="light"
          color="violet"
          onClick={abrirDrawer}
          title="Servicios de enfermería"
        >
          <IconVaccine size={14} />
        </ActionIcon>

        {vista === 'todos' && (
          <DatePickerInput
            label="Fecha"
            value={fecha}
            onChange={(v) => {
              if (!v) {
                setFecha(new Date())
                return
              }
              const str = typeof v === 'string' ? v : String(v)
              const [y, m, d] = str.slice(0, 10).split('-').map(Number)
              setFecha(new Date(y, m - 1, d))
            }}
            valueFormat="DD/MM/YYYY"
            maw={200}
          />
        )}
      </Group>

      {vista === 'todos' && (
        <ColaTurnosTable
          turnos={turnos}
          isLoading={isLoading}
          onCancelar={(id) => {
            if (confirm('¿Cancelar este turno?')) {
              cancelar.mutate(id)
            }
          }}
          onTomarTriaje={(turno) => setTurnoTriaje(turno)}
        />
      )}

      {vista === 'pendientes_triaje' && (
        <TriajePendientesList
          onSeleccionar={(turno) => setTurnoTriaje(turno)}
        />
      )}

      <AtencionesEnfermeriaDrawer
        opened={drawerOpened}
        onClose={cerrarDrawer}
      />
    </Stack>
  )
}
