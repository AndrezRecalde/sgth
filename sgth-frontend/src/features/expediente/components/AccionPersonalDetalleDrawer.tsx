'use client'

import { useState } from 'react'
import {
  Alert, Badge, Box, Button, Divider, Drawer, Grid, Group, Paper,
  Skeleton, Stack, Text,
} from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import {
  IconAlertTriangle, IconBan, IconCheck, IconFileDownload,
  IconPencil, IconUserOff,
} from '@tabler/icons-react'
import { notifications } from '@mantine/notifications'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { expedienteService } from '../services/expedienteService'
import { getApiErrorMessage } from '@/types/api'
import { useMovimiento, useMovimientoMutations } from '../hooks/useMovimientoMutations'
import { MovimientoModal } from './MovimientoModal'
import { CompletarVinculoModal } from './CompletarVinculoModal'
import { DictamenPresupuestarioModal } from './DictamenPresupuestarioModal'
import {
  ESTADO_COLORS, ESTADO_LABELS, TRANSICIONES, puedeDescargarPdf,
  requiereCompletarVinculo,
} from '../utils/estadoAccionPersonal'
import {
  SUBTIPO_LABELS, esAusenciaTemporal, etiquetaTipoMovimiento, proponeSituacion,
  tieneEfectoEconomico, type AccionSubtipo,
} from '../utils/taxonomiaAccionPersonal'
import { etiquetaNombramiento } from '../utils/tipoNombramientoOptions'
import type { EstadoAccionPersonal } from '@/types/api'

interface Props {
  opened: boolean
  onClose: () => void
  movimientoId: number | null
}

/**
 * Relaciones que trae el endpoint de detalle y que el tipo generado de
 * MovimientoPersonal no describe.
 */
type RelacionesMovimiento = {
  servidor?: {
    nombre?: string | null
    segundo_nombre?: string | null
    apellido?: string | null
    segundo_apellido?: string | null
    cedula?: string | null
    numero_papeleta_votacion?: string | null
  } | null
  unidad_origen?: { nombre?: string | null } | null
  puesto_origen?: { cargo?: { nombre?: string | null } | null } | null
  partida_origen?: { codigo?: string | null } | null
  unidad_destino?: { nombre?: string | null } | null
  puesto_destino?: {
    cargo?: { nombre?: string | null } | null
    partida_presupuestaria?: { codigo?: string | null } | null
  } | null
  partida_presupuestaria?: { codigo?: string | null } | null
  solicitud_certificacion?: { dictamen?: string | null; estado?: string | null } | null
  cubre_movimiento?: {
    id?: number
    codigo_registro?: string | null
    fecha_fin?: string | null
    servidor?: { nombre?: string | null; apellido?: string | null } | null
  } | null
}

