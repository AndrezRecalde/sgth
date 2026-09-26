'use client'

import { useState } from 'react'
import { Alert, Button, Group, Text } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconFolder, IconUserPlus, IconAlertTriangle, IconFilterOff } from '@tabler/icons-react'
import { ServidorToolbar } from '@/features/expediente/components/ServidorToolbar'
import { ExpedienteAcciones } from '@/features/expediente/components/ExpedienteAcciones'
import { ServidorTable } from '@/features/expediente/components/ServidorTable'
import { ServidorModal } from '@/features/expediente/components/ServidorModal'
import { SolicitarCertificacionLoteModal } from '@/features/expediente/components/SolicitarCertificacionLoteModal'
import { VinculacionInicialModal } from '@/features/expediente/components/VinculacionInicialModal'
import { usePuedeVincularInicial } from '@/features/expediente/hooks/useVinculacionInicial'
import { usePendientesVinculacion } from '@/features/expediente/hooks/usePendientesVinculacion'
import { MovimientoModal } from '@/features/expediente/components/MovimientoModal'
import { useServidores } from '@/features/expediente/hooks/useServidores'
import { useFiltrosServidores } from '@/features/expediente/hooks/useFiltrosServidores'
import { servidorService } from '@/features/expediente/services/servidorService'
import { getApiErrorMessage } from '@/types/api'
import { guardarArchivo } from '@/lib/archivo'
import { useAuth } from '@/hooks/useAuth'
import { useRouter } from 'next/navigation'
import { ROUTES } from '@/config/routes'
import type { ServidorConRelaciones } from '@/types/api'
import { DataState, PageHeader, PageShell, notificar } from '@/components/ui'

export function ExpedienteView() {
  const { hasPermiso } = useAuth()
  const router = useRouter()
  const puedeVincularInicial = usePuedeVincularInicial()
  const [vinculacionOpened, { open: abrirVinculacion, close: cerrarVinculacion }] = useDisclosure(false)
  const {
    page, setPage, filtros, hayFiltros, filtrosSecundarios, situacion,
    setSearch, setSituacion, setContratoEstado, setUnidadId,
    setTipoNombramiento, setAnioIngreso,
  } = useFiltrosServidores()
  const [exportando, setExportando]         = useState<'excel' | 'pdf' | null>(null)
  const [selectedRecords, setSelectedRecords] =
    useState<ServidorConRelaciones[]>([])

  const [modalOpened,        { open: openModal,        close: closeModal        }] = useDisclosure(false)
  const [loteOpened,         { open: openLote,         close: closeLote         }] = useDisclosure(false)


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
        ? await servidorService.exportarExcel(filtros)
        : await servidorService.exportarPdf(filtros)
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

  // Todo lo del servidor —ficha, vínculo y acciones— vive en su página.
  const irAlExpediente = (s: ServidorConRelaciones) =>
    router.push(ROUTES.SGTH.EXPEDIENTE_SERVIDOR(s.id))

  const handleNuevo = openModal

  return (
    <PageShell>
      <PageHeader
        title="Expediente Digital"
        description="Gestión de servidores públicos del GAD Provincial de Esmeraldas"
        actions={
          <ExpedienteAcciones
            seleccionados={selectedRecords.length}
            puedeSolicitarCertificacion={hasPermiso('solicitar-certificacion-medica')}
            puedeVincularInicial={puedeVincularInicial}
            exportando={exportando}
            onSolicitarCertificacion={openLote}
            onExportar={handleExportar}
            onVinculacionInicial={abrirVinculacion}
            onRegistrarFicha={handleNuevo}
          />
        }
      />

      {/* Nadie debería quedar a medio registrar sin que se note. */}
      {(pendientes ?? 0) > 0 && situacion !== 'sin_vinculo' && (
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
              onClick={() => setSituacion('sin_vinculo')}
            >
              Ver quiénes
            </Button>
          </Group>
        </Alert>
      )}

      <ServidorToolbar
        onSearch={setSearch}
        situacion={situacion}
        onSituacionChange={setSituacion}
        onContratoEstadoChange={setContratoEstado}
        onUnidadChange={setUnidadId}
        onTipoNombramientoChange={setTipoNombramiento}
        onAnioIngresoChange={setAnioIngreso}
        secundariosActivos={filtrosSecundarios}
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
          onView={irAlExpediente}
          seleccionable={hasPermiso('solicitar-certificacion-medica')}
          selectedRecords={selectedRecords}
          onSelectedRecordsChange={setSelectedRecords}
        />
      </DataState>

      <ServidorModal
        opened={modalOpened}
        onClose={closeModal}
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
