'use client'

import { useState } from 'react'
import { Alert, Button, Group, Paper, Stack, Text, TextInput } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { IconArrowBackUp, IconBan, IconCheck, IconInfoCircle, IconPackageImport } from '@tabler/icons-react'
import { DataState, MotivoModal, SgthModal, StatusBadge } from '@/components/ui'
import { useAuth } from '@/hooks/useAuth'
import { formatFecha, formatFechaHora, fromDateValueOrNull, hoyIso, toDateValue } from '@/lib/fecha'
import { useDesvinculacion, useDevolverBien, useMarcarPaso } from '../hooks/useDesvinculaciones'
import type { EstadoPasoDesvinculacion, PasoDeLaDesvinculacion } from '../services/desvinculacionService'
import { PagoDeLaSalida } from './DesvinculacionPagos'

const ESTADO_PASO: Record<EstadoPasoDesvinculacion, { etiqueta: string; tono: 'success' | 'neutral' | 'warning' }> = {
  hecho: { etiqueta: 'Hecho', tono: 'success' },
  no_aplica: { etiqueta: 'No aplica', tono: 'neutral' },
  pendiente: { etiqueta: 'Pendiente', tono: 'warning' },
}

interface Props {
  desvinculacionId: number | null
  onClose: () => void
}

/**
 * Los pasos de una desvinculación (fases 7.4a y 7.4b). Los que se leen de su
 * módulo —bienes, usuario, certificado, declaración— quedan hechos solos; la
 * liquidación y la indemnización, cuando el Director las confirma con su
 * cálculo, y la prohibición de reingreso sigue a la indemnización. Todo paso
 * puede no aplicar, con su motivo.
 */
