'use client'

import {
  Stack, Text, Button, Group, Badge,
  Card, ThemeIcon, Skeleton,
} from '@mantine/core'
import {
  IconCertificate, IconPlus,
  IconCalendar, IconUser,
} from '@tabler/icons-react'
import { useDisclosure } from '@mantine/hooks'
import { useCertificadosPorConsulta } from '../hooks/useCertificado'
import { EmitirCertificadoModal } from './EmitirCertificadoModal'
import { EmptyState } from '@/components/ui/EmptyState'
import type { AgendaMedica } from '../services/agendaService'
import type { ConsultaMedica } from '../services/consultaMedicaService'

interface Props {
  turno:    AgendaMedica
  consulta: ConsultaMedica
}

function formatFecha(fecha: string): string {
  return new Date(fecha).toLocaleDateString('es-EC', {
    day: '2-digit', month: 'short', year: 'numeric',
  })
}

export function TabCertificado({ turno, consulta }: Props) {
  const [modalOpened,
    { open: abrirModal, close: cerrarModal }] = useDisclosure(false)

  const { data: certificados = [], isLoading } =
    useCertificadosPorConsulta(consulta.id)

  const esFamiliar = !!turno.carga_familiar_id

  return (
    <Stack gap="md" p="md">
      <Group justify="space-between">
        <Text size="sm" fw={500}>
          Certificados de esta consulta
          {certificados.length > 0 && (
            <Text span c="dimmed" ml={4}>
              ({certificados.length})
            </Text>
          )}
        </Text>
        <Button
          size="xs"
          color="emerald"
          leftSection={<IconPlus size={13} />}
          onClick={abrirModal}
        >
          Nuevo certificado
        </Button>
      </Group>

      {isLoading ? (
        <Skeleton height={80} radius="md" />
      ) : certificados.length === 0 ? (
        <EmptyState
          icon={IconCertificate}
          title="Sin certificados"
          description="No se han emitido certificados
            para esta consulta."
        />
      ) : (
        <Stack gap="sm">
          {certificados.map((cert) => (
            <Card key={cert.id} withBorder radius="md" p="sm">
              <Stack gap="xs">
                <Group justify="space-between">
                  <Group gap="xs">
                    <ThemeIcon
                      size="sm" color="blue" variant="light"
                    >
                      <IconCertificate size={12} />
                    </ThemeIcon>
                    <Text size="sm" fw={500} ff="monospace">
                      {cert.folio}
                    </Text>
                  </Group>
                  <Badge size="sm" variant="light" color="blue">
                    {cert.dias_reposo} día{cert.dias_reposo !== 1
                      ? 's' : ''} de reposo
                  </Badge>
                </Group>

                <Group gap="xs">
                  <IconCalendar size={13} color="gray" />
                  <Text size="xs" c="dimmed">
                    {formatFecha(cert.fecha_inicio)} →{' '}
                    {formatFecha(cert.fecha_fin)}
                  </Text>
                </Group>

                {cert.diagnostico_cie10 && (
                  <Group gap="xs">
                    <Badge
                      size="xs"
                      variant="outline"
                      color="blue"
                      ff="monospace"
                    >
                      {cert.diagnostico_cie10.codigo}
                    </Badge>
                    <Text size="xs" c="dimmed">
                      {cert.diagnostico_cie10.descripcion}
                    </Text>
                  </Group>
                )}

                {cert.observaciones && (
                  <Text size="xs" c="dimmed">
                    {cert.observaciones}
                  </Text>
                )}

                {cert.permiso_servidor && (
                  <Group gap="xs">
                    <IconUser size={13} color="gray" />
                    <Text size="xs" c="dimmed">
                      Permiso generado:{' '}
                      <Text span ff="monospace">
                        {cert.permiso_servidor.folio}
                      </Text>
                    </Text>
                  </Group>
                )}
              </Stack>
            </Card>
          ))}
        </Stack>
      )}

      <EmitirCertificadoModal
        opened={modalOpened}
        onClose={cerrarModal}
        consulta={consulta}
        esFamiliar={esFamiliar}
      />
    </Stack>
  )
}
