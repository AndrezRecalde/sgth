'use client'

import type { SemanticTone } from '@/config/design.tokens'
import { Accordion, ActionIcon, Alert, Group, Stack, Text, Tooltip } from '@mantine/core'
import { IconCalendarCog, IconInfoCircle } from '@tabler/icons-react'
import { DetailList, StatusBadge } from '@/components/ui'
import { formatFecha, hoyIso } from '@/lib/fecha'
import type { EstadoContrato } from '@/types/api'
import { dinero } from '../utils/dinero'
import { etiquetaNombramiento } from '../utils/tipoNombramientoOptions'
import { partidaDelVinculo } from '../utils/partidaPorModalidad'
import type { VinculoConActividad } from '../services/actividadLaboralService'
import { AccionesSobreVinculo, ReprogramacionesDelPlazo } from './AccionesSobreVinculo'

const TONO_CONTRATO: Record<EstadoContrato, SemanticTone> = {
  vigente: 'success',
  terminado: 'neutral',
  cancelado: 'danger',
}

const ESTADO_LABELS: Record<EstadoContrato, string> = {
  vigente: 'Vigente',
  terminado: 'Terminado',
  cancelado: 'Cancelado',
}

interface Props {
  vinculo: VinculoConActividad
  onReprogramar: (vinculo: VinculoConActividad) => void
}

/** Un vínculo laboral de la pestaña Laboral, desplegable con su historia. */
export function VinculoLaboralItem({ vinculo, onReprogramar }: Props) {
  const c = vinculo.contrato
  const estado = (c.estado ?? 'vigente') as EstadoContrato
  // Ninguna tarea cesa al ocasional cuyo plazo pasó: seguía saliendo
  // «Vigente» para siempre. Se avisa; cerrarlo exige su acción.
  const vencido = estado === 'vigente' && !!c.fecha_fin && c.fecha_fin.slice(0, 10) < hoyIso()

  return (
    <Accordion.Item value={String(c.id)}>
      <Accordion.Control>
        <Group justify="space-between" wrap="nowrap" pr="sm">
          <div style={{ minWidth: 0 }}>
            <Group gap="xs">
              <Text fw={600} size="sm">
                {etiquetaNombramiento(c.tipo_nombramiento)}
              </Text>
              {c.numero_contrato && (
                <Text size="sm" c="dimmed" ff="monospace">{c.numero_contrato}</Text>
              )}
            </Group>
            <Text size="xs" c="dimmed">
              {c.puesto?.cargo?.nombre ?? 'Sin puesto'} · {c.unidad_administrativa?.nombre ?? 'Sin unidad'}
            </Text>
          </div>
          <Group gap="xs" wrap="nowrap">
            {vencido ? (
              <StatusBadge tone="warning">Vencido el {formatFecha(c.fecha_fin)}</StatusBadge>
            ) : (
              <StatusBadge tone={TONO_CONTRATO[estado]}>{ESTADO_LABELS[estado]}</StatusBadge>
            )}
            {/* Situación derivada de las acciones vigentes hoy, no un estado
                almacenado: el vínculo sigue vigente aunque la persona esté
                temporalmente ausente. */}
            {vinculo.situacion && (
              <StatusBadge tone="info">
                {vinculo.situacion.etiqueta}
                {vinculo.situacion.hasta ? ` hasta ${formatFecha(vinculo.situacion.hasta)}` : ''}
              </StatusBadge>
            )}
            {vinculo.reemplaza_a && <StatusBadge>Reemplazo</StatusBadge>}
          </Group>
        </Group>
      </Accordion.Control>

      <Accordion.Panel>
        <Stack gap="md">
          {vinculo.reemplaza_a && (
            // Un aviso informativo va en ocean (regla 03): amethyst es el
            // acento del Portal, no un color de mensaje.
            <Alert variant="light" color="ocean" icon={<IconInfoCircle size={16} />}>
              Contrato de reemplazo: cubre la{' '}
              {vinculo.reemplaza_a.etiqueta?.toLowerCase() ?? 'ausencia'} de{' '}
              <strong>{vinculo.reemplaza_a.servidor}</strong>
              {vinculo.reemplaza_a.hasta
                ? `, hasta el ${formatFecha(vinculo.reemplaza_a.hasta)}.`
                : '.'}
            </Alert>
          )}

          <DetailList
            columnas={3}
            items={[
              { label: 'Desde', value: formatFecha(c.fecha_inicio) },
              {
                label: 'Hasta',
                value: (
                  <Group gap={4} align="center" wrap="nowrap">
                    {c.fecha_fin ? formatFecha(c.fecha_fin) : 'Sin plazo'}
                    {/* El plazo es lo único editable de un vínculo ya creado,
                        y solo mientras siga vigente: en uno terminado la fecha
                        de fin ya es un hecho histórico. */}
                    {estado === 'vigente' && (
                      <Tooltip label="Prórroga o corrección del vencimiento" withArrow>
                        <ActionIcon
                          variant="subtle"
                          size="sm"
                          aria-label="Reprogramar el plazo"
                          onClick={() => onReprogramar(vinculo)}
                        >
                          <IconCalendarCog size={16} />
                        </ActionIcon>
                      </Tooltip>
                    )}
                  </Group>
                ),
              },
              { label: 'Remuneración', value: dinero(c.remuneracion) },
              { label: 'Resolución', value: c.resolucion_numero },
              { label: 'Partida', value: partidaDelVinculo(c) },
              { label: 'Marca asistencia', value: c.puede_marcar === false ? 'No' : 'Sí' },
              ...(c.motivo_fin
                ? [{ label: 'Motivo de término', value: c.motivo_fin, ancho: true }]
                : []),
            ]}
          />

          <ReprogramacionesDelPlazo cambios={vinculo.cambios} />
          <AccionesSobreVinculo acciones={vinculo.acciones} />
        </Stack>
      </Accordion.Panel>
    </Accordion.Item>
  )
}
