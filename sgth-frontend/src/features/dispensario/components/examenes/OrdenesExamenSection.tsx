'use client'

import { useState } from 'react'
import { Button, Card, Group, Stack, Text } from '@mantine/core'
import { IconBan, IconCircleX, IconFlask, IconPlus, IconPrinter, IconUpload } from '@tabler/icons-react'
import { useDisclosure } from '@mantine/hooks'
import { DataState, StatusBadge, TableActions } from '@/components/ui'
import { useAuthStore } from '@/store/auth.store'
import { formatFechaMes } from '@/lib/fecha'
import {
  useAnularOrdenExamen, useOrdenesAbiertasDelPaciente, useOrdenesExamen, useOrdenExamenPdf,
} from '../../hooks/useExamenes'
import { AnularRegistroModal, MOTIVOS_ANULAR_ORDEN_EXAMEN } from '../AnularRegistroModal'
import { SubirResultadoModal } from '../SubirResultadoModal'
import { ESTADO_ORDEN, ordenAbierta } from '../../utils/resultadoDeOrden'
import { ItemsDeOrden } from './ItemsDeOrden'
import { NoRealizadoModal } from './NoRealizadoModal'
import { SolicitarExamenesModal } from './SolicitarExamenesModal'
import type { OrdenExamen } from '../../services/examenService'
import type { ConsultaMedica } from '../../services/consultaMedicaService'

interface Props {
  consulta:          ConsultaMedica
  historiaClinicaId: number
}

/**
 * Las órdenes de exámenes de esta consulta, el botón para pedir otra y, debajo,
 * las del paciente que siguen abiertas en otras consultas.
 *
 * Desde el 2026-10-29 la bandeja de cada médico tiene solo lo que él pidió. Si
 * el paciente trae los resultados de lo que pidió un colega que hoy no está, se
 * registran aquí, en la consulta: el servidor ofrece las de la especialidad de
 * quien atiende, sin las de una ficha FEMO.
 */
