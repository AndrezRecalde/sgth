'use client'

import { useState } from 'react'
import { Alert, Button, Group, Text } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconFolder, IconUserPlus, IconStethoscope, IconFileSpreadsheet, IconFileTypePdf, IconHistoryToggle, IconAlertTriangle, IconFilterOff } from '@tabler/icons-react'
import { ServidorToolbar } from '@/features/expediente/components/ServidorToolbar'
import { ServidorTable } from '@/features/expediente/components/ServidorTable'
import { ServidorModal } from '@/features/expediente/components/ServidorModal'
import { ServidorDetail } from '@/features/expediente/components/ServidorDetail'
import { AccionPersonalDrawer } from '@/features/expediente/components/AccionPersonalDrawer'
import { SolicitarCertificacionLoteModal } from '@/features/expediente/components/SolicitarCertificacionLoteModal'
import { VinculacionInicialModal } from '@/features/expediente/components/VinculacionInicialModal'
import { usePuedeVincularInicial } from '@/features/expediente/hooks/useVinculacionInicial'
import { usePendientesVinculacion } from '@/features/expediente/hooks/usePendientesVinculacion'
import { MovimientoModal } from '@/features/expediente/components/MovimientoModal'
import { useServidores } from '@/features/expediente/hooks/useServidores'
import { useFiltrosServidores } from '@/features/expediente/hooks/useFiltrosServidores'
import { expedienteService } from '@/features/expediente/services/expedienteService'
import { getApiErrorMessage } from '@/types/api'
import { guardarArchivo } from '@/lib/archivo'
import { useAuth } from '@/hooks/useAuth'
import type { ServidorConRelaciones } from '@/types/api'
import { DataState, PageHeader, PageShell, notificar } from '@/components/ui'

