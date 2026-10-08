'use client'

import { Alert, List, Stack, Text } from '@mantine/core'
import { IconAlertTriangle, IconInfoCircle } from '@tabler/icons-react'
import { DetailList, SectionHeading } from '@/components/ui'
import { SEMANTIC_COLOR } from '@/config/design.tokens'
import { formatFecha } from '@/lib/fecha'
import { ESTADO_LABELS, TIPO_LABELS } from './permisos.constants'
import type { PermisoServidor, PreviaSirha7 } from '@/types/api'

interface Props {
  permiso: PermisoServidor
  previa:  PreviaSirha7
}

const dias = (p: PreviaSirha7) =>
  p.desde === p.hasta ? formatFecha(p.desde) : `${formatFecha(p.desde)} al ${formatFecha(p.hasta)}`

/**
 * Qué se registrará en Sirha7: días, horario, el certificado del dispensario
 * (sin diagnóstico) y los otros permisos de la persona en esos días.
 */
export function PreviaSirha7Detalle({ permiso, previa }: Props) {
  const servidor = permiso.servidor
  const cert = previa.certificado

  return (
    <Stack gap="md">
      {!previa.pendiente && previa.motivo && (
        <Alert color={SEMANTIC_COLOR.danger} variant="light" icon={<IconAlertTriangle size={16} />}>
          <Text size="sm">{previa.motivo}</Text>
        </Alert>
      )}

      <DetailList
        items={[
          { label: 'Servidor', value: [servidor?.apellido, servidor?.nombre].filter(Boolean).join(' ') },
          { label: 'Folio', value: permiso.folio },
          { label: 'Días', value: dias(previa) },
          {
            label: 'Horario',
            value: previa.jornada_completa
              ? 'Jornada completa'
              : `${previa.hora_inicio} — ${previa.hora_fin}`,
          },
        ]}
      />

      <Alert color="ocean" variant="light" icon={<IconInfoCircle size={16} />} py={8}>
        <Text size="xs">
          Se escribe una fila por día con la referencia <b>{previa.referencia}</b>.
          {previa.jornada_completa &&
            ' Cada día toma el horario de la persona en Sirha7, o de 08:00 a 17:00 si no tiene; sábado y domingo se omiten.'}
          {previa.desde === previa.hasta
            ? ' Si ese horario ya tiene otro permiso en Sirha7, no se registra y se dirá con cuál choca.'
            : ' Los días que ya tengan otro permiso en Sirha7, como un feriado, se omiten.'}
        </Text>
      </Alert>

      {cert && (
        <>
          <SectionHeading title="Certificado médico" />
          <DetailList
            items={[
              { label: 'Folio del certificado', value: cert.folio },
              { label: 'Días de reposo', value: cert.dias_reposo },
              { label: 'Desde', value: cert.fecha_inicio ? formatFecha(cert.fecha_inicio) : null },
              { label: 'Hasta', value: cert.fecha_fin ? formatFecha(cert.fecha_fin) : null },
              { label: 'Médico', value: cert.medico, ancho: true },
            ]}
          />
        </>
      )}

      {previa.cruces.length > 0 && (
        <Alert
          color={SEMANTIC_COLOR.warning}
          variant="light"
          icon={<IconAlertTriangle size={16} />}
          title="La persona tiene otros permisos en esos días"
        >
          <List size="sm" spacing={2}>
            {previa.cruces.map((c) => (
              <List.Item key={c.id}>
                {c.folio} · {TIPO_LABELS[c.tipo] ?? c.tipo} · {formatFecha(c.fecha)}{' '}
                {c.hora_inicio}–{c.hora_fin} · {ESTADO_LABELS[c.estado] ?? c.estado}
              </List.Item>
            ))}
          </List>
          <Text size="xs" mt="xs">
            Si alguno es el permiso para ir a la consulta del dispensario, anúlelo
            antes de aprobar: el reposo ya cubre ese día.
          </Text>
        </Alert>
      )}
    </Stack>
  )
}