export function OrdenesExamenSection({ consulta, historiaClinicaId }: Props) {
  const [solicitarOpened, { open: abrirSolicitar, close: cerrarSolicitar }] = useDisclosure(false)
  const [anulando, setAnulando] = useState<OrdenExamen | null>(null)
  const [registrando, setRegistrando] = useState<OrdenExamen | null>(null)
  const [sinRealizar, setSinRealizar] = useState<OrdenExamen | null>(null)
  const { usuario, hasPermiso } = useAuthStore()
  // Lo que vuelve lo registran el médico y el odontólogo (D2, P1).
  const puedeRegistrar = hasPermiso('crear-consulta')
  const { data: ordenes = [], isLoading, error, refetch } = useOrdenesExamen(historiaClinicaId, consulta.id)
  const { data: abiertas = [] } = useOrdenesAbiertasDelPaciente(historiaClinicaId, puedeRegistrar)
  const anular = useAnularOrdenExamen()
  const { abrir: abrirPdf, abriendo } = useOrdenExamenPdf()

  const deOtrasConsultas = abiertas.filter((o) => o.consulta_medica_id !== consulta.id)

  // Como el servidor: pide quien atendió la consulta (la orden sale con su
  // firma); anula quien la emitió o la administración, mientras esté abierta.
  const puedeSolicitar = consulta.medico_id === usuario?.id
  const puedeAnular = (o: OrdenExamen) =>
    ordenAbierta(o) && (o.solicitado_por === usuario?.id || hasPermiso('supervisar-dispensario'))

  const tarjeta = (o: OrdenExamen, conSolicitante = false) => (
    <Card key={o.id} withBorder radius="md" p="sm">
      <Group justify="space-between" wrap="nowrap" align="flex-start">
        <Stack gap={4}>
          <Group gap="xs">
            <Text size="sm" fw={600} ff="monospace">{o.folio}</Text>
            <StatusBadge size="xs" tone={ESTADO_ORDEN[o.estado].tone}>{ESTADO_ORDEN[o.estado].label}</StatusBadge>
            {o.vencida && <StatusBadge size="xs" tone="danger">Vencida</StatusBadge>}
            {o.prioridad === 'urgente' && <StatusBadge size="xs" tone="warning">Urgente</StatusBadge>}
            <Text size="xs" c="dimmed">{formatFechaMes(o.fecha_solicitud)}</Text>
          </Group>
          {conSolicitante && (
            <Text size="xs" c="dimmed">
              Pidió: {o.solicitado_por === usuario?.id
                ? 'usted'
                : o.solicitante?.servidor ? `${o.solicitante.servidor.nombre} ${o.solicitante.servidor.apellido}` : '—'}
            </Text>
          )}
          <ItemsDeOrden items={o.items} />
          {o.estado === 'anulada' && o.motivo_anulacion && (
            <Text size="xs" c="dimmed">Anulada: {o.motivo_anulacion}</Text>
          )}
        </Stack>
        <Group gap={4} wrap="nowrap">
          {ordenAbierta(o) && puedeRegistrar && (
            <Button size="compact-xs" variant="light" leftSection={<IconUpload size={13} />}
              onClick={() => setRegistrando(o)}>
              Registrar resultado
            </Button>
          )}
          <Button size="compact-xs" variant="light" leftSection={<IconPrinter size={13} />}
            loading={abriendo === o.id} onClick={() => abrirPdf(o.id, o.folio)}>
            Imprimir
          </Button>
          <TableActions actions={[
            {
              label: 'Marcar no realizado', icon: <IconCircleX size={14} />,
              onClick: () => setSinRealizar(o),
              hidden: !(ordenAbierta(o) && puedeRegistrar && o.items.some((i) => i.estado === 'pendiente')),
            },
            {
              label: 'Anular orden', icon: <IconBan size={14} />, color: 'red',
              onClick: () => setAnulando(o), hidden: !puedeAnular(o),
            },
          ]} />
        </Group>
      </Group>
    </Card>
  )

  return (
    <Stack gap="sm">
      <Group justify="space-between">
        <Text size="sm" fw={500}>
          Órdenes de exámenes
          {ordenes.length > 0 && <Text span c="dimmed" ml={4}>({ordenes.length})</Text>}
        </Text>
        {puedeSolicitar && (
          <Button size="xs" leftSection={<IconPlus size={13} />} onClick={abrirSolicitar}>
            Solicitar exámenes
          </Button>
        )}
      </Group>

      <DataState loading={isLoading} error={error} onRetry={() => refetch()}
        errorTitle="No se pudieron cargar las órdenes"
        empty={!ordenes.length}
        emptyProps={{ icon: IconFlask, title: 'Sin órdenes', description: 'No se han pedido exámenes en esta consulta.' }}
        skeletonRows={2}>
        <Stack gap="sm">
          {ordenes.map((o) => tarjeta(o))}
        </Stack>
      </DataState>

      {deOtrasConsultas.length > 0 && (
        <Stack gap="sm" mt="xs">
          <Stack gap={2}>
            <Text size="sm" fw={500}>
              Abiertas de otras consultas
              <Text span c="dimmed" ml={4}>({deOtrasConsultas.length})</Text>
            </Text>
            <Text size="xs" c="dimmed">
              Si el paciente trae estos resultados, regístrelos aquí aunque los haya pedido otro colega.
            </Text>
          </Stack>
          {deOtrasConsultas.map((o) => tarjeta(o, true))}
        </Stack>
      )}

      <SolicitarExamenesModal opened={solicitarOpened} onClose={cerrarSolicitar} origen={{ tipo: 'consulta', consulta }} />
      <SubirResultadoModal opened={registrando !== null} onClose={() => setRegistrando(null)}
        historiaClinicaId={historiaClinicaId} consulta={consulta} ordenInicial={registrando} />
      <NoRealizadoModal orden={sinRealizar} onClose={() => setSinRealizar(null)} />

      <AnularRegistroModal
        opened={anulando !== null}
        onClose={() => setAnulando(null)}
        titulo="Anular orden de exámenes"
        descripcion={`Orden ${anulando?.folio ?? ''}: dejará de esperar resultados.`}
        motivos={MOTIVOS_ANULAR_ORDEN_EXAMEN}
        loading={anular.isPending}
        onConfirmar={(motivo) => {
          if (!anulando) return
          anular.mutate({ id: anulando.id, motivo }, { onSuccess: () => setAnulando(null) })
        }}
      />
    </Stack>
  )
}
