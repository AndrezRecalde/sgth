'use client'

import { useState } from 'react'
import { Alert, Button, Group, Stack, Text } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconArrowBackUp, IconBan, IconChecks, IconInfoCircle, IconList, IconPlus, IconStack2 } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import {
  DataState, MotivoModal, SgthModal, SgthTable, StatusBadge, TableActions, confirmar, type TableAction,
} from '@/components/ui'
import { useAuth } from '@/hooks/useAuth'
import { formatFecha } from '@/lib/fecha'
import {
  useDescartarLote, useLoteAccion, useLotesAccion, useRegistrarLote, useRevertirLote,
} from '../hooks/useContratoColectivo'
import type { ActoDelLote, LoteAccion, ResultadoDelLote } from '../services/contratoColectivoService'
import { ESTADO_LABELS } from '../utils/estadoAccionPersonal'
import { dinero } from '../utils/dinero'
import { IncrementoEnLoteModal } from './IncrementoEnLoteModal'

const enTramite = (l: LoteAccion) => (l.por_estado.borrador ?? 0) + (l.por_estado.suscrita ?? 0)
const registrados = (l: LoteAccion) => (l.por_estado.registrada ?? 0) + (l.por_estado.notificada ?? 0)
const esTraspaso = (l: LoteAccion) => l.tipo === 'traspaso_puesto'
const esRevision = (l: LoteAccion) => l.tipo === 'revision_clasificacion'
/** El traspaso y la revisión se registran y se anulan enteros (7.1a, 7.1b). */
const esEntero = (l: LoteAccion) => esTraspaso(l) || esRevision(l)

const calculo = (l: LoteAccion) => (l.parametros.modo === 'porcentaje'
  ? `${l.parametros.valor} %`
  : `${dinero(l.parametros.valor)} adicionales`)

/**
 * Los actos que nacen juntos (diseño, 8.5). El incremento de los obreros
 * (fase 4.6) prepara un borrador por obrero; se registran uno por uno, y el que
 * falla queda en su estado. El traspaso de un puesto (fase 7.1a), uno por
 * ocupante: se registran y se anulan todos a la vez, porque un puesto no puede
 * quedar con unos ocupantes en cada unidad. La revisión de la clasificación
 * (7.1b), igual: el grado o la denominación de uno o varios puestos, una por
 * ocupante. Los dos se preparan desde Estructura.
 */