export function ExpedienteView() {
  const { hasPermiso } = useAuth()
  const puedeVincularInicial = usePuedeVincularInicial()
  const [vinculacionOpened, { open: abrirVinculacion, close: cerrarVinculacion }] = useDisclosure(false)
  const {
    page, setPage, filtros, hayFiltros, pendienteVinculacion,
    setSearch, setContratoEstado, setEnFunciones, setUnidadId,
    setTipoNombramiento, setAnioIngreso, setPendienteVinculacion,
  } = useFiltrosServidores()
  const [exportando, setExportando]         = useState<'excel' | 'pdf' | null>(null)
  const [selectedRecords, setSelectedRecords] =
    useState<ServidorConRelaciones[]>([])

  const [modalOpened,        { open: openModal,        close: closeModal        }] = useDisclosure(false)
  const [detailOpened,       { open: openDetail,       close: closeDetail       }] = useDisclosure(false)
  const [accionPersonalOpened, { open: openAccionPersonal, close: closeAccionPersonal }] = useDisclosure(false)
  const [loteOpened,         { open: openLote,         close: closeLote         }] = useDisclosure(false)

  const [editServidor, setEditServidor] =
    useState<ServidorConRelaciones | null>(null)
  const [viewServidor, setViewServidor] =
    useState<ServidorConRelaciones | null>(null)
  const [accionPersonalServidor, setAccionPersonalServidor] =
    useState<ServidorConRelaciones | null>(null)

  // Ficha recién creada, para encadenar su Ingreso y Vinculación.
  const [servidorReciente, setServidorReciente] =
    useState<ServidorConRelaciones | null>(null)
  const [ingresoOpened, { open: abrirIngreso, close: cerrarIngreso }] = useDisclosure(false)

  const { data: pendientes } = usePendientesVinculacion()

  const { data, isLoading, error } = useServidores({
    page,
    per_page: 15,
    ...filtros,
  })

  const servidores = data?.data ?? []

  const handleExportar = async (tipo: 'excel' | 'pdf') => {
    setExportando(tipo)
    try {
      const blob = tipo === 'excel'
        ? await expedienteService.exportarExcel(filtros)
        : await expedienteService.exportarPdf(filtros)
      guardarArchivo(blob, `nomina_servidores.${tipo === 'excel' ? 'xlsx' : 'pdf'}`)
    } catch (error) {
      notificar.error(
        `No se pudo exportar el listado a ${tipo === 'excel' ? 'Excel' : 'PDF'}`,
        getApiErrorMessage(error, 'Inténtalo de nuevo en unos segundos.'),
      )
    } finally {
      setExportando(null)
    }
  }

  const handleView = (s: ServidorConRelaciones) => {
    setViewServidor(s)
    openDetail()
  }

  const handleEdit = (s: ServidorConRelaciones) => {
    setEditServidor(s)
    openModal()
  }

  const handleAccionPersonal = (s: ServidorConRelaciones) => {
    setAccionPersonalServidor(s)
    openAccionPersonal()
  }

  const handleNuevo = () => {
    setEditServidor(null)
    openModal()
  }

  return (
    <PageShell>
      <PageHeader
        title="Expediente Digital"
        description="Gestión de servidores públicos del GAD Provincial de Esmeraldas"
      />

      <Group justify="flex-end" mb="md">
        {hasPermiso('solicitar-certificacion-medica') &&
          selectedRecords.length > 0 && (
          <Button
            variant="light"
            leftSection={<IconStethoscope size={16} />}
            onClick={openLote}
          >
            Solicitar certificación médica ({selectedRecords.length})
          </Button>
        )}
        <Button
          variant="light"
          leftSection={<IconFileSpreadsheet size={16} />}
          loading={exportando === 'excel'}
          onClick={() => handleExportar('excel')}
        >
          Exportar Excel
        </Button>
        <Button
          variant="light"
          leftSection={<IconFileTypePdf size={16} />}
          loading={exportando === 'pdf'}
          onClick={() => handleExportar('pdf')}
        >
          Exportar PDF
        </Button>
        {/* Carga inicial: solo aparece mientras dure la migración, para quien
            tenga el permiso. Al revocarlo el botón desaparece solo. */}
        {puedeVincularInicial && (
          <Button
            variant="light"
            leftSection={<IconHistoryToggle size={16} />}
            onClick={abrirVinculacion}
          >
            Vinculación inicial
          </Button>
        )}
        {/* "Registrar ficha" y no "Nuevo servidor": esto crea a la persona,
            no la contrata. El vínculo se registra en el paso siguiente. */}
        <Button
          variant="light"
          leftSection={<IconUserPlus size={16} />}
          onClick={handleNuevo}
        >
          Registrar ficha
        </Button>
      </Group>

      {/* Nadie debería quedar a medio registrar sin que se note. */}
      {(pendientes ?? 0) > 0 && pendienteVinculacion !== true && (
        <Alert
          variant="light"
          color="amber"
          icon={<IconAlertTriangle size={16} />}
          mb="md"
          title={`${pendientes} ficha(s) sin vínculo laboral registrado`}
        >
          <Group justify="space-between" wrap="nowrap">
            <Text size="sm">
              Existen en el sistema pero no están contratadas: no aparecen en
              nómina ni en asistencia hasta que se registre su Ingreso y Vinculación.
            </Text>
            {/* Sin flexShrink el grupo lo encogía y el texto se cortaba («Ver quiéne»). */}
            <Button
              size="xs"
              variant="light"
              style={{ flexShrink: 0 }}
              onClick={() => setPendienteVinculacion(true)}
            >
              Ver quiénes
            </Button>
          </Group>
        </Alert>
      )}

      <ServidorToolbar
        onSearch={setSearch}
        onContratoEstadoChange={setContratoEstado}
        onEnFuncionesChange={setEnFunciones}
        onUnidadChange={setUnidadId}
        onTipoNombramientoChange={setTipoNombramiento}
        onAnioIngresoChange={setAnioIngreso}
        onPendienteVinculacionChange={setPendienteVinculacion}
        pendienteVinculacion={pendienteVinculacion}
      />

      {/* Con filtros, un listado vacío no significa que falten servidores:
          antes una búsqueda sin resultados invitaba a registrar «el primero»,
          y un fallo de red se veía igual. */}
      <DataState
        loading={isLoading}
        error={error}
        empty={servidores.length === 0}
        emptyProps={hayFiltros ? {
          icon: IconFilterOff,
          title: 'Ningún servidor coincide con los filtros',
          description: 'Cambie la búsqueda o quite alguno de los filtros.',
        } : {
          icon: IconFolder,
          title: 'No hay servidores registrados',
          description: 'Comience registrando la ficha del primer servidor. El vínculo laboral se registra después, con su Acción de Personal de Ingreso.',
          action: (
            <Button variant="light"
              leftSection={<IconUserPlus size={14} />}
              onClick={handleNuevo}>
              Registrar ficha
            </Button>
          ),
        }}
      >
        <ServidorTable
          data={servidores}
          isLoading={isLoading}
          total={data?.total ?? 0}
          page={page}
          onPageChange={setPage}
          onView={handleView}
          onEdit={handleEdit}
          onAccionPersonal={handleAccionPersonal}
          selectedRecords={selectedRecords}
          onSelectedRecordsChange={setSelectedRecords}
        />
      </DataState>

      <ServidorModal
        key={editServidor?.id ?? 'nuevo'}
        opened={modalOpened}
        onClose={() => { setEditServidor(null); closeModal() }}
        servidor={editServidor}
        onCreado={(creado) => { setServidorReciente(creado); abrirIngreso() }}
      />

      {/* Segundo paso del alta ordinaria: el vínculo con su Acción de
          Personal. Se abre encadenado para no dejar la ficha a medias. */}
      {servidorReciente && (
        <MovimientoModal
          opened={ingresoOpened}
          onClose={() => { setServidorReciente(null); cerrarIngreso() }}
          servidorId={Number(servidorReciente.id)}
          tipoFijo="ingreso"
          titulo={`Ingreso y Vinculación — ${[servidorReciente.apellido, servidorReciente.nombre].filter(Boolean).join(' ')}`}
        />
      )}
      <ServidorDetail
        opened={detailOpened}
        onClose={closeDetail}
        servidor={viewServidor}
        onEdit={handleEdit}
      />
      <AccionPersonalDrawer
        opened={accionPersonalOpened}
        onClose={() => { setAccionPersonalServidor(null); closeAccionPersonal() }}
        servidor={accionPersonalServidor}
      />
      <SolicitarCertificacionLoteModal
        opened={loteOpened}
        onClose={() => { setSelectedRecords([]); closeLote() }}
        servidores={selectedRecords}
      />
      <VinculacionInicialModal
        opened={vinculacionOpened}
        onClose={cerrarVinculacion}
      />
    </PageShell>
  )
}
