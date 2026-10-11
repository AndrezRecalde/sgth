'use client'

import { useState } from 'react'
import { Alert, Group, Stack, Switch, Text } from '@mantine/core'
import { IconDownload, IconFileCheck, IconInfoCircle, IconUserMinus } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import { DataState, SgthTable, StatusBadge, TableActions, notificar, type TableAction } from '@/components/ui'
import { guardarArchivo } from '@/lib/archivo'
import { formatFecha } from '@/lib/fecha'
import { getApiErrorMessage } from '@/types/api'
import { useTerminacionesObreros } from '../hooks/useTerminacionesObreros'
import { usePuedePrepararAcciones } from '../hooks/usePuedePrepararAccion'
import {
  nombreDelObrero,
  terminacionObreroService,
  type TerminacionObrero,
} from '../services/terminacionObreroService'
import { dinero } from '../utils/dinero'
import { ActaFiniquitoModal } from './ActaFiniquitoModal'

/**
 * Las terminaciones de obreros ya registradas y su acta de finiquito (diseño,
 * 9.15; fase 4.5). El acta se genera y se legaliza en el sistema del
 * Ministerio del Trabajo; aquí se registra y se ve cuáles faltan.
 *
 * La que el juez dejó sin efecto con un reintegro ya registrado no espera su
 * acta (fase 4.5b).
 */
/** ¿El juez la dejó sin efecto con un reintegro ya registrado? */
function reintegrado(t: TerminacionObrero): boolean {
  return t.reintegro?.estado === 'registrada' || t.reintegro?.estado === 'notificada'
}

export function TerminacionesObrerosPanel() {
  const [soloSinActa, setSoloSinActa] = useState(true)
  const { data: terminaciones = [], isLoading, error, refetch } = useTerminacionesObreros(soloSinActa)
  const puedePreparar = usePuedePrepararAcciones()
  const [aRegistrar, setARegistrar] = useState<TerminacionObrero | null>(null)

  const descargar = async (t: TerminacionObrero) => {
    try {
      guardarArchivo(
        await terminacionObreroService.descargarActa(t.movimiento_id),
        t.acta?.documento_nombre || `acta-finiquito-${t.codigo_registro}.pdf`,
      )
    } catch (e) {
      notificar.error('No se pudo descargar el acta', getApiErrorMessage(e))
    }
  }

  const accionesDe = (t: TerminacionObrero): TableAction[] => [
    ...(puedePreparar(null)
      ? [{
          label: t.acta ? 'Corregir acta' : 'Registrar acta',
          icon: <IconFileCheck size={14} />,
          onClick: () => setARegistrar(t),
        }]
      : []),
    ...(t.acta?.tiene_documento
      ? [{ label: 'Descargar el PDF del acta', icon: <IconDownload size={14} />, onClick: () => descargar(t) }]
      : []),
  ]

  const columnas: DataTableColumn<TerminacionObrero>[] = [
    {
      accessor: 'servidor',
      title: 'Obrero',
      render: (t) => (
        <div>
          <Text size="sm" fw={500}>{nombreDelObrero(t.servidor)}</Text>
          <Text size="xs" c="dimmed">{t.servidor?.cedula ?? '—'}</Text>
        </div>
      ),
    },
    {
      accessor: 'causal_etiqueta',
      title: 'Causal',
      render: (t) => (
        <div>
          <Text size="sm">{t.causal_etiqueta ?? '—'}</Text>
          <Text size="xs" c="dimmed">{t.codigo_registro} · desde el {formatFecha(t.fecha_efectiva)}</Text>
        </div>
      ),
    },
    {
      accessor: 'total_referencial',
      title: 'Liquidación referencial',
      width: 170,
      render: (t) => (t.total_referencial != null ? dinero(t.total_referencial) : '—'),
    },
    {
      accessor: 'acta',
      title: 'Acta de finiquito',
      width: 220,
      render: (t) => (
        <div>
          {t.acta
            ? (
              <>
                <StatusBadge tone="success">N.º {t.acta.numero}</StatusBadge>
                <Text size="xs" c="dimmed" mt={2}>
                  {formatFecha(t.acta.fecha)} · {dinero(t.acta.valor_pagado)}
                </Text>
              </>
            )
            : reintegrado(t)
              ? <StatusBadge tone="info">Reintegrado</StatusBadge>
              : <StatusBadge tone="warning">Pendiente</StatusBadge>}
          {t.reintegro && (
            <Text size="xs" c="dimmed" mt={2}>
              {reintegrado(t)
                ? `Sin efecto por el reintegro ${t.reintegro.codigo_registro}`
                : 'Reintegro por despido ineficaz en trámite'}
            </Text>
          )}
        </div>
      ),
    },
    {
      accessor: 'acciones',
      title: '',
      width: 50,
      render: (t) => <TableActions actions={accionesDe(t)} />,
    },
  ]

  return (
    <Stack gap="md">
      <Alert variant="light" color="ocean" icon={<IconInfoCircle size={16} />}>
        La terminación del contrato de un obrero se registra desde «Nueva acción de
        personal», con su causal del Código del Trabajo; su hoja de registro
        interno trae la liquidación referencial, que va a Financiero. El acta de
        finiquito se genera y se legaliza en el sistema del Ministerio del Trabajo:
        regístrela aquí cuando se firme.
      </Alert>

      <Group justify="flex-end">
        <Switch
          label="Solo las que no tienen acta"
          checked={soloSinActa}
          onChange={(e) => setSoloSinActa(e.currentTarget.checked)}
        />
      </Group>

      <DataState
        loading={isLoading}
        error={error}
        errorTitle="No se pudieron cargar las terminaciones"
        onRetry={refetch}
        empty={!terminaciones.length}
        emptyProps={{
          icon: IconUserMinus,
          title: soloSinActa ? 'No hay actas de finiquito pendientes' : 'Sin terminaciones de obreros',
          description: 'Aquí aparecen las terminaciones de obreros ya registradas, con su acta de finiquito.',
        }}
      >
        <SgthTable records={terminaciones} columns={columnas} idAccessor="movimiento_id" minHeight={200} />
      </DataState>

      <ActaFiniquitoModal terminacion={aRegistrar} onClose={() => setARegistrar(null)} />
    </Stack>
  )
}