export function ActosEnLotePanel() {
  const { data: lotes = [], isLoading, error, refetch } = useLotesAccion()
  const { hasPermiso } = useAuth()
  const puedePreparar = hasPermiso('preparar-accion-personal')
  const puedeRegistrar = hasPermiso('suscribir-accion-personal') && hasPermiso('registrar-accion-personal')
  const puedeAnular = hasPermiso('anular-accion-personal')

  const [nuevoAbierto, { open: abrirNuevo, close: cerrarNuevo }] = useDisclosure(false)
  const [verId, setVerId] = useState<number | null>(null)
  const [aDescartar, setADescartar] = useState<LoteAccion | null>(null)
  const [aRevertir, setARevertir] = useState<LoteAccion | null>(null)
  const [resultado, setResultado] = useState<ResultadoDelLote | null>(null)

  const registrar = useRegistrarLote()
  const descartar = useDescartarLote()
  const revertir = useRevertirLote()

  const pedirRegistro = (l: LoteAccion) => confirmar({
    title: 'Suscribir y registrar el lote',
    message: esTraspaso(l)
      ? `Se suscriben y registran los ${enTramite(l)} actos del traspaso, todos o ninguno. Desde el `
        + `${formatFecha(l.fecha_efectiva)} el puesto y sus ocupantes pasan a ${l.traspaso?.unidad_destino ?? 'la unidad nueva'}.`
      : esRevision(l)
        ? `Se suscriben y registran los ${enTramite(l)} actos de la revisión, todos o ninguno. Desde el `
          + `${formatFecha(l.fecha_efectiva)} cada puesto cambia de grado o de denominación, y sus ocupantes de remuneración.`
        : `Se suscriben y registran los ${enTramite(l)} actos que siguen en trámite, uno por uno y con las `
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
    // El traspaso y la revisión registrados no se descartan: se revierten, enteros.
    ...(puedeAnular && enTramite(l) > 0 && !(esEntero(l) && registrados(l) > 0)
      ? [{ label: 'Descartar lo que sigue en trámite', icon: <IconBan size={14} />, color: 'red', onClick: () => setADescartar(l) }]
      : []),
    ...(puedeAnular && esEntero(l) && registrados(l) > 0
      ? [{
          label: esTraspaso(l) ? 'Revertir el traspaso' : 'Revertir la revisión',
          icon: <IconArrowBackUp size={14} />,
          color: 'red',
          onClick: () => setARevertir(l),
        }]
      : []),
  ]

  const columnas: DataTableColumn<LoteAccion>[] = [
    {
      accessor: 'descripcion',
      title: 'Lote',
      render: (l) => (
        <div>
          <Text size="sm" fw={500}>
            {esTraspaso(l)
              ? `Traspaso de ${l.traspaso?.puesto ?? 'puesto'}: ${l.traspaso?.unidad_origen ?? '—'} → ${l.traspaso?.unidad_destino ?? '—'}`
              : esRevision(l)
                ? `${l.revision?.causal ?? 'Revisión'} de ${l.revision?.puestos.length ?? 0} puesto(s)`
                : `Incremento de ${calculo(l)}`} · desde el {formatFecha(l.fecha_efectiva)}
          </Text>
          {esRevision(l)
            ? (l.revision?.puestos ?? []).map((p) => (
              <Text key={p.puesto_id} size="xs" c="dimmed" lineClamp={1}>{p.antes} → {p.despues}</Text>
            ))
            : <Text size="xs" c="dimmed" lineClamp={1}>{l.descripcion}</Text>}
        </div>
      ),
    },
    {
      accessor: 'documento_origen',
      title: 'Respaldo',
      width: 220,
      render: (l) => (
        <div>
          <Text size="sm">{esTraspaso(l) ? `Informe UATH ${l.documento_origen}` : l.documento_origen}</Text>
          <Text size="xs" c="dimmed" lineClamp={1}>
            {esTraspaso(l)
              ? l.traspaso?.criterio
              : l.dictamen_presupuestario_ref ? `Dictamen ${l.dictamen_presupuestario_ref}` : 'Sin dictamen: no sube la remuneración'}
          </Text>
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
        Los actos que nacen juntos. El incremento de la tabla sectorial o del contrato colectivo da un
        borrador por obrero, cada uno con su hoja de registro interno; se registran uno por uno, y el que
        falla se corrige desde la bandeja. El traspaso de un puesto a otra unidad y la revisión de su
        clasificación se preparan en Estructura y dan una acción por ocupante, que se registran y se
        revierten todas a la vez.
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
          title: 'Sin actos en lote',
          description: 'Aquí aparecen los incrementos de los obreros, los traspasos de puestos y las revisiones de su clasificación.',
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
          ? (esEntero(aDescartar)
            ? `Se anulan los ${enTramite(aDescartar)} actos del lote. El puesto sigue como está.`
            : `Se anulan los ${enTramite(aDescartar)} actos que siguen en trámite. Lo ya registrado no: se anula uno por uno, desde su acto.`)
          : ''}
        confirmLabel="Anular lo que sigue en trámite"
        destructiva
        cargando={descartar.isPending}
        onConfirm={(motivo) => aDescartar && descartar.mutate(
          { id: aDescartar.id, motivo },
          { onSuccess: () => setADescartar(null) },
        )}
      />

      <MotivoModal
        opened={!!aRevertir}
        onClose={() => setARevertir(null)}
        title={aRevertir && esRevision(aRevertir) ? 'Revertir la revisión' : 'Revertir el traspaso'}
        descripcion={aRevertir
          ? (esRevision(aRevertir)
            ? 'Se anulan los actos de la revisión: cada puesto vuelve a su grado y su denominación, y sus ocupantes a su remuneración.'
            : `Se anulan los actos del traspaso, y el puesto y sus ocupantes vuelven a ${aRevertir.traspaso?.unidad_origen ?? 'su unidad de origen'}.`)
          : ''}
        confirmLabel="Revertir"
        destructiva
        cargando={revertir.isPending}
        onConfirm={(motivo) => aRevertir && revertir.mutate(
          { id: aRevertir.id, motivo },
          { onSuccess: () => setARevertir(null) },
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
  const traspaso = data?.tipo === 'traspaso_puesto'

  const columnas: DataTableColumn<ActoDelLote>[] = [
    {
      accessor: 'nombre',
      title: data?.tipo === 'incremento_obreros' ? 'Obrero' : 'Ocupante',
      render: (a) => (
        <div>
          <Text size="sm">{a.nombre}</Text>
          <Text size="xs" c="dimmed">{a.cedula ?? '—'}</Text>
        </div>
      ),
    },
    // En el traspaso la remuneración no cambia: el puesto se va con ella.
    ...(traspaso ? [] : [
      { accessor: 'remuneracion_actual', title: 'Actual', width: 110, render: (a: ActoDelLote) => dinero(a.remuneracion_actual) },
      { accessor: 'remuneracion_nueva', title: 'Nueva', width: 110, render: (a: ActoDelLote) => dinero(a.remuneracion_nueva) },
    ]),
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
