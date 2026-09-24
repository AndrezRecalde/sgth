'use client'

import { Button, Card, Group, Stack, Text, ThemeIcon } from '@mantine/core'
import {
  IconBan, IconCalendar, IconCertificate, IconDownload, IconUser,
} from '@tabler/icons-react'
import { StatusBadge } from '@/components/ui'
import { formatFechaMes } from '@/lib/fecha'
import type { CertificadoMedico } from '../services/certificadoService'

interface Props {
  certificado: CertificadoMedico
  /** `true` mientras se está generando el PDF de ESTE certificado. */
  descargando: boolean
  onDescargar: () => void
  onAnular:    () => void
}

/**
 * Un certificado médico de la consulta.
 *
 * El anulado no desaparece: se queda atenuado, con el folio tachado y el
 * motivo a la vista. Anular no es borrar, y quien mira la consulta un mes
 * después necesita ver que se emitió y por qué se retiró.
 */
export function CertificadoItem({
  certificado: cert, descargando, onDescargar, onAnular,
}: Props) {
  const anulado = !!cert.anulado_en

  return (
    <Card
      withBorder
      radius="md"
      p="sm"
      style={{ opacity: anulado ? 0.65 : 1 }}
    >
      <Stack gap="xs">
        <Group justify="space-between">
          <Group gap="xs">
            <ThemeIcon size="sm" variant="light">
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
              <StatusBadge tone="danger" size="xs">
                Anulado
              </StatusBadge>
            )}
          </Group>
          <StatusBadge>
            {cert.dias_reposo} día{cert.dias_reposo !== 1 ? 's' : ''} de reposo
          </StatusBadge>
        </Group>

        <Group gap="xs">
          <IconCalendar size={13} color="var(--mantine-color-slate-6)" />
          <Text size="xs" c="dimmed">
            {formatFechaMes(cert.fecha_inicio)} →{' '}
            {formatFechaMes(cert.fecha_fin)}
          </Text>
        </Group>

        {cert.diagnostico_cie10 && (
          <Group gap="xs">
            <StatusBadge size="xs" variant="outline" ff="monospace">
              {cert.diagnostico_cie10.codigo}
            </StatusBadge>
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
            <IconUser size={13} color="var(--mantine-color-slate-6)" />
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
              <> — {cert.anulador.nombre_completo ?? cert.anulador.usuario_ti}</>
            )}
          </Text>
        )}

        <Group gap="xs" mt={4}>
          {/* El PDF se descarga también si está anulado: lleva la marca
              «ANULADO» y hace falta poder enseñar qué se anuló. */}
          <Button
            size="compact-xs"
            variant="light"
            leftSection={<IconDownload size={13} />}
            loading={descargando}
            onClick={onDescargar}
          >
            Descargar PDF
          </Button>

          {!anulado && (
            <Button
              size="compact-xs"
              variant="subtle"
              leftSection={<IconBan size={13} />}
              onClick={onAnular}
            >
              Anular
            </Button>
          )}
        </Group>
      </Stack>
    </Card>
  )
}
