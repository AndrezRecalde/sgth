'use client'

import { useState } from 'react'
import { Button } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconBeach, IconCubePlus, IconUserOff } from '@tabler/icons-react'
import {
  DataState, EmptyState, PAGINACION_ES, PageHeader, PageShell, SectionCard, SgthTable,
} from '@/components/ui'
import { useAuth } from '@/hooks/useAuth'
import { generaVacaciones } from '@/lib/regimen'
import { getMisVacacionesColumns } from './misVacaciones.columns'
import { MiSaldoVacaciones } from './MiSaldoVacaciones'
import { VacacionModal } from './VacacionModal'
import { useMisVacaciones } from '../hooks/useMisVacaciones'
import { useExportarVacacion } from '../hooks/useExportarVacacion'

/** Las solicitudes llegan todas juntas; se muestran de diez en diez. */
const POR_PAGINA = 10

const TITULO      = 'Mis vacaciones'
const DESCRIPCION = 'Su saldo, sus períodos y sus solicitudes de vacaciones.'

/**
 * «Mis vacaciones» del Portal del Servidor.
 *
 * El menú llevaba aquí y la página no existía: el enlace terminaba en un 404,
 * aunque el backend ya dejaba a cada servidor pedir, ver e imprimir las suyas
 * (`VacacionPolicy`) y consultar su saldo. «Solicitar vacaciones» abre el mismo
 * modal del SGTH, limitado a la solicitud propia, como en «Mis permisos».
 */
export function MisVacacionesView() {
  const { usuario } = useAuth()
  const servidor = usuario?.servidor ?? null
  const [page, setPage] = useState(1)
  const [modalAbierto, { open: abrirModal, close: cerrarModal }] = useDisclosure(false)
  const { data, isLoading, error, refetch } = useMisVacaciones()
  const { exportar, exportandoId } = useExportarVacacion()

  if (!servidor) {
    return (
      <PageShell>
        <PageHeader title={TITULO} description={DESCRIPCION} />
        <EmptyState
          icon={IconUserOff}
          title="Su usuario no está vinculado a un servidor"
          description="Las vacaciones se llevan sobre el expediente del servidor. Pídale a Talento Humano que complete la vinculación."
        />
      </PageShell>
    )
  }

  // Lo mismo que rechaza el backend (`RegimenLaboral::generaVacaciones`): un
  // contrato civil no tiene un período del que pedir.
  if (!generaVacaciones(servidor.regimen_laboral)) {
    return (
      <PageShell>
        <PageHeader title={TITULO} description={DESCRIPCION} />
        <EmptyState
          icon={IconBeach}
          title="Su régimen no genera vacaciones"
          description="Un contrato de servicios profesionales no tiene jornada ni relación de dependencia, así que no le corresponde un período de vacaciones."
        />
      </PageShell>
    )
  }

  const solicitudes = data?.historial_solicitudes ?? []
  const diasPorAprobar = solicitudes
    .filter(s => s.estado === 'pendiente')
    .reduce((total, s) => total + Number(s.dias_solicitados), 0)
  const nombrePropio = usuario?.nombre_completo
    || [servidor.nombre, servidor.apellido].filter(Boolean).join(' ')

  const columns = getMisVacacionesColumns({
    exportandoId,
    onExportar: (v) => exportar(v.id, v.folio),
  })

  const botonSolicitar = (
    <Button variant="light" leftSection={<IconCubePlus size={16} />} onClick={abrirModal}>
      Solicitar vacaciones
    </Button>
  )

  return (
    <PageShell>
      <PageHeader title={TITULO} description={DESCRIPCION} actions={botonSolicitar} />

      <MiSaldoVacaciones servidorId={servidor.id} diasPorAprobar={diasPorAprobar} />

      <SectionCard
        title="Mis solicitudes"
        description="Talento Humano aprueba o rechaza cada una; la aprobada descuenta los días de su saldo."
      >
        <DataState
          loading={isLoading}
          error={error}
          empty={!solicitudes.length}
          errorTitle="No se pudieron cargar sus solicitudes"
          onRetry={() => void refetch()}
          skeletonRows={3}
          emptyProps={{
            icon: IconBeach,
            title: 'No tiene solicitudes de vacaciones',
            description: 'Pida las suyas con «Solicitar vacaciones». Las que registre Talento Humano a su nombre también aparecerán aquí.',
            action: botonSolicitar,
          }}
        >
          <SgthTable
            {...PAGINACION_ES}
            records={solicitudes.slice((page - 1) * POR_PAGINA, page * POR_PAGINA)}
            columns={columns}
            totalRecords={solicitudes.length}
            recordsPerPage={POR_PAGINA}
            page={page}
            onPageChange={setPage}
            minHeight={150}
          />
        </DataState>
      </SectionCard>

      <VacacionModal
        opened={modalAbierto}
        onClose={cerrarModal}
        soloPropio
        nombrePropio={nombrePropio}
      />
    </PageShell>
  )
}
