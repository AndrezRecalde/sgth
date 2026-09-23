'use client'

import { confirmar, Toolbar } from '@/components/ui'
import { useState } from 'react'
import {
  Stack, Box, Chip,
  Group, Button,
} from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { IconVaccine } from '@tabler/icons-react'
import { useDisclosure } from '@mantine/hooks'
import { useContainedInput } from '@/hooks/useContainedInput'
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
  const contained = useContainedInput('sm')
  const [fecha, setFecha] = useState<Date | null>(new Date())
  const [vista, setVista] = useState<VistaMonitoreo>('todos')
  const [turnoTriaje, setTurnoTriaje] = useState<AgendaMedica | null>(null)
  const [soloAlertas, setSoloAlertas] = useState(false)
  const [drawerOpened, { open: abrirDrawer, close: cerrarDrawer }] =
    useDisclosure(false)

  const fechaStr = formatFechaLocal(fecha ?? new Date())

  const { data, isLoading } = useColaTurnos({ fecha: fechaStr })
  const { data: pendientesTriaje = [] } = useTriajesPendientes()
  const cancelar = useCancelarTurno()

  const turnosDelDia = data?.data ?? []

  const conAlerta = turnosDelDia.filter((t) =>
    t.triaje?.nivel_alerta === 'critico' ||
    t.triaje?.nivel_alerta === 'atencion'
  )

  // El orden no cambia solo: quién pasa antes lo decide quien atiende. Este
  // filtro solo permite mirar primero a los que el triaje marcó, sin que nadie
  // quede desplazado de la cola sin saber por qué.
  const turnos = soloAlertas ? conAlerta : turnosDelDia

  // Si se eligió tomar el triaje de un turno
  // desde la lista de pendientes
  if (turnoTriaje) {
    return (
      // Mismo ancho de lectura que tenía el `Container`, alineado a la
      // izquierda como el título. La cola sí ocupa todo el ancho.
      <Box maw={720}>
        <TriajeForm
          turno={turnoTriaje}
          onCreado={() => setTurnoTriaje(null)}
          onCancelar={() => setTurnoTriaje(null)}
        />
      </Box>
    )
  }

  return (
    <Stack gap="md">
      <Toolbar
        actions={
          <Button
            variant="light"
            leftSection={<IconVaccine size={16} />}
            onClick={abrirDrawer}
          >
            Servicios de enfermería
          </Button>
        }
      >
        {/* Los tres chips salen del principal del subsistema: son el mismo
            gesto —recortar la lista— y el color no distingue un filtro de
            otro. Lo que está fuera de rango ya se ve en la columna «Estado»,
            en rojo y con su icono. */}
        <Group gap="xs">
          <Chip
            checked={vista === 'todos'}
            onChange={() => setVista('todos')}
            size="sm"
          >
            Todos los turnos
          </Chip>
          <Chip
            checked={vista === 'pendientes_triaje'}
            onChange={() => setVista('pendientes_triaje')}
            size="sm"
          >
            Pendientes de triaje
            {pendientesTriaje.length > 0 ? ` (${pendientesTriaje.length})` : ''}
          </Chip>
          {vista === 'todos' && conAlerta.length > 0 && (
            <Chip
              checked={soloAlertas}
              onChange={() => setSoloAlertas((v) => !v)}
              size="sm"
            >
              Con alerta ({conAlerta.length})
            </Chip>
          )}
        </Group>

        {vista === 'todos' && (
          <DatePickerInput
            label="Fecha"
            {...contained}
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
      </Toolbar>

      {vista === 'todos' && (
        <ColaTurnosTable
          turnos={turnos}
          isLoading={isLoading}
          onCancelar={(id) => confirmar({
            title:   'Cancelar turno',
            message: 'Se cancelará este turno y el paciente saldrá de la cola.',
            destructiva: true,
            confirmLabel: 'Cancelar turno',
            cancelLabel:  'Volver',
            onConfirm: () => cancelar.mutate(id),
          })}
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
