'use client'

import type { SemanticTone } from '@/config/design.tokens'
import { useState } from 'react'
import {
  Accordion, ActionIcon, Alert, Box, Grid, Group, Paper, Skeleton,
  Stack, Text, Tooltip,
} from '@mantine/core'
import { IconBriefcase, IconCalendarCog, IconInfoCircle } from '@tabler/icons-react'
import { EmptyState } from '@/components/ui/EmptyState'
import { useActividadLaboral } from '../../hooks/useActividadLaboral'
import { etiquetaNombramiento } from '../../utils/tipoNombramientoOptions'
import { ReprogramarPlazoModal } from '../ReprogramarPlazoModal'
import type {
  AccionSobreVinculo, VinculoConActividad,
} from '../../services/actividadLaboralService'
import type { ContratoConRelaciones, EstadoContrato } from '@/types/api'
import { StatusBadge } from '@/components/ui'
import { formatFecha, formatFechaHora } from '@/lib/fecha'

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

function dinero(v?: string | number | null): string {
  return v != null ? `$ ${Number(v).toFixed(2)}` : '—'
}

function Dato({ etiqueta, valor }: { etiqueta: string; valor?: string | null }) {
  return (
    <Box>
      <Text size="xs" fw={600} c="dimmed" tt="uppercase">{etiqueta}</Text>
      <Text size="sm">{valor?.toString().trim() || '—'}</Text>
    </Box>
  )
}

/** Resumen legible del cambio que produjo una acción. */
function cambio(a: AccionSobreVinculo): string | null {
  if (a.unidad_destino && a.unidad_destino !== a.unidad_origen) {
    return `${a.unidad_origen ?? 'Sin unidad'} → ${a.unidad_destino}`
  }
  if (a.fecha_inicio) {
    return `${formatFecha(a.fecha_inicio)} – ${a.fecha_fin ? formatFecha(a.fecha_fin) : 'sin fecha de fin'}`
  }
  return null
}

function FilaAccion({ accion }: { accion: AccionSobreVinculo }) {
  const detalle = cambio(accion)

  return (
    <Paper withBorder p="xs" radius="sm">
      <Group justify="space-between" wrap="nowrap" align="flex-start">
        <div style={{ minWidth: 0 }}>
          <Text size="sm" fw={500}>{accion.etiqueta ?? accion.tipo_movimiento}</Text>
          {detalle && <Text size="xs" c="dimmed">{detalle}</Text>}
        </div>
        <div style={{ textAlign: 'right', whiteSpace: 'nowrap' }}>
          <Text size="xs">{formatFecha(accion.fecha_efectiva)}</Text>
          {accion.codigo_registro && (
            <Text size="xs" c="dimmed" ff="monospace">{accion.codigo_registro}</Text>
          )}
        </div>
      </Group>
    </Paper>
  )
}

