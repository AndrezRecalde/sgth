'use client'

import { confirmar, Toolbar } from '@/components/ui'
import { useState } from 'react'
import { Stack, Box, Chip, Group, Button } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { IconVaccine } from '@tabler/icons-react'
import { useDisclosure } from '@mantine/hooks'
import { useContainedInput } from '@/hooks/useContainedInput'
import { TriajeForm } from '@/features/dispensario/components/TriajeForm'
import { AtencionesEnfermeriaDrawer } from '@/features/dispensario/components/AtencionesEnfermeriaDrawer'
import { ColaTurnosTable } from '@/features/dispensario/components/ColaTurnosTable'
import { TriajePendientesList } from '@/features/dispensario/components/TriajePendientesList'
import { useColaTurnos, useCancelarTurno } from '@/features/dispensario/hooks/useAgenda'
import { useTriajesPendientes } from '@/features/dispensario/hooks/useTriaje'
import { usePuedeTriar } from '@/features/dispensario/hooks/usePuedeTriar'
import type { AgendaMedica } from '@/features/dispensario/services/agendaService'
import { fromDateValue, hoyIso } from '@/lib/fecha'

type VistaMonitoreo = 'todos' | 'pendientes_triaje'

export function EnfermeriaColaMonitoreoView() {
  const contained = useContainedInput('sm')
  const [fecha, setFecha] = useState(hoyIso)
  const [vista, setVista] = useState<VistaMonitoreo>('todos')
  const [turnoTriaje, setTurnoTriaje] = useState<AgendaMedica | null>(null)
  const [soloAlertas, setSoloAlertas] = useState(false)
  const [drawerOpened, { open: abrirDrawer, close: cerrarDrawer }] =
    useDisclosure(false)

  const puedeTriar = usePuedeTriar()

  const cola = useColaTurnos({ fecha, per_page: 200 })
  const { data: pendientesTriaje = [] } = useTriajesPendientes()
  const cancelar = useCancelarTurno()

  const turnosDelDia = cola.data?.data ?? []
  const conAlerta = turnosDelDia.filter((t) =>
    t.triaje?.nivel_alerta === 'critico' ||
    t.triaje?.nivel_alerta === 'atencion'
  )

  // El orden no cambia solo: quién pasa antes lo decide quien atiende. Este
  // filtro solo permite mirar primero a los que el triaje marcó, sin que nadie
  // quede desplazado de la cola sin saber por qué.
  const turnos = soloAlertas ? conAlerta : turnosDelDia

  const pedirCancelacion = (id: number) => confirmar({
    title:   'Cancelar turno',
    message: 'Se cancelará este turno y el paciente saldrá de la cola.',
    destructiva: true,
    confirmLabel: 'Cancelar turno',
    cancelLabel:  'Volver',
    onConfirm: () => cancelar.mutate(id),
  })

  if (turnoTriaje) {
    return (
      // Mismo ancho de lectura que el resto de formularios, alineado a la
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
            onChange={() => { setVista('pendientes_triaje'); setSoloAlertas(false) }}
            size="sm"
          >
            Pendientes de triaje
            {pendientesTriaje.length > 0 ? ` (${pendientesTriaje.length})` : ''}
          </Chip>
          {/* Se queda a la vista mientras esté activo aunque la cuenta baje a
              cero: antes desaparecía con el filtro puesto —al cambiar de fecha
              o al re-triar al único marcado— y la cola quedaba vacía sin forma
              de quitarlo. */}
          {vista === 'todos' && (conAlerta.length > 0 || soloAlertas) && (
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
            onChange={(v) => setFecha(v ? fromDateValue(v) : hoyIso())}
            valueFormat="DD/MM/YYYY"
            maw={200}
          />
        )}
      </Toolbar>

      {vista === 'todos' && (
        <ColaTurnosTable
          turnos={turnos}
          isLoading={cola.isLoading}
          error={cola.error}
          onReintentar={() => cola.refetch()}
          ahora={cola.dataUpdatedAt}
          vacio={soloAlertas
            ? { titulo: 'Sin turnos con alerta', descripcion: 'Ningún triaje de esta fecha quedó fuera de rango.' }
            : { titulo: 'Sin turnos', descripcion: 'No hay turnos registrados en esta fecha.' }}
          onCancelar={pedirCancelacion}
          onTomarTriaje={puedeTriar ? setTurnoTriaje : undefined}
        />
      )}

      {vista === 'pendientes_triaje' && (
        <TriajePendientesList
          onSeleccionar={puedeTriar ? setTurnoTriaje : undefined}
          onCancelar={pedirCancelacion}
        />
      )}

      <AtencionesEnfermeriaDrawer
        opened={drawerOpened}
        onClose={cerrarDrawer}
      />
    </Stack>
  )
}