export function DesvinculacionDetalleModal({ desvinculacionId, onClose }: Props) {
  const { data, isLoading, error, refetch } = useDesvinculacion(desvinculacionId)
  const { hasPermiso } = useAuth()
  const esDirector = hasPermiso('registrar-accion-personal')
  const marcar = useMarcarPaso(desvinculacionId ?? 0)
  const devolver = useDevolverBien(desvinculacionId ?? 0)
  const [accion, setAccion] = useState<{ paso: PasoDeLaDesvinculacion; estado: 'hecho' | 'no_aplica' } | null>(null)
  const [aDevolver, setADevolver] = useState<{ asignacionId: number; bien: string } | null>(null)
  const [fecha, setFecha] = useState(hoyIso())
  const [observaciones, setObservaciones] = useState('')

  const cancelada = data?.estado === 'cancelada'

  return (
    <SgthModal
      opened={desvinculacionId !== null}
      onClose={onClose}
      title={data ? `Desvinculación de ${data.servidor}` : 'Desvinculación'}
      size="lg"
    >
      <DataState loading={isLoading} error={error} errorTitle="No se pudo cargar la desvinculación" onRetry={refetch}>
        {data && (
          <Stack gap="sm">
            <Text size="sm" c="dimmed">
              Sale el {formatFecha(data.fecha_salida)} · {data.causal ?? 'Cese'}{data.acto ? ` · ${data.acto}` : ''}
              {data.tipo_nombramiento ? ` · ${data.tipo_nombramiento}` : ''}
            </Text>

            {cancelada && (
              <Alert variant="light" color="slate" icon={<IconInfoCircle size={16} />}>
                {data.motivo_cancelacion ?? 'Se canceló.'}
              </Alert>
            )}
            {data.estado === 'cerrada' && (
              <Alert variant="light" color="emerald" icon={<IconCheck size={16} />}>
                Completa desde el {formatFechaHora(data.cerrada_en)}: cada paso está hecho o no aplica.
              </Alert>
            )}

            {data.pasos.map((p) => {
              // Lo que se puede deshacer: el «no aplica» decidido, y la liquidación o
              // la indemnización ya registradas (las deshace el Director).
              const decidido = p.paso !== 'reingreso' && (
                (p.estado === 'no_aplica' && !!p.nota)
                || (p.estado === 'hecho' && (p.paso === 'vacaciones' || p.paso === 'indemnizacion'))
              )
              const puedeMarcarHecho = p.se_marca && (!p.lo_marca_el_director || esDirector)

              return (
                <Paper key={p.paso} withBorder p="sm" radius="md">
                  <Stack gap={6}>
                    <Group justify="space-between" wrap="nowrap" gap="sm">
                      <Text size="sm" fw={600}>{p.etiqueta}</Text>
                      <StatusBadge tone={ESTADO_PASO[p.estado].tono}>{ESTADO_PASO[p.estado].etiqueta}</StatusBadge>
                    </Group>
                    <Text size="xs" c="dimmed">{p.responsable}</Text>
                    <Text size="sm">{p.detalle}</Text>

                    {p.nota && (
                      <Text size="xs">
                        <b>Nota:</b> {p.nota}
                        {p.registrado_por ? ` — ${p.registrado_por}` : ''}
                        {p.registrado_en ? `, ${formatFechaHora(p.registrado_en)}` : ''}
                      </Text>
                    )}

                    <PagoDeLaSalida
                      desvinculacionId={data.id}
                      paso={p}
                      esDirector={esDirector}
                      abierta={!cancelada}
                    />

                    {(p.bienes ?? []).map((b) => (
                      <Group key={b.asignacion_id} justify="space-between" wrap="nowrap" gap="sm">
                        <Text size="xs">
                          {b.bien}{b.codigo ? ` · ${b.codigo}` : ''}{b.numero_serie ? ` · serie ${b.numero_serie}` : ''}
                          {b.fecha_asignacion ? ` · desde el ${formatFecha(b.fecha_asignacion)}` : ''}
                        </Text>
                        {!cancelada && (
                          <Button
                            size="compact-xs"
                            variant="light"
                            leftSection={<IconPackageImport size={14} />}
                            onClick={() => { setFecha(hoyIso()); setObservaciones(''); setADevolver({ asignacionId: b.asignacion_id, bien: b.bien }) }}
                          >
                            Registrar la devolución
                          </Button>
                        )}
                      </Group>
                    ))}

                    {!cancelada && (
                      <Group gap="xs">
                        {p.estado === 'pendiente' && p.se_marca && (
                          <Button
                            size="compact-xs"
                            variant="light"
                            leftSection={<IconCheck size={14} />}
                            disabled={!puedeMarcarHecho}
                            title={puedeMarcarHecho ? undefined : 'Lo marca el Director de Talento Humano'}
                            onClick={() => setAccion({ paso: p, estado: 'hecho' })}
                          >
                            Marcar como hecho
                          </Button>
                        )}
                        {p.estado === 'pendiente' && p.paso !== 'reingreso' && (
                          <Button
                            size="compact-xs"
                            variant="subtle"
                            color="slate"
                            leftSection={<IconBan size={14} />}
                            disabled={p.lo_marca_el_director && !esDirector}
                            onClick={() => setAccion({ paso: p, estado: 'no_aplica' })}
                          >
                            No aplica
                          </Button>
                        )}
                        {decidido && (
                          <Button
                            size="compact-xs"
                            variant="subtle"
                            color="slate"
                            leftSection={<IconArrowBackUp size={14} />}
                            disabled={p.lo_marca_el_director && !esDirector}
                            loading={marcar.isPending}
                            onClick={() => marcar.mutate({ paso: p.paso, estado: 'pendiente' })}
                          >
                            Volver a pendiente
                          </Button>
                        )}
                      </Group>
                    )}
                  </Stack>
                </Paper>
              )
            })}
          </Stack>
        )}
      </DataState>

      <MotivoModal
        opened={!!accion}
        onClose={() => setAccion(null)}
        title={accion?.estado === 'hecho' ? `${accion.paso.etiqueta}: hecho` : `${accion?.paso.etiqueta ?? ''}: no aplica`}
        descripcion={accion?.estado === 'hecho'
          ? 'Anote cómo se hizo: el documento, la fecha o el monto.'
          : 'Diga por qué este paso no corresponde a esta salida.'}
        etiqueta={accion?.estado === 'hecho' ? 'Cómo se hizo' : 'Por qué no aplica'}
        confirmLabel={accion?.estado === 'hecho' ? 'Marcar como hecho' : 'No aplica'}
        cargando={marcar.isPending}
        onConfirm={(nota) => accion && marcar.mutate(
          { paso: accion.paso.paso, estado: accion.estado, nota },
          { onSuccess: () => setAccion(null) },
        )}
      />

      <SgthModal opened={!!aDevolver} onClose={() => setADevolver(null)} title="Registrar la devolución" size="sm">
        <Stack gap="sm">
          <Text size="sm">{aDevolver?.bien}</Text>
          <DatePickerInput
            label="Fecha de devolución"
            withAsterisk
            valueFormat="DD/MM/YYYY"
            maxDate={hoyIso()}
            value={toDateValue(fecha)}
            onChange={(d) => setFecha(fromDateValueOrNull(d) ?? hoyIso())}
          />
          <TextInput
            label="Observaciones"
            description="El acta de Tecnologías de la Información y el estado en que se recibe."
            value={observaciones}
            onChange={(e) => setObservaciones(e.currentTarget.value)}
          />
          <Group justify="flex-end">
            <Button variant="default" onClick={() => setADevolver(null)}>Cancelar</Button>
            <Button
              loading={devolver.isPending}
              onClick={() => aDevolver && devolver.mutate(
                { asignacionId: aDevolver.asignacionId, fecha, observaciones: observaciones.trim() || undefined },
                { onSuccess: () => setADevolver(null) },
              )}
            >
              Registrar
            </Button>
          </Group>
        </Stack>
      </SgthModal>
    </SgthModal>
  )
}
