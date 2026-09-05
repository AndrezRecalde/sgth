'use client'

import {
  Stack, Text, Button, Group, Badge,
  Card, ThemeIcon, Skeleton,
} from '@mantine/core'
import {
  IconCertificate, IconPlus,
  IconCalendar, IconUser, IconDownload, IconBan,
} from '@tabler/icons-react'
import { useState } from 'react'
import { useDisclosure } from '@mantine/hooks'
import {
  useCertificadosPorConsulta, useAnularCertificado,
  useDescargarCertificado,
} from '../hooks/useCertificado'
import { EmitirCertificadoModal } from './EmitirCertificadoModal'
import {
  AnularRegistroModal, MOTIVOS_ANULAR_CERTIFICADO,
} from './AnularRegistroModal'
import { EmptyState } from '@/components/ui/EmptyState'
import type { AgendaMedica } from '../services/agendaService'
import type { ConsultaMedica } from '../services/consultaMedicaService'
import type { CertificadoMedico } from '../services/certificadoService'

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
  const anular = useAnularCertificado(consulta.id)
  const { descargar, descargando } = useDescargarCertificado()
  const [aAnular, setAAnular] = useState<CertificadoMedico | null>(null)

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
          {certificados.map((cert) => {
            const anulado = !!cert.anulado_en
            return (
            <Card
              key={cert.id}
              withBorder
              radius="md"
              p="sm"
              style={{ opacity: anulado ? 0.65 : 1 }}
            >
              <Stack gap="xs">
                <Group justify="space-between">
                  <Group gap="xs">
                    <ThemeIcon
                      size="sm"
                      color={anulado ? 'gray' : 'blue'}
                      variant="light"
                    >
                      <IconCertificate size={12} />
                    </ThemeIcon>
                    <Text
                      size="sm"
                      fw={500}
                      ff="monospace"
                      td={anulado ? 'line-through' : undefined}
                    >
                      {cert.folio}
                    </Text>
                    {anulado && (
                      <Badge size="xs" variant="light" color="orange">
                        Anulado
                      </Badge>
                    )}
                  </Group>
                  <Badge
                    size="sm"
                    variant="light"
                    color={anulado ? 'gray' : 'blue'}
                  >
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
                      {anulado && ' — anulado con el certificado'}
                    </Text>
                  </Group>
                )}

                {anulado && cert.motivo_anulacion && (
                  <Text size="xs" c="dimmed" fs="italic">
                    Motivo: {cert.motivo_anulacion}
                    {cert.anulador && (
                      <> — {cert.anulador.nombre_completo
                        ?? cert.anulador.usuario_ti}</>
                    )}
                  </Text>
                )}

                <Group gap="xs" mt={4}>
                  {/* El PDF se descarga también si está anulado: lleva la
                      marca «ANULADO» y hace falta poder enseñar qué se
                      anuló. */}
                  <Button
                    size="compact-xs"
                    variant="light"
                    leftSection={<IconDownload size={13} />}
                    loading={descargando === cert.id}
                    onClick={() => descargar(cert.id, cert.folio)}
                  >
                    Descargar PDF
                  </Button>

                  {!anulado && (
                    <Button
                      size="compact-xs"
                      variant="subtle"
                      color="orange"
                      leftSection={<IconBan size={13} />}
                      onClick={() => setAAnular(cert)}
                    >
                      Anular
                    </Button>
                  )}
                </Group>
              </Stack>
            </Card>
            )
          })}
        </Stack>
      )}

      <EmitirCertificadoModal
        opened={modalOpened}
        onClose={cerrarModal}
        consulta={consulta}
        esFamiliar={esFamiliar}
      />

      <AnularRegistroModal
        opened={!!aAnular}
        onClose={() => setAAnular(null)}
        titulo="Anular certificado médico"
        descripcion={
          aAnular?.permiso_servidor
            ? `Se anulará ${aAnular.folio} y con él el permiso ` +
              `${aAnular.permiso_servidor.folio}, que dejará de justificar ` +
              'la ausencia.'
            : `Se anulará ${aAnular?.folio ?? ''}.`
        }
        motivos={MOTIVOS_ANULAR_CERTIFICADO}
        loading={anular.isPending}
        onConfirmar={(motivo) => {
          if (!aAnular) return
          anular.mutate(
            { id: aAnular.id, motivo },
            { onSuccess: () => setAAnular(null) }
          )
        }}
      />
    </Stack>
  )
}
