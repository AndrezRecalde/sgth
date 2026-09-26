'use client'

import {
  confirmar, DetailList, notificar, SectionHeading, SgthDrawer, StatusBadge,
} from '@/components/ui'
import { useState } from 'react'
import { Alert, Button, Divider, Grid, Group, Skeleton, Stack, Text } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import {
  IconAlertTriangle, IconBan, IconCheck, IconFileDownload,
  IconPencil, IconUserOff,
} from '@tabler/icons-react'
import { expedienteService } from '../services/expedienteService'
import { getApiErrorMessage } from '@/types/api'
import { useMovimiento, useMovimientoMutations } from '../hooks/useMovimientoMutations'
import { BloqueDetalle } from './BloqueDetalle'
import { MovimientoModal } from './MovimientoModal'
import { CompletarVinculoModal } from './CompletarVinculoModal'
import { DictamenPresupuestarioModal } from './DictamenPresupuestarioModal'
import {
  TONO_ACCION, ESTADO_LABELS, TRANSICIONES, puedeDescargarPdf,
  requiereCompletarVinculo,
} from '../utils/estadoAccionPersonal'
import {
  SUBTIPO_LABELS, esAusenciaTemporal, etiquetaTipoMovimiento, proponeSituacion,
  tieneEfectoEconomico, type AccionSubtipo,
} from '../utils/taxonomiaAccionPersonal'
import { etiquetaNombramiento } from '../utils/tipoNombramientoOptions'
import type { EstadoAccionPersonal } from '@/types/api'
import { guardarArchivo } from '@/lib/archivo'
import { formatFecha } from '@/lib/fecha'

interface Props {
  opened: boolean
  onClose: () => void
  movimientoId: number | null
}

function dinero(v?: string | number | null): string {
  return v != null ? `$ ${Number(v).toFixed(2)}` : '—'
}

/**
 * Revisión de una acción de personal antes de decidir sobre ella. Todo es de
 * solo lectura: editar exige que siga en borrador, y aprobar un ingreso abre
 * el formulario que completa los datos del vínculo.
 */
