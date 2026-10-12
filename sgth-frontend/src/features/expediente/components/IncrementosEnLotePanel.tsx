'use client'

import { useState } from 'react'
import { Alert, Button, Group, Stack, Text } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconBan, IconChecks, IconInfoCircle, IconList, IconPlus, IconStack2 } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import {
  DataState, MotivoModal, SgthModal, SgthTable, StatusBadge, TableActions, confirmar, type TableAction,
} from '@/components/ui'
import { useAuth } from '@/hooks/useAuth'
import { formatFecha } from '@/lib/fecha'
import { useDescartarLote, useLoteAccion, useLotesAccion, useRegistrarLote } from '../hooks/useContratoColectivo'
import type { ActoDelLote, LoteAccion, ResultadoDelLote } from '../services/contratoColectivoService'
import { ESTADO_LABELS } from '../utils/estadoAccionPersonal'
import { dinero } from '../utils/dinero'
import { IncrementoEnLoteModal } from './IncrementoEnLoteModal'

const enTramite = (l: LoteAccion) => (l.por_estado.borrador ?? 0) + (l.por_estado.suscrita ?? 0)

const calculo = (l: LoteAccion) => (l.parametros.modo === 'porcentaje'
  ? `${l.parametros.valor} %`
  : `${dinero(l.parametros.valor)} adicionales`)

/**
 * Los incrementos de remuneración de los obreros en lote (diseño, 9.15; fase
 * 4.6). Cada lote prepara un borrador por obrero; desde aquí se suscriben y
 * registran todos, y el que falla queda en su estado para corregirlo suelto.
 */