function Vinculo({
  vinculo, onReprogramar,
}: {
  vinculo: VinculoConActividad
  onReprogramar: (contrato: VinculoConActividad['contrato']) => void
}) {
  const c = vinculo.contrato
  const estado = (c.estado ?? 'vigente') as EstadoContrato

  // De todo lo auditado sobre el contrato, la reprogramación es la única que
  // trae una explicación escrita por una persona. El alta y el cierre ya se
  // leen en el resto de la tarjeta.
  const reprogramaciones = (vinculo.cambios ?? []).filter((x) => x.motivo)

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
            <StatusBadge tone={TONO_CONTRATO[estado]}>
              {ESTADO_LABELS[estado]}
            </StatusBadge>
            {/* Situación derivada de las acciones vigentes hoy, no un estado
                almacenado: el vínculo sigue vigente aunque la persona esté
                temporalmente ausente. */}
            {vinculo.situacion && (
              <StatusBadge tone="info">
                {vinculo.situacion.etiqueta}
                {vinculo.situacion.hasta ? ` hasta ${formatFecha(vinculo.situacion.hasta)}` : ''}
              </StatusBadge>
            )}
            {vinculo.reemplaza_a && (
              <StatusBadge>Reemplazo</StatusBadge>
            )}
          </Group>
        </Group>
      </Accordion.Control>

      <Accordion.Panel>
        <Stack gap="md">
          {vinculo.reemplaza_a && (
            <Alert variant="light" color="amethyst" icon={<IconInfoCircle size={16} />}>
              Contrato de reemplazo: cubre la{' '}
              {vinculo.reemplaza_a.etiqueta?.toLowerCase() ?? 'ausencia'} de{' '}
              <strong>{vinculo.reemplaza_a.servidor}</strong>
              {vinculo.reemplaza_a.hasta
                ? `, hasta el ${formatFecha(vinculo.reemplaza_a.hasta)}.`
                : '.'}
            </Alert>
          )}

          <Grid>
            <Grid.Col span={{ base: 6, sm: 3 }}>
              <Dato etiqueta="Desde" valor={formatFecha(c.fecha_inicio)} />
            </Grid.Col>
            <Grid.Col span={{ base: 6, sm: 3 }}>
              <Group gap={4} align="flex-start" wrap="nowrap">
                <Dato etiqueta="Hasta" valor={c.fecha_fin ? formatFecha(c.fecha_fin) : 'Sin plazo'} />
                {/* El plazo es lo único editable de un vínculo ya creado, y
                    solo mientras siga vigente: en uno terminado la fecha de
                    fin ya es un hecho histórico. */}
                {estado === 'vigente' && (
                  <Tooltip label="Prórroga o corrección del vencimiento" withArrow>
                    <ActionIcon
                      variant="subtle"
                      size="sm"
                      aria-label="Reprogramar el plazo"
                      onClick={() => onReprogramar(c)}
                    >
                      <IconCalendarCog size={16} />
                    </ActionIcon>
                  </Tooltip>
                )}
              </Group>
            </Grid.Col>
            <Grid.Col span={{ base: 6, sm: 3 }}>
              <Dato etiqueta="Remuneración" valor={dinero(c.remuneracion)} />
            </Grid.Col>
            <Grid.Col span={{ base: 6, sm: 3 }}>
              <Dato etiqueta="Resolución" valor={c.resolucion_numero} />
            </Grid.Col>
            <Grid.Col span={{ base: 6, sm: 3 }}>
              <Dato
                etiqueta="Partida"
                valor={c.puesto?.partida_presupuestaria?.codigo}
              />
            </Grid.Col>
            <Grid.Col span={{ base: 6, sm: 3 }}>
              <Dato etiqueta="Marca asistencia" valor={c.puede_marcar === false ? 'No' : 'Sí'} />
            </Grid.Col>
            {c.motivo_fin && (
              <Grid.Col span={12}>
                <Dato etiqueta="Motivo de término" valor={c.motivo_fin} />
              </Grid.Col>
            )}
          </Grid>

          {/* El plazo es lo único editable de un vínculo, y moverlo cambia
              cuándo cesa el servidor. Aquí queda dicho quién lo movió y por
              qué — antes el motivo se exigía, se guardaba y no lo veía nadie. */}
          {reprogramaciones.length > 0 && (
            <div>
              <Text size="xs" fw={600} c="dimmed" tt="uppercase" mb="xs">
                Reprogramaciones del plazo
              </Text>
              <Stack gap="xs">
                {reprogramaciones.map((r) => (
                  <Paper key={r.id} withBorder p="xs" radius="sm">
                    <Group gap="xs" wrap="nowrap" align="baseline">
                      <Text size="sm" fw={500}>
                        {formatFecha(r.fecha_fin_anterior)} → {formatFecha(r.fecha_fin_nueva)}
                      </Text>
                      <Text size="xs" c="dimmed">
                        {formatFechaHora(r.fecha)}
                        {r.por ? ` · ${r.por}` : ''}
                      </Text>
                    </Group>
                    {r.motivo && <Text size="sm" mt={2}>{r.motivo}</Text>}
                  </Paper>
                ))}
              </Stack>
            </div>
          )}

          <div>
            <Text size="xs" fw={600} c="dimmed" tt="uppercase" mb="xs">
              Acciones de personal sobre este vínculo
            </Text>

            {vinculo.acciones.length === 0 ? (
              <Text size="sm" c="dimmed">
                Sin acciones registradas sobre este vínculo.
              </Text>
            ) : (
              <Stack gap="xs">
                {vinculo.acciones.map((a) => <FilaAccion key={a.id} accion={a} />)}
              </Stack>
            )}
          </div>
        </Stack>
      </Accordion.Panel>
    </Accordion.Item>
  )
}

interface Props {
  servidorId: number
}

/**
 * Actividad laboral: cada vínculo con las acciones ocurridas sobre él.
 *
 * No hay botones para crear ni cerrar contratos: un vínculo nace de una acción
 * de personal de ingreso y muere con una de cesación. Permitirlo aquí sería la
 * puerta trasera que deja contratos sin acción que los respalde.
 */
export function LaboralTab({ servidorId }: Props) {
  const { data: vinculos = [], isLoading } = useActividadLaboral(servidorId)

  /** El contrato abierto para reprogramar; null = modal cerrado. */
  const [reprogramando, setReprogramando] = useState<ContratoConRelaciones | null>(null)

  if (isLoading) {
    return (
      <Stack gap="xs">
        <Skeleton height={64} radius="md" />
        <Skeleton height={64} radius="md" />
      </Stack>
    )
  }

  if (vinculos.length === 0) {
    return (
      <EmptyState
        icon={IconBriefcase}
        title="Sin vínculos registrados"
        description="El vínculo laboral se crea al aprobar una acción de personal de Ingreso y Vinculación."
      />
    )
  }

  return (
    <Stack gap="md">
      <Alert variant="light" color="ocean" icon={<IconInfoCircle size={16} />}>
        Cada vínculo conserva su número de contrato original. Traspasos,
        comisiones y sanciones no crean uno nuevo: se registran sobre el mismo,
        y se ven al desplegarlo.
      </Alert>

      <Accordion variant="separated" defaultValue={String(vinculos[0]?.contrato.id)}>
        {vinculos.map((v) => (
          <Vinculo key={v.contrato.id} vinculo={v} onReprogramar={setReprogramando} />
        ))}
      </Accordion>

      <ReprogramarPlazoModal
        opened={reprogramando !== null}
        onClose={() => setReprogramando(null)}
        servidorId={servidorId}
        contrato={reprogramando}
      />
    </Stack>
  )
}