export function AccionPersonalDetalleDrawer({ opened, onClose, movimientoId }: Props) {
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
      guardarArchivo(blob, `accion_personal_${m.codigo_registro ?? m.id}.pdf`)
    } catch (error) {
      notificar.error('No se pudo generar el PDF de la acción de personal', getApiErrorMessage(error, 'Inténtalo de nuevo en unos segundos.'))
    } finally {
      setDescargando(false)
    }
  }

  const contenido = () => {
    if (isLoading || !m) return <Skeleton height={420} radius="md" />

    const mv = m
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
            <StatusBadge tone={TONO_ACCION[estado]}>
              {ESTADO_LABELS[estado]}
            </StatusBadge>
          )}
        </Group>

        <BloqueDetalle>
          <DetailList items={[
            {
              label: 'Servidor',
              value: [mv.servidor?.apellido, mv.servidor?.nombre].filter(Boolean).join(' '),
            },
            { label: 'Cédula', value: mv.servidor?.cedula },
            { label: 'Rige desde', value: formatFecha(m.fecha_efectiva) },
            { label: 'Código', value: m.codigo_registro },
          ]} />
        </BloqueDetalle>

        <div>
          <SectionHeading title="Explicación" mb={4} />
          <Text size="sm">{m.descripcion}</Text>
        </div>

        {/* Las dos columnas del documento impreso. La actual quedó congelada
            al crear la acción, así que refleja dónde estaba el servidor
            entonces — no dónde está hoy. */}
        <Grid>
          {/* Sin columna derecha —cesación, sanción— la actual ocupa el ancho
              completo en vez de dejar medio panel vacío. */}
          <Grid.Col span={{ base: 12, sm: propone || ausencia ? 6 : 12 }}>
            <BloqueDetalle hundido altoCompleto>
              <SectionHeading
                title={propone ? 'Situación actual' : 'Situación del servidor'}
                mb="xs"
              />
              {esIngreso ? (
                <Text size="sm" c="dimmed">
                  Sin vínculo previo — este es el primer ingreso del servidor.
                </Text>
              ) : (
                <DetailList columnas={1} items={[
                  { label: 'Apellidos', value: apellidos },
                  { label: 'Nombres', value: nombres },
                  { label: 'Cédula', value: mv.servidor?.cedula },
                  { label: 'Papeleta de votación', value: mv.servidor?.numero_papeleta_votacion },
                  { label: 'Unidad', value: mv.unidad_origen?.nombre },
                  { label: 'Puesto', value: mv.puesto_origen?.cargo?.nombre },
                  { label: 'R.M.U.', value: dinero(m.remuneracion_origen) },
                  { label: 'Partida', value: mv.partida_origen?.codigo },
                ]} />
              )}

              {/* Solo cuando no hay tarjeta a la derecha donde anclarlo. */}
              {!propone && !ausencia && botonEditar}
            </BloqueDetalle>
          </Grid.Col>

          {/* Una cesación no propone nada: termina el vínculo. Una comisión o
              una licencia dejan al servidor en su puesto, y lo que las define
              es el período. Reservar la columna para todas obligaba a
              rellenarla de guiones. */}
          {propone && (
            <Grid.Col span={{ base: 12, sm: 6 }}>
              <BloqueDetalle altoCompleto>
                <SectionHeading
                  title={esSubrogacion ? 'Puesto subrogado' : 'Situación propuesta'}
                  mb="xs"
                />
                <DetailList columnas={1} items={[
                  { label: 'Unidad', value: mv.unidad_destino?.nombre },
                  { label: 'Puesto', value: mv.puesto_destino?.cargo?.nombre },
                  ...(esSubrogacion
                    ? []
                    : [{ label: 'Lugar de trabajo', value: m.lugar_trabajo }]),
                  {
                    label: 'Partida',
                    // La de la acción manda; si Talento Humano no fijó
                    // ninguna, rige la del puesto de destino.
                    value: mv.partida_presupuestaria?.codigo
                      ?? mv.puesto_destino?.partida_presupuestaria?.codigo,
                  },
                  {
                    label: esSubrogacion ? 'R.M.U. del puesto' : 'R.M.U. propuesta',
                    value: dinero(m.remuneracion_propuesta),
                  },
                  // Lo que realmente se autoriza en una subrogación: no el
                  // sueldo del puesto, sino la diferencia contra lo que el
                  // servidor ya percibe (Art. 21 Reglamento LOSEP). Ambas
                  // cifras quedaron congeladas al crear la acción, así que
                  // esta resta es la que se aprobó, no la de hoy.
                  ...(esSubrogacion
                    ? [{
                        label: 'Diferencia a pagar',
                        value: diferencia != null && diferencia > 0 ? dinero(diferencia) : null,
                      }]
                    : []),
                ]} />

                {botonEditar}
              </BloqueDetalle>
            </Grid.Col>
          )}

          {ausencia && (
            <Grid.Col span={{ base: 12, sm: 6 }}>
              <BloqueDetalle altoCompleto>
                <SectionHeading title="Período de la ausencia" mb="xs" />
                <DetailList columnas={1} items={[
                  { label: 'Desde', value: formatFecha(m.fecha_inicio) },
                  {
                    label: 'Hasta',
                    value: m.fecha_fin ? formatFecha(m.fecha_fin) : 'Sin fecha de fin',
                  },
                  { label: 'Destino', value: mv.unidad_destino?.nombre },
                ]} />
                <Text size="xs" c="dimmed" mt="xs">
                  El servidor conserva su puesto y su plaza; regresa al vencer
                  el período.
                </Text>

                {botonEditar}
              </BloqueDetalle>
            </Grid.Col>
          )}
        </Grid>

        {/* Los datos de la contratación solo existen en el ingreso, que es la
            única acción que da origen a un contrato. En un traspaso o una
            comisión salían todos en blanco y hacían creer que faltaba algo por
            llenar. Resolución y dictamen sí aplican a cualquier acción, así que
            se quedan fuera de ese bloque. */}
        {esIngreso && (
          <BloqueDetalle>
            <SectionHeading title="Datos de la contratación" mb="xs" />
            <DetailList items={[
              {
                label: 'Nombramiento',
                value: etiquetaNombramiento(m.tipo_nombramiento_propuesto),
              },
              { label: 'N.º de contrato', value: m.numero_contrato },
              { label: 'Remuneración', value: dinero(m.remuneracion_propuesta) },
              {
                label: 'Marca asistencia',
                value: m.puede_marcar == null ? null : (m.puede_marcar ? 'Sí' : 'No'),
              },
            ]} />
          </BloqueDetalle>
        )}

        <BloqueDetalle>
          <SectionHeading title="Respaldos" mb="xs" />
          <DetailList items={[
            { label: 'N.º de resolución', value: m.resolucion_numero },
            {
              label: 'Dictamen médico',
              value: m.requiere_dictamen_medico
                ? (mv.solicitud_certificacion?.dictamen ?? 'Pendiente')
                : 'No requiere',
            },
            ...(m.caucionado
              ? [
                  { label: 'Caución N.º', value: m.caucion_numero },
                  { label: 'Fecha de caución', value: formatFecha(m.caucion_fecha) },
                ]
              : []),
          ]} />
        </BloqueDetalle>

        {(m.firmante_autoridad_nombre || m.firmante_th_nombre) && (
          <BloqueDetalle>
            <SectionHeading title="Firmantes sellados" mb="xs" />
            <DetailList items={[
              {
                label: m.firmante_autoridad_cargo ?? 'Autoridad',
                value: m.firmante_autoridad_nombre,
              },
              {
                label: m.firmante_th_cargo ?? 'Talento Humano',
                value: m.firmante_th_nombre,
              },
            ]} />
          </BloqueDetalle>
        )}

        {mv.cubre_movimiento && (
          <Alert variant="light" color="amethyst" icon={<IconUserOff size={16} />}>
            Contratación de reemplazo: cubre la ausencia de{' '}
            <strong>
              {[mv.cubre_movimiento.servidor?.apellido, mv.cubre_movimiento.servidor?.nombre]
                .filter(Boolean).join(' ') || 'un servidor'}
            </strong>
            {mv.cubre_movimiento.fecha_fin
              ? `, que regresa el ${formatFecha(mv.cubre_movimiento.fecha_fin)}.`
              : '.'}{' '}
            No consume plaza: la sigue ocupando el titular.
          </Alert>
        )}

        {esIngreso && estado === 'suscrita' && (
          <Alert variant="light" color="amber" icon={<IconAlertTriangle size={16} />}>
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
                onClick={() => confirmar({
                  title:   'Anular acción de personal',
                  message: 'Se anulará esta acción de personal y no podrá reactivarse.',
                  destructiva: true,
                  confirmLabel: 'Anular',
                  onConfirm: () =>
                    transicionar.mutate({ id: Number(m.id), estado: 'anulada' }, { onSuccess: onClose }),
                })}
              >
                Anular
              </Button>
            )}

            {avanzar.map((destino) => (
              <Button
                key={destino}
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
    <SgthDrawer
      opened={opened}
      onClose={onClose}
      title="Acción de personal"
      ancho="lg"
    >
      {contenido()}
    </SgthDrawer>
  )
}
