'use client'

import { Button, Card, Group, Stack, Text, ThemeIcon } from '@mantine/core'
import { IconDental, IconStethoscope } from '@tabler/icons-react'
import { StatusBadge } from '@/components/ui'
import { formatFechaMes } from '@/lib/fecha'
import type { ConsultaMedica } from '../services/consultaMedicaService'

/** Una consulta en la lista del historial clínico. */
export function ConsultaItem({
  consulta,
  onVerDetalle,
}: {
  consulta: ConsultaMedica;
  onVerDetalle: (id: number) => void;
}) {
  const esOdontologia = consulta.especialidad === "odontologia";

  return (
    <Card withBorder radius="md" p="sm">
      <Stack gap="xs">
        <Group justify="space-between" wrap="nowrap">
          <Group gap="xs">
            {/* El historial mezcla las dos especialidades; hasta ahora no
                había forma de distinguirlas de un vistazo. */}
            <ThemeIcon
              size="sm"
              variant="light"
            >
              {esOdontologia
                ? <IconDental size={12} />
                : <IconStethoscope size={12} />}
            </ThemeIcon>
            <Text size="sm" fw={500}>
              {formatFechaMes(consulta.fecha_consulta)}
            </Text>
            {consulta.especialidad && (
              <StatusBadge size="xs">
                {esOdontologia ? "Odontología" : "Medicina general"}
              </StatusBadge>
            )}
            {consulta.tipo_atencion && (
              <StatusBadge size="xs">
                {consulta.tipo_atencion.replace("_", " ")}
              </StatusBadge>
            )}
            {consulta.tipo_diagnostico && (
              <StatusBadge
                tone={consulta.tipo_diagnostico === 'definitivo' ? 'success' : 'warning'}
                size="xs"
              >
                {consulta.tipo_diagnostico}
              </StatusBadge>
            )}
          </Group>
          <Button
            size="compact-xs"
            variant="subtle"
            onClick={() => onVerDetalle(consulta.id)}
          >
            Ver detalle
          </Button>
        </Group>

        <Text size="xs" c="dimmed">
          Dr. {consulta.medico?.nombre_completo ?? "—"}
        </Text>
      </Stack>
    </Card>
  );
}
