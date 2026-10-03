'use client'

import { Stack, Group, Text, Card, Avatar } from '@mantine/core'
import { IconUser } from '@tabler/icons-react'
import { StatusBadge } from '@/components/ui'
import { useRegistrarSignosVitalesSolicitud } from '../hooks/useSolicitudSignosVitales'
import { etiquetaTipoEvento } from '../services/solicitudCertificacionService'
import type { SolicitudCertificacion } from '../services/solicitudCertificacionService'
import { FormularioSignosVitales } from './FormularioSignosVitales'

interface Props {
  solicitud:  SolicitudCertificacion
  onCreado:   () => void
  onCancelar: () => void
}

/**
 * Los signos vitales previos a la evaluación ocupacional (FEMO). El médico no
 * puede iniciar la ficha hasta que estén.
 *
 * Los pacientes del FEMO son servidores o candidatos a ingresar, todos
 * adultos: nunca se trata como menor.
 */
export function SolicitudSignosVitalesForm({ solicitud, onCreado, onCancelar }: Props) {
  const registrar = useRegistrarSignosVitalesSolicitud()

  const encabezado = (
    <Card withBorder radius="lg" padding="md">
      <Group justify="space-between" wrap="nowrap">
        <Group gap="sm">
          <Avatar radius="xl">
            <IconUser size={16} />
          </Avatar>
          <Stack gap={0}>
            <Text size="sm" fw={600}>
              {solicitud.nombres_paciente}
            </Text>
            <Text size="xs" c="dimmed" ff="monospace">
              {solicitud.cedula_paciente}
            </Text>
          </Stack>
        </Group>
        <StatusBadge>
          {etiquetaTipoEvento(solicitud.tipo_evento)}
        </StatusBadge>
      </Group>
    </Card>
  )

  return (
    <FormularioSignosVitales
      encabezado={encabezado}
      conPerimetro
      esMenor={false}
      consecuencia={{
        critico:  'Hay cifras críticas: avise al médico antes de que el paciente se retire.',
        atencion: 'El médico verá estas cifras al iniciar la evaluación.',
      }}
      textoEnviar="Registrar signos vitales"
      textoCancelar="Cancelar"
      enviando={registrar.isPending}
      enviar={(data) =>
        registrar.mutateAsync({ id: solicitud.id, data }).then(onCreado)
      }
      onCancelar={onCancelar}
    />
  )
}