function fecha(f?: string | null): string {
  if (!f) return '—'
  return new Date(f).toLocaleDateString('es-EC', {
    day: '2-digit', month: '2-digit', year: 'numeric', timeZone: 'UTC',
  })
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

/**
 * Revisión de una acción de personal antes de decidir sobre ella. Todo es de
 * solo lectura: editar exige que siga en borrador, y aprobar un ingreso abre
 * el formulario que completa los datos del vínculo.
 */
export function AccionPersonalDetalleDrawer({ opened, onClose, movimientoId }: Props) {
  const { isMobile } = useMobileBreakpoint()
  const { data: m, isLoading } = useMovimiento(opened ? movimientoId : null)
  const { transicionar } = useMovimientoMutations()

  const [editarOpened, { open: abrirEditar, close: cerrarEditar }] = useDisclosure(false)
  const [descargando, setDescargando] = useState(false)

  /**
   * El formulario de contratación aparte existe por un solo motivo: una acción
   * suscrita ya no se edita, pero el contrato todavía necesita nacer con
   * número y remuneración. En borrador todo se corrige en el formulario
   * completo de la acción, así que aquí no hay modo 'editar'.
   */
  const [aprobarOpened, { open: abrirAprobar, close: cerrarAprobar }] = useDisclosure(false)

  /** Referencia de la certificación presupuestaria, exigida al suscribir. */
  const [dictamenOpened, { open: abrirDictamen, close: cerrarDictamen }] = useDisclosure(false)

  const descargarPdf = async () => {
    if (!m) return
    setDescargando(true)
    try {
      const blob = await expedienteService.descargarAccionPersonalPdf(Number(m.id))
      const url = URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = url
      link.download = `accion_personal_${m.codigo_registro ?? m.id}.pdf`
      link.click()
      URL.revokeObjectURL(url)
    } catch (error) {
      notifications.show({
        title: 'Error',
        message: getApiErrorMessage(error, 'No se pudo generar el PDF.'),
        color: 'red',
      })
    } finally {
      setDescargando(false)
    }
  }

  const contenido = () => {
    if (isLoading || !m) return <Skeleton height={420} radius="md" />

    const mv = m as unknown as RelacionesMovimiento
    const estado = m.estado as EstadoAccionPersonal | undefined
    const posibles = estado ? TRANSICIONES[estado] : []
    const avanzar = posibles.filter((e) => e !== 'anulada')
    const puedeAnular = posibles.includes('anulada')
    const esIngreso = m.tipo_movimiento === 'ingreso'
    const subtipo = m.subtipo_movimiento as AccionSubtipo | null | undefined
    const propone = proponeSituacion(m.tipo_movimiento, subtipo)
    const ausencia = esAusenciaTemporal(m.tipo_movimiento, subtipo)
    const esSubrogacion = m.tipo_movimiento === 'subrogacion'

    const diferencia = m.remuneracion_propuesta != null && m.remuneracion_origen != null
      ? Number(m.remuneracion_propuesta) - Number(m.remuneracion_origen)
      : null

    /**
     * Único punto de edición del drawer. Se ancla al pie de la tarjeta de la
     * derecha; cuando esa tarjeta no existe —cesación, sanción— baja a la
     * única que hay, para que nunca quede una acción en borrador sin forma de
     * corregirla.
     *
     * La subrogación se excluye: su acción es el reflejo de una fila en
     * `subrogaciones`, y este modal solo escribiría el movimiento. Cambiar
     * aquí el puesto dejaría a los dos registros diciendo cosas distintas —
     * uno para el documento, otro para quién puede firmar. Se corrige
     * cancelándola y volviéndola a registrar.
     */
    const botonEditar = esSubrogacion ? (
      <Text size="xs" c="dimmed" mt="xs">
        Para corregirla, cancele la subrogación y regístrela de nuevo.
      </Text>
    ) : (
      <Button
        size="xs"
        variant="light"
        mt="xs"
        fullWidth
        leftSection={<IconPencil size={14} />}
        disabled={estado !== 'borrador'}
        onClick={abrirEditar}
      >
        {estado === 'borrador' ? 'Editar la acción' : 'Solo se edita en borrador'}
      </Button>
    )

    const nombres = [mv.servidor?.nombre, mv.servidor?.segundo_nombre].filter(Boolean).join(' ')
    const apellidos = [mv.servidor?.apellido, mv.servidor?.segundo_apellido].filter(Boolean).join(' ')

    return (
      <Stack gap="md">
        <Group justify="space-between" align="flex-start">
          <div>
            <Text fw={600}>
              {etiquetaTipoMovimiento(m.tipo_movimiento)}
            </Text>
            {m.subtipo_movimiento && (
              <Text size="sm" c="dimmed">
                {SUBTIPO_LABELS[m.subtipo_movimiento as keyof typeof SUBTIPO_LABELS]}
              </Text>
            )}
          </div>
          {estado && (
            <Badge color={ESTADO_COLORS[estado]} variant="light">
              {ESTADO_LABELS[estado]}
            </Badge>
          )}
        </Group>

        <Paper withBorder p="sm" radius="md">
          <Grid>
            <Grid.Col span={6}>
              <Dato
                etiqueta="Servidor"
                valor={[mv.servidor?.apellido, mv.servidor?.nombre].filter(Boolean).join(' ')}
              />
            </Grid.Col>
            <Grid.Col span={6}><Dato etiqueta="Cédula" valor={mv.servidor?.cedula} /></Grid.Col>
            <Grid.Col span={6}><Dato etiqueta="Rige desde" valor={fecha(m.fecha_efectiva)} /></Grid.Col>
            <Grid.Col span={6}><Dato etiqueta="Código" valor={m.codigo_registro} /></Grid.Col>
          </Grid>
        </Paper>

        <div>
          <Text size="xs" fw={600} c="dimmed" tt="uppercase" mb={4}>Explicación</Text>
          <Text size="sm">{m.descripcion}</Text>
        </div>

        {/* Las dos columnas del documento impreso. La actual quedó congelada
            al crear la acción, así que refleja dónde estaba el servidor
            entonces — no dónde está hoy. */}
        <Grid>
          {/* Sin columna derecha —cesación, sanción— la actual ocupa el ancho
              completo en vez de dejar medio panel vacío. */}
          <Grid.Col span={{ base: 12, sm: propone || ausencia ? 6 : 12 }}>
            <Paper withBorder p="sm" radius="md" h="100%" bg="var(--mantine-color-gray-0)">
              <Text size="sm" fw={700} mb="xs">
                {propone ? 'SITUACIÓN ACTUAL' : 'SITUACIÓN DEL SERVIDOR'}
              </Text>
              {esIngreso ? (
                <Text size="sm" c="dimmed">
                  Sin vínculo previo — este es el primer ingreso del servidor.
                </Text>
              ) : (
                <Stack gap="xs">
                  <Dato etiqueta="Apellidos" valor={apellidos} />
                  <Dato etiqueta="Nombres" valor={nombres} />
                  <Dato etiqueta="Cédula" valor={mv.servidor?.cedula} />
                  <Dato etiqueta="Papeleta de votación" valor={mv.servidor?.numero_papeleta_votacion} />
                  <Dato etiqueta="Unidad" valor={mv.unidad_origen?.nombre} />
                  <Dato etiqueta="Puesto" valor={mv.puesto_origen?.cargo?.nombre} />
                  <Dato etiqueta="R.M.U." valor={dinero(m.remuneracion_origen)} />
                  <Dato etiqueta="Partida" valor={mv.partida_origen?.codigo} />
                </Stack>
              )}

              {/* Solo cuando no hay tarjeta a la derecha donde anclarlo. */}
              {!propone && !ausencia && botonEditar}
            </Paper>
          </Grid.Col>

          {/* Una cesación no propone nada: termina el vínculo. Una comisión o
              una licencia dejan al servidor en su puesto, y lo que las define
              es el período. Reservar la columna para todas obligaba a
              rellenarla de guiones. */}
          {propone && (
            <Grid.Col span={{ base: 12, sm: 6 }}>
              <Paper withBorder p="sm" radius="md" h="100%">
                <Text size="sm" fw={700} mb="xs">
                  {esSubrogacion ? 'PUESTO SUBROGADO' : 'SITUACIÓN PROPUESTA'}
                </Text>
                <Stack gap="xs">
                  <Dato etiqueta="Unidad" valor={mv.unidad_destino?.nombre} />
                  <Dato etiqueta="Puesto" valor={mv.puesto_destino?.cargo?.nombre} />
                  {!esSubrogacion && (
                    <Dato etiqueta="Lugar de trabajo" valor={m.lugar_trabajo} />
                  )}
                  {/* La de la acción manda; si Talento Humano no fijó ninguna,
                      rige la del puesto de destino. */}
                  <Dato
                    etiqueta="Partida"
                    valor={mv.partida_presupuestaria?.codigo
                      ?? mv.puesto_destino?.partida_presupuestaria?.codigo}
                  />
                  <Dato
                    etiqueta={esSubrogacion ? 'R.M.U. del puesto' : 'R.M.U. propuesta'}
                    valor={dinero(m.remuneracion_propuesta)}
                  />
                  {/* Lo que realmente se autoriza en una subrogación: no el
                      sueldo del puesto, sino la diferencia contra lo que el
                      servidor ya percibe (Art. 21 Reglamento LOSEP). Ambas
                      cifras quedaron congeladas al crear la acción, así que
                      esta resta es la que se aprobó, no la de hoy. */}
                  {esSubrogacion && (
                    <Dato
                      etiqueta="Diferencia a pagar"
                      valor={diferencia != null && diferencia > 0 ? dinero(diferencia) : null}
                    />
                  )}
                </Stack>

                {botonEditar}
              </Paper>
            </Grid.Col>
          )}

          {ausencia && (
            <Grid.Col span={{ base: 12, sm: 6 }}>
              <Paper withBorder p="sm" radius="md" h="100%">
                <Text size="sm" fw={700} mb="xs">PERÍODO DE LA AUSENCIA</Text>
                <Stack gap="xs">
                  <Dato etiqueta="Desde" valor={fecha(m.fecha_inicio)} />
                  <Dato etiqueta="Hasta" valor={m.fecha_fin ? fecha(m.fecha_fin) : 'Sin fecha de fin'} />
                  <Dato etiqueta="Destino" valor={mv.unidad_destino?.nombre} />
                  <Text size="xs" c="dimmed" mt={4}>
                    El servidor conserva su puesto y su plaza; regresa al vencer
                    el período.
                  </Text>
                </Stack>

                {botonEditar}
              </Paper>
            </Grid.Col>
          )}
        </Grid>

        {/* Los datos de la contratación solo existen en el ingreso, que es la
            única acción que da origen a un contrato. En un traspaso o una
            comisión salían todos en blanco y hacían creer que faltaba algo por
            llenar. Resolución y dictamen sí aplican a cualquier acción, así que
            se quedan fuera de ese bloque. */}
        {esIngreso && (
          <Paper withBorder p="sm" radius="md">
            <Text size="sm" fw={700} mb="xs">DATOS DE LA CONTRATACIÓN</Text>
            <Grid>
              <Grid.Col span={6}>
                <Dato
                  etiqueta="Nombramiento"
                  valor={etiquetaNombramiento(m.tipo_nombramiento_propuesto)}
                />
              </Grid.Col>
              <Grid.Col span={6}><Dato etiqueta="N.º de contrato" valor={m.numero_contrato} /></Grid.Col>
              <Grid.Col span={6}><Dato etiqueta="Remuneración" valor={dinero(m.remuneracion_propuesta)} /></Grid.Col>
              <Grid.Col span={6}>
                <Dato etiqueta="Marca asistencia" valor={m.puede_marcar == null ? '—' : (m.puede_marcar ? 'Sí' : 'No')} />
              </Grid.Col>
            </Grid>
          </Paper>
        )}

        <Paper withBorder p="sm" radius="md">
          <Text size="sm" fw={700} mb="xs">RESPALDOS</Text>
          <Grid>
            <Grid.Col span={6}><Dato etiqueta="N.º de resolución" valor={m.resolucion_numero} /></Grid.Col>
            <Grid.Col span={6}>
              <Dato
                etiqueta="Dictamen médico"
                valor={m.requiere_dictamen_medico
                  ? (mv.solicitud_certificacion?.dictamen ?? 'Pendiente')
                  : 'No requiere'}
              />
            </Grid.Col>
            {m.caucionado && (
              <>
                <Grid.Col span={6}><Dato etiqueta="Caución N.º" valor={m.caucion_numero} /></Grid.Col>
                <Grid.Col span={6}><Dato etiqueta="Fecha de caución" valor={fecha(m.caucion_fecha)} /></Grid.Col>
              </>
            )}
          </Grid>
        </Paper>

        {(m.firmante_autoridad_nombre || m.firmante_th_nombre) && (
          <Paper withBorder p="sm" radius="md">
            <Text size="sm" fw={700} mb="xs">FIRMANTES SELLADOS</Text>
            <Grid>
              <Grid.Col span={6}>
                <Dato etiqueta={m.firmante_autoridad_cargo ?? 'Autoridad'} valor={m.firmante_autoridad_nombre} />
              </Grid.Col>
              <Grid.Col span={6}>
                <Dato etiqueta={m.firmante_th_cargo ?? 'Talento Humano'} valor={m.firmante_th_nombre} />
              </Grid.Col>
            </Grid>
          </Paper>
        )}

        {mv.cubre_movimiento && (
          <Alert variant="light" color="violet" icon={<IconUserOff size={16} />}>
            Contratación de reemplazo: cubre la ausencia de{' '}
            <strong>
              {[mv.cubre_movimiento.servidor?.apellido, mv.cubre_movimiento.servidor?.nombre]
                .filter(Boolean).join(' ') || 'un servidor'}
            </strong>
            {mv.cubre_movimiento.fecha_fin
              ? `, que regresa el ${fecha(mv.cubre_movimiento.fecha_fin)}.`
              : '.'}{' '}
            No consume plaza: la sigue ocupando el titular.
          </Alert>
        )}

        {esIngreso && estado === 'suscrita' && (
          <Alert variant="light" color="orange" icon={<IconAlertTriangle size={16} />}>
            Al aprobar se creará el contrato. Se pedirán el número de contrato y la
            remuneración, que aún no están registrados.
          </Alert>
        )}

        <Divider />

        {/* La barra inferior queda solo para lo que hace avanzar el trámite.
            Editar vive junto a los datos que corrige. */}
        <Group justify="flex-end">
          <Group>
            {puedeDescargarPdf(estado, m.tipo_movimiento) && (
              <Button
                variant="subtle"
                leftSection={<IconFileDownload size={14} />}
                loading={descargando}
                onClick={descargarPdf}
              >
                PDF
              </Button>
            )}

            {puedeAnular && (
              <Button
                variant="subtle"
                color="red"
                leftSection={<IconBan size={14} />}
                onClick={() => {
                  if (confirm('¿Anular esta acción de personal? No podrá reactivarse.')) {
                    transicionar.mutate({ id: Number(m.id), estado: 'anulada' }, { onSuccess: onClose })
                  }
                }}
              >
                Anular
              </Button>
            )}

            {avanzar.map((destino) => (
              <Button
                key={destino}
                color="emerald"
                leftSection={<IconCheck size={14} />}
                loading={transicionar.isPending}
                onClick={() => {
                  // Un ingreso que pasa a registrada crea el contrato: se
                  // completan primero sus datos en vez de fallar después.
                  if (destino === 'registrada' && requiereCompletarVinculo(estado, m.tipo_movimiento)) {
                    abrirAprobar()
                    return
                  }
                  // Mismo criterio para el dictamen presupuestario: el backend
                  // rechaza suscribir sin él, así que se pide antes en vez de
                  // dejar que la transición falle.
                  if (destino === 'suscrita' && tieneEfectoEconomico(m.tipo_movimiento)) {
                    abrirDictamen()
                    return
                  }
                  transicionar.mutate({ id: Number(m.id), estado: destino })
                }}
              >
                Pasar a {ESTADO_LABELS[destino]}
              </Button>
            ))}
          </Group>
        </Group>

        {/* El mismo formulario con el que se registró la acción, en modo
            edición: un solo sitio donde corregir cada campo. */}
        <MovimientoModal
          opened={editarOpened}
          onClose={cerrarEditar}
          servidorId={m.servidor_id}
          movimiento={m}
        />

        <CompletarVinculoModal
          opened={aprobarOpened}
          onClose={cerrarAprobar}
          // Al aprobar la acción cambia de estado, así que el drawer se cierra
          // para no dejar a la vista un detalle que ya quedó obsoleto.
          onSaved={onClose}
          movimiento={m}
        />

        <DictamenPresupuestarioModal
          opened={dictamenOpened}
          onClose={cerrarDictamen}
          movimiento={m}
        />
      </Stack>
    )
  }

  return (
    <Drawer
      opened={opened}
      onClose={onClose}
      position="right"
      size={isMobile ? '100%' : 'lg'}
      title="Acción de personal"
    >
      {contenido()}
    </Drawer>
  )
}
