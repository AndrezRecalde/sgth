'use client'

import { Stack, Group, Text, Card, Avatar } from '@mantine/core'
import { IconUser, IconUsers } from '@tabler/icons-react'
import { StatusBadge } from '@/components/ui'
import { useRegistrarTriaje } from '../hooks/useTriaje'
import { UltimoTriajeReferencia } from './UltimoTriajeReferencia'
import { TomasPreviasTriaje } from './TomasPreviasTriaje'
import { FormularioSignosVitales } from './FormularioSignosVitales'
import { EDAD_ADULTO, edadEnAnios } from '../constants/signosVitales'
import type { AgendaMedica } from '../services/agendaService'
import type { Triaje } from '../services/triajeService'

interface Props {
  turno:      AgendaMedica
  onCreado:   (triaje: Triaje) => void
  onCancelar: () => void
}

/** El triaje de un turno: signos vitales antes de pasar con el profesional. */
export function TriajeForm({ turno, onCreado, onCancelar }: Props) {
  const registrar = useRegistrarTriaje()

  const esServidor = !!turno.servidor_id
  const nombrePaciente = esServidor
    ? `${turno.servidor?.nombre ?? ''} ${turno.servidor?.apellido ?? ''}`
    : `${turno.carga_familiar?.nombres ?? ''} ${turno.carga_familiar?.apellidos ?? ''}`
  const edad = edadEnAnios(
    esServidor ? turno.servidor?.fecha_nacimiento : turno.carga_familiar?.fecha_nacimiento,
  )

  const encabezado = (
    <Card withBorder radius="lg" padding="md">
      <Stack gap="xs">
        <Group justify="space-between" wrap="nowrap">
          <Group gap="sm">
            <Avatar radius="xl">
              {esServidor ? <IconUser size={16} /> : <IconUsers size={16} />}
            </Avatar>
            <Stack gap={0}>
              <Text size="sm" fw={600}>
                {nombrePaciente.trim() || '—'}
              </Text>
              <Text size="xs" c="dimmed">
                <Text span ff="monospace" inherit>{turno.folio}</Text>
                {edad !== null && ` · ${edad} ${edad === 1 ? 'año' : 'años'}`}
              </Text>
            </Stack>
          </Group>
          <StatusBadge>
            {turno.tipo_atencion === 'medicina_general' ? 'Medicina General' : 'Odontología'}
          </StatusBadge>
        </Group>
        {/* Lo que se escribió al crear el turno: orienta qué mirar al medir. */}
        {turno.motivo_solicitud && (
          <Text size="xs">
            <Text span c="dimmed" inherit>Motivo: </Text>
            {turno.motivo_solicitud}
          </Text>
        )}
      </Stack>
    </Card>
  )

  return (
    <FormularioSignosVitales
      encabezado={encabezado}
      contexto={
        <>
          <TomasPreviasTriaje agendaId={turno.id} />
          <UltimoTriajeReferencia agendaId={turno.id} />
        </>
      }
      esMenor={edad !== null && edad < EDAD_ADULTO}
      consecuencia={{
        critico:  'El turno quedará marcado como crítico en la cola. Valore si el paciente puede esperar.',
        atencion: 'El turno quedará marcado en la cola para que el profesional lo vea.',
      }}
      textoEnviar="Registrar triaje"
      textoCancelar="Cancelar"
      enviando={registrar.isPending}
      enviar={(data) =>
        registrar.mutateAsync({ agendaId: turno.id, data }).then(onCreado)
      }
      onCancelar={onCancelar}
    />
  )
}
