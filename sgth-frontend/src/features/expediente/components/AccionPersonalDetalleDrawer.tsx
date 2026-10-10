'use client'

import {
  DataState, DetailList, SectionHeading, SgthDrawer,
} from '@/components/ui'
import { Alert, Group, Stack, Text } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconAlertTriangle, IconFileOff, IconUserOff } from '@tabler/icons-react'
import { useMovimiento } from '../hooks/useMovimientoMutations'
import { AccionAnotaciones } from './AccionAnotaciones'
import { EstadoAccionBadge } from './EstadoAccionBadge'
import { AccionBotonEditar } from './AccionBotonEditar'
import { AccionPersonalPie } from './AccionPersonalPie'
import { AccionRespaldos } from './AccionRespaldos'
import { AccionSituacionBloques } from './AccionSituacionBloques'
import { BloqueDetalle } from './BloqueDetalle'
import { MovimientoModal } from './MovimientoModal'
import { CompletarVinculoModal } from './CompletarVinculoModal'
import { DictamenPresupuestarioModal } from './DictamenPresupuestarioModal'
import type { MovimientoPersonal } from '@/types/api'
import { formatFecha } from '@/lib/fecha'

interface Props {
  opened: boolean
  onClose: () => void
  movimientoId: number | null
}

/**
 * Revisión de una acción de personal antes de decidir sobre ella. Todo es de
 * solo lectura: editar exige que siga en borrador, y aprobar un ingreso abre
 * el formulario que completa los datos del vínculo.
 */
export function AccionPersonalDetalleDrawer({ opened, onClose, movimientoId }: Props) {
  const { data: m, isLoading, error, refetch } = useMovimiento(opened ? movimientoId : null)

  const [editarOpened, { open: abrirEditar, close: cerrarEditar }] = useDisclosure(false)

  /**
   * El formulario de contratación aparte existe por un solo motivo: una acción
   * suscrita ya no se edita, pero el contrato todavía necesita nacer con
   * número y remuneración. En borrador todo se corrige en el formulario
   * completo de la acción, así que aquí no hay modo 'editar'.
   */
  const [aprobarOpened, { open: abrirAprobar, close: cerrarAprobar }] = useDisclosure(false)

  /** Referencia de la certificación presupuestaria, exigida al suscribir. */
  const [dictamenOpened, { open: abrirDictamen, close: cerrarDictamen }] = useDisclosure(false)

  const contenido = (m: MovimientoPersonal) => {
    const estado = m.estado
    const esIngreso = m.clase === 'ingreso'

    return (
      <Stack gap="md">
        <Group justify="space-between" align="flex-start">
          <div>
            <Text fw={600}>{m.etiqueta}</Text>
            {m.causal_etiqueta && (
              <Text size="sm" c="dimmed">
                {m.causal_etiqueta}
                {m.causal_base_legal ? ` · ${m.causal_base_legal}` : ''}
              </Text>
            )}
          </div>
          <EstadoAccionBadge m={m} />
        </Group>

        <BloqueDetalle>
          <DetailList items={[
            {
              label: 'Servidor',
              value: [m.servidor?.apellido, m.servidor?.nombre].filter(Boolean).join(' '),
            },
            { label: 'Cédula', value: m.servidor?.cedula },
            { label: 'Rige desde', value: formatFecha(m.fecha_efectiva) },
            { label: 'Código', value: m.codigo_registro },
          ]} />
        </BloqueDetalle>

        {m.aviso_proteccion && (
          // Un aviso que detiene el trámite va en amber, como los demás
          // bloqueos del cajón (regla 03).
          <Alert variant="light" color="amber" icon={<IconAlertTriangle size={16} />}>
            {m.aviso_proteccion}
          </Alert>
        )}

        <div>
          <SectionHeading title="Explicación" mb={4} />
          <Text size="sm">{m.descripcion}</Text>
        </div>

        {/* Las dos columnas del documento impreso. La actual quedó congelada
            al crear la acción, así que refleja dónde estaba el servidor
            entonces — no dónde está hoy. */}
        <AccionSituacionBloques m={m} botonEditar={<AccionBotonEditar m={m} onEditar={abrirEditar} />} />

        <AccionRespaldos m={m} />

        <AccionAnotaciones m={m} />

        {m.cubre_movimiento && (
          <Alert variant="light" color="ocean" icon={<IconUserOff size={16} />}>
            Contratación de reemplazo: cubre la ausencia de{' '}
            <strong>
              {[m.cubre_movimiento.servidor?.apellido, m.cubre_movimiento.servidor?.nombre]
                .filter(Boolean).join(' ') || 'un servidor'}
            </strong>
            {m.cubre_movimiento.fecha_fin
              ? `, que regresa el ${formatFecha(m.cubre_movimiento.fecha_fin)}.`
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

        <AccionPersonalPie
          m={m}
          onClose={onClose}
          onCompletarVinculo={abrirAprobar}
          onPedirDictamen={abrirDictamen}
        />

        {/* El mismo formulario con el que se registró la acción, en modo
            edición: un solo sitio donde corregir cada campo.

            `tipoNombramiento` no se pasaba, y de él depende el régimen: sin él,
            `esLosep(undefined)` daba true y la R.M.U. salía en solo lectura
            diciendo «No se edita en régimen LOSEP» al corregir el borrador de un
            obrero del Código del Trabajo, que es de los dos casos en que se
            negocia en el contrato. */}
        <MovimientoModal
          opened={editarOpened}
          onClose={cerrarEditar}
          servidorId={m.servidor_id}
          tipoNombramiento={m.servidor?.contrato_vigente?.tipo_nombramiento}
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
      {/* Los cuatro estados, no solo el normal.
          Antes era `if (isLoading || !m) return <Skeleton />`: con la consulta
          caída —un 403, un 500— `isLoading` es falso y `data` indefinido, así
          que el cajón se quedaba con el esqueleto para siempre y sin decir por
          qué. Le pasaba en particular a asistente-uath, que veía la bandeja y
          recibía 403 en el detalle: lo que veía era una caja que cargaba sin fin. */}
      <DataState
        loading={isLoading}
        error={error}
        errorTitle="No se pudo cargar la acción de personal"
        onRetry={refetch}
        skeletonRows={8}
        empty={!m}
        emptyProps={{
          icon: IconFileOff,
          title: 'No se encontró la acción de personal',
          description: 'Puede haber sido anulada o corregida por otro registro. Vuelva a la bandeja y ábrala desde allí.',
        }}
      >
        {m && contenido(m)}
      </DataState>
    </SgthDrawer>
  )
}
