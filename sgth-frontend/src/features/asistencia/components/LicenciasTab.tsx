'use client'

import { useState } from 'react'
import { Button, Select, Stack, Text } from '@mantine/core'
import { IconCheck, IconDownload, IconPlus, IconReportMedical, IconX } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import {
  DataState, MotivoModal, PAGINACION_ES, SgthTable, StatusBadge, TableActions, Toolbar, notificar, type TableAction,
} from '@/components/ui'
import { useAuth } from '@/hooks/useAuth'
import { useContainedInput } from '@/hooks/useContainedInput'
import { guardarArchivo } from '@/lib/archivo'
import { formatFecha } from '@/lib/fecha'
import { getApiErrorMessage } from '@/types/api'
import { useAnularLicencia, useCausasLicencia, useLicencias } from '../hooks/useLicencias'
import { licenciaService, type CausaLicencia, type EstadoLicencia, type Licencia } from '../services/licenciaService'
import { AprobarLicenciaModal } from './AprobarLicenciaModal'
import { RegistrarLicenciaModal } from './RegistrarLicenciaModal'

const POR_PAGINA = 15

const ESTADOS: { value: EstadoLicencia; label: string }[] = [
  { value: 'pendiente', label: 'Pendientes' },
  { value: 'aprobada', label: 'Aprobadas' },
  { value: 'anulada', label: 'Anuladas' },
]

const TONO = { pendiente: 'warning', aprobada: 'success', anulada: 'neutral' } as const

/*
| Las licencias con remuneración, fuera de las vacaciones (fase 5.1): con su
| causa, su tope y su respaldo, sin acción de personal. Las registra Talento
| Humano; las aprueba quien aprueba esa causa, y entonces van a Sirha7.
*/
export function LicenciasTab() {
  const contained = useContainedInput('sm')
  const { hasPermiso } = useAuth()
  const puedeRegistrar = hasPermiso('gestionar-licencias')

  const [page, setPage] = useState(1)
  const [estado, setEstado] = useState<EstadoLicencia | null>('pendiente')
  const [causa, setCausa] = useState<CausaLicencia | null>(null)
  const [registrando, setRegistrando] = useState(false)
  const [aprobando, setAprobando] = useState<Licencia | null>(null)
  const [anulando, setAnulando] = useState<Licencia | null>(null)

  const causas = useCausasLicencia().data ?? []
  const anular = useAnularLicencia()

  const { data, isLoading, error, refetch } = useLicencias({
    page,
    per_page: POR_PAGINA,
    ...(estado ? { estado } : {}),
    ...(causa ? { causa } : {}),
  })
  const lista = data?.data ?? []

  const descargar = async (l: Licencia) => {
    try {
      guardarArchivo(await licenciaService.documento(l.id), l.documento_nombre || `licencia-${l.folio}.pdf`)
    } catch (e) {
      notificar.error('No se pudo descargar el respaldo', getApiErrorMessage(e))
    }
  }

  const accionesDe = (l: Licencia): TableAction[] => [
    ...(l.puede_aprobar ? [{ label: 'Aprobar', icon: <IconCheck size={14} />, onClick: () => setAprobando(l) }] : []),
    { label: 'Descargar el respaldo', icon: <IconDownload size={14} />, onClick: () => descargar(l) },
    ...(l.puede_anular ? [{ label: 'Anular', icon: <IconX size={14} />, color: 'red', onClick: () => setAnulando(l) }] : []),
  ]

  const columnas: DataTableColumn<Licencia>[] = [
    {
      accessor: 'servidor',
      title: 'Servidor',
      render: (l) => (
        <div>
          <Text size="sm" fw={500}>{[l.servidor?.apellido, l.servidor?.nombre].filter(Boolean).join(' ') || '—'}</Text>
          <Text size="xs" c="dimmed">{l.servidor?.unidad ?? '—'}</Text>
        </div>
      ),
    },
    {
      accessor: 'causa',
      title: 'Causa',
      render: (l) => (
        <div>
          <Text size="sm">{l.causa_etiqueta}</Text>
          <Text size="xs" c="dimmed">
            {l.folio}
            {l.alcance_etiqueta ? ` · ${l.alcance_etiqueta}` : ''}
            {l.clausula ? ` · contrato colectivo, ${l.clausula.clausula}` : ''}
          </Text>
        </div>
      ),
    },
    {
      accessor: 'fecha_inicio',
      title: 'Días',
      width: 200,
      render: (l) => (
        <div>
          <Text size="sm">{formatFecha(l.fecha_inicio)} al {formatFecha(l.fecha_fin)}</Text>
          <Text size="xs" c="dimmed">{l.dias} día(s){l.tope != null ? ` de ${l.tope}` : ''}</Text>
        </div>
      ),
    },
    {
      accessor: 'estado',
      title: 'Estado',
      width: 190,
      render: (l) => (
        <div>
          <StatusBadge tone={TONO[l.estado]}>{ESTADOS.find((e) => e.value === l.estado)?.label.slice(0, -1)}</StatusBadge>
          {l.estado === 'aprobada' && (
            <Text size="xs" c="dimmed" mt={2}>
              {l.registro_sirha7 === 'sgth' ? `En Sirha7: ${l.sirha7_leave_nombre}` : 'Cargada a mano en Sirha7'}
            </Text>
          )}
        </div>
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
      <Toolbar
        actions={puedeRegistrar && (
          <Button leftSection={<IconPlus size={16} />} onClick={() => setRegistrando(true)}>
            Registrar licencia
          </Button>
        )}
      >
        <Select
          label="Estado"
          placeholder="Todas"
          data={ESTADOS}
          value={estado}
          onChange={(v) => { setEstado(v as EstadoLicencia | null); setPage(1) }}
          clearable
          {...contained}
          style={{ minWidth: 200 }}
        />
        <Select
          label="Causa"
          placeholder="Todas"
          data={causas.map((c) => ({ value: c.codigo, label: c.etiqueta }))}
          value={causa}
          onChange={(v) => { setCausa(v as CausaLicencia | null); setPage(1) }}
          clearable
          searchable
          {...contained}
          style={{ minWidth: 260 }}
        />
      </Toolbar>

      <DataState
        loading={isLoading}
        error={error}
        errorTitle="No se pudieron cargar las licencias"
        onRetry={refetch}
        empty={!lista.length}
        page={page}
        emptyProps={{
          icon: IconReportMedical,
          title: 'Sin licencias',
          description: estado ? 'No hay licencias en ese estado.' : 'No hay licencias registradas.',
        }}
      >
        <SgthTable
          {...PAGINACION_ES}
          records={lista}
          columns={columnas}
          totalRecords={data?.total ?? lista.length}
          recordsPerPage={POR_PAGINA}
          page={page}
          onPageChange={setPage}
          minHeight={200}
        />
      </DataState>

      <RegistrarLicenciaModal opened={registrando} onClose={() => setRegistrando(false)} />
      <AprobarLicenciaModal licencia={aprobando} onClose={() => setAprobando(null)} />
      <MotivoModal
        opened={!!anulando}
        onClose={() => setAnulando(null)}
        title="Anular la licencia"
        descripcion={anulando
          ? `Se anula ${anulando.folio} y sus días quedan libres.${anulando.registro_sirha7 === 'sgth' ? ' Lo registrado en Sirha7 se retira.' : ''}`
          : ''}
        confirmLabel="Anular la licencia"
        destructiva
        cargando={anular.isPending}
        onConfirm={(motivo) => anulando && anular.mutate(
          { id: anulando.id, motivo },
          { onSuccess: () => setAnulando(null) },
        )}
      />
    </Stack>
  )
}
