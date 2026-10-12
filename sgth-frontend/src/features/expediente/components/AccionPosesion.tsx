'use client'

import { Alert, Button, Group, Stack, Text } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconAlertTriangle, IconBan, IconUserCheck } from '@tabler/icons-react'
import { DetailList, MotivoModal, SectionHeading } from '@/components/ui'
import { formatFecha } from '@/lib/fecha'
import { useMovimientoMutations } from '../hooks/useMovimientoMutations'
import type { MovimientoPersonal } from '@/types/api'
import { BloqueDetalle } from './BloqueDetalle'
import { RegistrarPosesionModal } from './RegistrarPosesionModal'

/** El motivo que propone anular por no presentarse: se puede corregir. */
const MOTIVO_NO_SE_PRESENTO = 'No se presentó a trabajar en el término para presentarse: el nombramiento '
  + 'queda insubsistente (Reglamento a la LOSEP, Art. 20).'

/**
 * La posesión y el plazo para presentarse (fase 6.4). En el ingreso dice hasta
 * cuándo puede presentarse; si el plazo venció sin la posesión, el
 * nombramiento queda insubsistente y se ofrece anularlo con el motivo escrito.
 * En el ascenso, el definitivo y el encargo, solo la posesión.
 */
export function AccionPosesion({ m, onAnulada }: { m: MovimientoPersonal; onAnulada: () => void }) {
  const [registrarOpened, { open: abrirRegistrar, close: cerrarRegistrar }] = useDisclosure(false)
  const [anularOpened, { open: abrirAnular, close: cerrarAnular }] = useDisclosure(false)
  const { transicionar } = useMovimientoMutations()

  const vigente = m.estado === 'registrada' || m.estado === 'notificada'
  if (!m.admite_posesion || !vigente) return null

  const esContrato = m.instrumento === 'registro_contrato'
  const puedeAnular = (m.transiciones_permitidas ?? []).includes('anulada')

  const registrar = m.puede_registrar_posesion && (
    <Button size="xs" variant="light" leftSection={<IconUserCheck size={14} />} onClick={abrirRegistrar}>
      {esContrato ? 'Registrar que se presentó' : 'Registrar la posesión'}
    </Button>
  )

  return (
    <div>
      <SectionHeading title={esContrato ? 'Inicio de funciones' : 'Posesión'} mb={4} />

      {m.fecha_posesion ? (
        <BloqueDetalle>
          <DetailList items={[
            { label: esContrato ? 'Se presentó el' : 'Fecha', value: formatFecha(m.fecha_posesion) },
            ...(esContrato ? [] : [{ label: 'Lugar', value: m.lugar_posesion }]),
          ]} />
          {m.posesion_fuera_de_plazo && m.presentacion_vence_el && (
            <Text size="xs" c="amber" mt={4}>
              Fuera del plazo para presentarse, que vencía el {formatFecha(m.presentacion_vence_el)}.
            </Text>
          )}
        </BloqueDetalle>
      ) : m.presentacion_vencida && m.presentacion_vence_el ? (
        // Un aviso que pide decidir va en amber, como los demás del cajón.
        <Alert variant="light" color="amber" icon={<IconAlertTriangle size={16} />}>
          <Stack gap="xs">
            <Text size="sm">
              El plazo para presentarse venció el {formatFecha(m.presentacion_vence_el)} sin la posesión: el
              nombramiento queda insubsistente (Reglamento a la LOSEP, Art. 20). Si se presentó, registre la
              posesión; si no, anúlelo.
            </Text>
            <Group gap="xs">
              {registrar}
              {puedeAnular && (
                <Button size="xs" variant="light" color="red" leftSection={<IconBan size={14} />} onClick={abrirAnular}>
                  Anular por no presentarse
                </Button>
              )}
            </Group>
          </Stack>
        </Alert>
      ) : (
        <Group justify="space-between" align="center">
          <Text size="sm" c="dimmed">
            {m.presentacion_vence_el
              ? `Tiene hasta el ${formatFecha(m.presentacion_vence_el)} para presentarse${esContrato
                ? ': 24 horas desde el registro del contrato.'
                : ` (${m.dias_para_presentarse ?? 3} días hábiles desde el registro o desde que rige).`}`
              : m.pendiente_de_vigencia
                ? `La posesión se registra desde el ${formatFecha(m.fecha_efectiva)}, cuando rige.`
                : 'Sin registrar todavía.'}
          </Text>
          {registrar}
        </Group>
      )}

      <RegistrarPosesionModal opened={registrarOpened} movimiento={m} onClose={cerrarRegistrar} />

      <MotivoModal
        opened={anularOpened}
        onClose={cerrarAnular}
        title="Anular por no presentarse"
        descripcion={
          <>
            Se anulará <b>{m.codigo_registro}</b> y <b>se deshará su efecto sobre el vínculo</b>: la plaza
            queda libre. El motivo queda en el expediente.
          </>
        }
        valorInicial={MOTIVO_NO_SE_PRESENTO}
        confirmLabel="Anular"
        destructiva
        cargando={transicionar.isPending}
        onConfirm={(motivo) => transicionar.mutate(
          { id: Number(m.id), estado: 'anulada', motivo_anulacion: motivo },
          { onSuccess: () => { cerrarAnular(); onAnulada() } },
        )}
      />
    </div>
  )
}
