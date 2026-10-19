'use client'

import { Alert, List, Stack, Text } from '@mantine/core'
import { IconAlertTriangle } from '@tabler/icons-react'
import { DetailList, ModalFooter, SgthModal } from '@/components/ui'
import { SEMANTIC_COLOR } from '@/config/design.tokens'
import { formatFecha } from '@/lib/fecha'
import type { SolicitudCargaFamiliar } from '@/types/api'
import { PARENTESCO_LABELS, SEXO_LABELS } from '../constants/cargaFamiliar'
import { useValidacionCargas } from '../hooks/useValidacionCargas'
import { familiarDe } from './cargasPorValidar.columns'

interface Props {
  /** La solicitud que se valida; sin ella, el modal está cerrado. */
  solicitud: SolicitudCargaFamiliar | null
  onClose:   () => void
}

const parentesco = (p?: SolicitudCargaFamiliar['parentesco']) => (p ? PARENTESCO_LABELS[p] : '—')
const sexo = (g?: string | null) => (g ? SEXO_LABELS[g] ?? g : '—')

/**
 * Validar una solicitud de carga del Portal (docs/diseno-cargas-familiares-portal.md,
 * 6.2). Antes de confirmar se ve qué cambia —el familiar nuevo, el antes y el
 * después de una corrección, o a quién se da de baja— y los avisos del backend.
 * El del cónyuge activo no deja validar: lo frena el backend con su mensaje.
 */
export function ValidarSolicitudCargaModal({ solicitud: s, onClose }: Props) {
  const { validar } = useValidacionCargas()

  const filas = s?.tipo === 'correccion' && s.carga
    ? [
        ['Nombres', s.carga.nombres, s.nombres],
        ['Apellidos', s.carga.apellidos, s.apellidos],
        ['Parentesco', parentesco(s.carga.parentesco), parentesco(s.parentesco)],
        ['Sexo', sexo(s.carga.genero), sexo(s.genero)],
        ['Nacimiento', formatFecha(s.carga.fecha_nacimiento), formatFecha(s.fecha_nacimiento)],
      ].filter(([, antes, despues]) => antes !== despues)
    : []

  return (
    <SgthModal opened={s !== null} onClose={onClose} title="Validar solicitud de carga" size="md">
      {s && (
        <Stack gap="md">
          {s.tipo === 'alta' && (
            <>
              <Text size="sm">
                <b>{familiarDe(s)}</b> quedará como carga de este servidor y podrá atenderse en el Dispensario.
              </Text>
              <DetailList items={[
                { label: 'Cédula', value: s.cedula },
                { label: 'Parentesco', value: parentesco(s.parentesco) },
                { label: 'Sexo', value: sexo(s.genero) },
                { label: 'Nacimiento', value: formatFecha(s.fecha_nacimiento) },
              ]} />
            </>
          )}

          {s.tipo === 'correccion' && (
            <>
              <Text size="sm">Los datos de <b>{familiarDe(s)}</b> cambiarán así:</Text>
              <DetailList columnas={1} items={filas.map(([campo, antes, despues]) => ({
                label: campo ?? '',
                value: (
                  <>
                    <Text span td="line-through" c="dimmed">{antes}</Text>{' → '}<b>{despues}</b>
                  </>
                ),
              }))} />
              {s.motivo && <Text size="sm" c="dimmed">Lo que corrige: {s.motivo}</Text>}
            </>
          )}

          {s.tipo === 'baja' && (
            <Text size="sm">
              <b>{familiarDe(s)}</b> dejará de ser carga de este servidor. No se borra: se desactiva y su
              historia clínica se conserva. Motivo: {s.motivo}
            </Text>
          )}

          {!!s.avisos?.length && (
            <Alert color={SEMANTIC_COLOR.warning} variant="light" icon={<IconAlertTriangle size={16} />}>
              <List size="sm" spacing={4}>
                {s.avisos.map((aviso) => <List.Item key={aviso}>{aviso}</List.Item>)}
              </List>
            </Alert>
          )}

          <ModalFooter
            onCancel={onClose}
            submitLabel="Validar"
            submitting={validar.isPending}
            onSubmit={() => validar.mutate(s, { onSuccess: onClose })}
          />
        </Stack>
      )}
    </SgthModal>
  )
}