export function IncrementosEnLotePanel() {
  const { data: lotes = [], isLoading, error, refetch } = useLotesAccion()
  const { hasPermiso } = useAuth()
  const puedePreparar = hasPermiso('preparar-accion-personal')
  const puedeRegistrar = hasPermiso('suscribir-accion-personal') && hasPermiso('registrar-accion-personal')
  const puedeAnular = hasPermiso('anular-accion-personal')

  const [nuevoAbierto, { open: abrirNuevo, close: cerrarNuevo }] = useDisclosure(false)
  const [verId, setVerId] = useState<number | null>(null)
  const [aDescartar, setADescartar] = useState<LoteAccion | null>(null)
  const [resultado, setResultado] = useState<ResultadoDelLote | null>(null)

  const registrar = useRegistrarLote()
  const descartar = useDescartarLote()

  const pedirRegistro = (l: LoteAccion) => confirmar({
    title: 'Suscribir y registrar el lote',
    message: `Se suscriben y registran los ${enTramite(l)} actos que siguen en trámite, uno por uno y con las `
      + `validaciones de siempre. Cada uno sube la remuneración del obrero desde el ${formatFecha(l.fecha_efectiva)}.`,
    confirmLabel: 'Suscribir y registrar',
    onConfirm: () => registrar.mutate(l.id, {
      onSuccess: (r) => { if (r.datos.fallidos.length) setResultado(r.datos) },
    }),
  })

  const accionesDe = (l: LoteAccion): TableAction[] => [
    { label: 'Ver sus actos', icon: <IconList size={14} />, onClick: () => setVerId(l.id) },
    ...(puedeRegistrar && enTramite(l) > 0
      ? [{ label: 'Suscribir y registrar', icon: <IconChecks size={14} />, onClick: () => pedirRegistro(l) }]
      : []),
    ...(puedeAnular && enTramite(l) > 0
      ? [{ label: 'Descartar lo que sigue en trámite', icon: <IconBan size={14} />, color: 'red', onClick: () => setADescartar(l) }]
      : []),
  ]

  const columnas: DataTableColumn<LoteAccion>[] = [
    {
      accessor: 'descripcion',
      title: 'Incremento',
      render: (l) => (
        <div>
          <Text size="sm" fw={500}>{calculo(l)} · desde el {formatFecha(l.fecha_efectiva)}</Text>
          <Text size="xs" c="dimmed" lineClamp={1}>{l.descripcion}</Text>
        </div>
      ),
    },
    {
      accessor: 'documento_origen',
      title: 'Respaldo',
      width: 200,
      render: (l) => (
        <div>
          <Text size="sm">{l.documento_origen}</Text>
          <Text size="xs" c="dimmed">Dictamen {l.dictamen_presupuestario_ref}</Text>
        </div>
      ),
    },
    {
      accessor: 'por_estado',
      title: 'Actos',
      width: 230,
      render: (l) => (
        <Group gap={4}>
          {(Object.entries(l.por_estado) as [keyof typeof ESTADO_LABELS, number][]).map(([estado, n]) => (
            <StatusBadge
              key={estado}
              tone={estado === 'registrada' || estado === 'notificada' ? 'success' : estado === 'anulada' ? 'neutral' : 'warning'}
            >
              {n} {ESTADO_LABELS[estado].toLowerCase()}
            </StatusBadge>
          ))}
        </Group>
      ),
    },
    {
      accessor: 'acciones',
      title: '',
      width: 50,
      render: (l) => <TableActions actions={accionesDe(l)} />,
    },
  ]

  return (
    <Stack gap="md">
      <Alert variant="light" color="ocean" icon={<IconInfoCircle size={16} />}>
        El incremento de la tabla sectorial o del contrato colectivo, para todos los obreros
        o los de una unidad: un borrador por obrero, cada uno con su hoja de registro interno.
        Desde aquí se suscriben y registran todos; el que falla queda en su estado, y se
        corrige desde la bandeja.
      </Alert>

      {puedePreparar && (
        <Group justify="flex-end">
          <Button leftSection={<IconPlus size={16} />} onClick={abrirNuevo}>Nuevo incremento en lote</Button>
        </Group>
      )}

      <DataState
        loading={isLoading}
        error={error}
        errorTitle="No se pudieron cargar los lotes"
        onRetry={refetch}
        empty={!lotes.length}
        emptyProps={{
          icon: IconStack2,
          title: 'Sin incrementos en lote',
          description: 'Aquí aparecen los incrementos de los obreros preparados en lote.',
        }}
      >
        <SgthTable records={lotes} columns={columnas} minHeight={200} />
      </DataState>

      <IncrementoEnLoteModal opened={nuevoAbierto} onClose={cerrarNuevo} />
      <ActosDelLoteModal loteId={verId} onClose={() => setVerId(null)} />

      <MotivoModal
        opened={!!aDescartar}
        onClose={() => setADescartar(null)}
        title="Descartar el lote"
        descripcion={aDescartar
          ? `Se anulan los ${enTramite(aDescartar)} actos que siguen en trámite. Lo ya registrado no: se anula uno por uno, desde su acto.`
          : ''}
        confirmLabel="Anular lo que sigue en trámite"
        destructiva
        cargando={descartar.isPending}
        onConfirm={(motivo) => aDescartar && descartar.mutate(
          { id: aDescartar.id, motivo },
          { onSuccess: () => setADescartar(null) },
        )}
      />

      <SgthModal opened={!!resultado} onClose={() => setResultado(null)} title="Lo que quedó pendiente" size="lg">
        {resultado && (
          <Stack gap="xs">
            <Text size="sm">
              Se registraron {resultado.registrados}. Estos quedaron en su estado; corríjalos desde la bandeja:
            </Text>
            {resultado.fallidos.map((f) => (
              <Text key={f.movimiento_id} size="sm"><b>{f.nombre}</b>: {f.mensaje}</Text>
            ))}
          </Stack>
        )}
      </SgthModal>
    </Stack>
  )
}

function ActosDelLoteModal({ loteId, onClose }: { loteId: number | null; onClose: () => void }) {
  const { data, isLoading, error, refetch } = useLoteAccion(loteId)

  const columnas: DataTableColumn<ActoDelLote>[] = [
    {
      accessor: 'nombre',
      title: 'Obrero',
      render: (a) => (
        <div>
          <Text size="sm">{a.nombre}</Text>
          <Text size="xs" c="dimmed">{a.cedula ?? '—'}</Text>
        </div>
      ),
    },
    { accessor: 'remuneracion_actual', title: 'Actual', width: 110, render: (a) => dinero(a.remuneracion_actual) },
    { accessor: 'remuneracion_nueva', title: 'Nueva', width: 110, render: (a) => dinero(a.remuneracion_nueva) },
    {
      accessor: 'estado',
      title: 'Estado',
      width: 170,
      render: (a) => (
        <div>
          <Text size="sm">{ESTADO_LABELS[a.estado]}</Text>
          {a.codigo_registro && <Text size="xs" c="dimmed">{a.codigo_registro}</Text>}
        </div>
      ),
    },
  ]

  return (
    <SgthModal opened={loteId != null} onClose={onClose} title="Actos del lote" size="xl">
      <DataState loading={isLoading} error={error} errorTitle="No se pudo cargar el lote" onRetry={refetch}>
        <SgthTable records={data?.actos ?? []} columns={columnas} idAccessor="movimiento_id" minHeight={120} maxHeight={480} />
      </DataState>
    </SgthModal>
  )
}
