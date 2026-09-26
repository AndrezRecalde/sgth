'use client'

import { Alert, Button, Group, Skeleton, Text } from '@mantine/core'
import { IconAlertTriangle, IconStethoscope } from '@tabler/icons-react'
import { DetailList, SectionCard, StatusBadge } from '@/components/ui'
import { formatFecha } from '@/lib/fecha'
import {
  DICTAMEN_LABELS, TONO_DICTAMEN,
} from '@/features/dispensario/services/solicitudCertificacionService'
import {
  ANIOS_ENTRE_EVALUACIONES, type AptitudVigente,
} from '../utils/aptitudMedica'

interface Props {
  aptitud: AptitudVigente | null
  cargando: boolean
  /** Abre el formulario para enviar al servidor a evaluarse. */
  onSolicitar: () => void
  puedeSolicitar: boolean
}

/**
 * Qué dice salud ocupacional sobre esta persona, de un vistazo.
 *
 * Antes había que leer la fila más reciente de la tabla para saber si alguien
 * estaba apto, y las restricciones —lo que de verdad condiciona dónde se le
 * puede ubicar— no llegaban al frontend.
 */
export function AptitudVigentePanel({
  aptitud, cargando, onSolicitar, puedeSolicitar,
}: Props) {
  const accion = puedeSolicitar ? (
    <Button
      size="xs"
      variant="light"
      leftSection={<IconStethoscope size={14} />}
      onClick={onSolicitar}
    >
      Solicitar evaluación
    </Button>
  ) : undefined

  if (cargando) {
    return (
      <SectionCard title="Aptitud vigente">
        <Skeleton height={64} radius="md" />
      </SectionCard>
    )
  }

  if (!aptitud) {
    return (
      <SectionCard title="Aptitud vigente" actions={accion}>
        <Text size="sm" c="dimmed">
          Sin evaluaciones médicas ocupacionales completadas. La aptitud
          aparecerá aquí en cuanto el Dispensario emita el primer dictamen.
        </Text>
      </SectionCard>
    )
  }

  return (
    <SectionCard title="Aptitud vigente" actions={accion}>
      {/* Un aviso, no un bloqueo: Talento Humano envía a evaluación cuando lo
          cree necesario, así que el vencimiento solo sugiere. */}
      {aptitud.vencida && (
        <Alert
          variant="light"
          color="amber"
          icon={<IconAlertTriangle size={16} />}
          mb="md"
        >
          Han pasado más de {ANIOS_ENTRE_EVALUACIONES} años desde la última
          evaluación. Corresponde una periódica.
        </Alert>
      )}

      <DetailList
        columnas={3}
        items={[
          {
            label: 'Dictamen',
            value: (
              <StatusBadge tone={TONO_DICTAMEN[aptitud.dictamen] ?? 'neutral'}>
                {DICTAMEN_LABELS[aptitud.dictamen] ?? aptitud.dictamen}
              </StatusBadge>
            ),
          },
          {
            label: 'Última evaluación',
            value: formatFecha(aptitud.fechaEvaluacion),
          },
          {
            label: 'Siguiente periódica',
            value: (
              <Group gap="xs" wrap="nowrap">
                {formatFecha(aptitud.venceEl)}
                {aptitud.vencida && <StatusBadge tone="warning">Vencida</StatusBadge>}
              </Group>
            ),
          },
          {
            label: 'Restricciones',
            value: aptitud.restricciones,
            ancho: true,
          },
        ]}
      />
    </SectionCard>
  )
}
