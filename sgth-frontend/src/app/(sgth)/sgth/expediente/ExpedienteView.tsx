'use client'

import { useState } from 'react'
import { Box, Button, Group } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { notifications } from '@mantine/notifications'
import { IconFolder, IconUserPlus, IconStethoscope, IconFileSpreadsheet, IconFileTypePdf } from '@tabler/icons-react'
import { PageHeader } from '@/components/ui/PageHeader'
import { EmptyState } from '@/components/ui/EmptyState'
import { ServidorToolbar } from '@/features/expediente/components/ServidorToolbar'
import { ServidorTable } from '@/features/expediente/components/ServidorTable'
import { ServidorModal } from '@/features/expediente/components/ServidorModal'
import { ServidorDetail } from '@/features/expediente/components/ServidorDetail'
import { SolicitarCertificacionLoteModal } from '@/features/expediente/components/SolicitarCertificacionLoteModal'
import { useServidores } from '@/features/expediente/hooks/useServidores'
import { expedienteService } from '@/features/expediente/services/expedienteService'
import { getApiErrorMessage } from '@/types/api'
import { useAuth } from '@/hooks/useAuth'
import type { ServidorConRelaciones, EstadoContrato, TipoNombramiento } from '@/types/api'

export function ExpedienteView() {
  const { hasPermiso } = useAuth()
  const [page, setPage]     = useState(1)
  const [search, setSearch] = useState('')
  const [contratoEstado, setContratoEstado] = useState<string | null>(null)
  const [enFunciones, setEnFunciones]       = useState<boolean | null>(null)
  const [unidadId, setUnidadId]             = useState<number | null>(null)
  const [tipoNombramiento, setTipoNombramiento] = useState<string | null>(null)
  const [anioIngreso, setAnioIngreso]       = useState<number | null>(null)
  const [exportando, setExportando]         = useState<'excel' | 'pdf' | null>(null)
  const [selectedRecords, setSelectedRecords] =
    useState<ServidorConRelaciones[]>([])

  const [modalOpened,  { open: openModal,  close: closeModal  }] = useDisclosure(false)
  const [detailOpened, { open: openDetail, close: closeDetail }] = useDisclosure(false)
  const [loteOpened,   { open: openLote,   close: closeLote   }] = useDisclosure(false)

  const [editServidor, setEditServidor] =
    useState<ServidorConRelaciones | null>(null)
  const [viewServidor, setViewServidor] =
    useState<ServidorConRelaciones | null>(null)

  const filtros = {
    search:            search || undefined,
    contrato_estado:   (contratoEstado as EstadoContrato) || undefined,
    en_funciones:      enFunciones ?? undefined,
    unidad_administrativa_id: unidadId ?? undefined,
    tipo_nombramiento: (tipoNombramiento as TipoNombramiento) || undefined,
    anio_ingreso:      anioIngreso ?? undefined,
  }

  const { data, isLoading } = useServidores({
    page,
    per_page: 15,
    ...filtros,
  })

  const servidores = (data?.data ?? []) as ServidorConRelaciones[]

  const handleExportar = async (tipo: 'excel' | 'pdf') => {
    setExportando(tipo)
    try {
      const blob = tipo === 'excel'
        ? await expedienteService.exportarExcel(filtros)
        : await expedienteService.exportarPdf(filtros)
      const url = URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = url
      link.download = `nomina_servidores.${tipo === 'excel' ? 'xlsx' : 'pdf'}`
      link.click()
      URL.revokeObjectURL(url)
    } catch (error) {
      notifications.show({
        title: 'Error',
        message: getApiErrorMessage(error, 'No se pudo generar la exportación.'),
        color: 'red',
      })
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

  const handleNuevo = () => {
    setEditServidor(null)
    openModal()
  }

  return (
    <Box>
      <PageHeader
        title="Expediente Digital"
        subtitle="Gestión de servidores públicos del GAD Provincial de Esmeraldas"
        icon={<IconFolder size={28} />}
      />

      <Group justify="flex-end" mb="md">
        {hasPermiso('solicitar-certificacion-medica') &&
          selectedRecords.length > 0 && (
          <Button
            color="blue"
            variant="light"
            leftSection={<IconStethoscope size={16} />}
            onClick={openLote}
          >
            Solicitar certificación médica ({selectedRecords.length})
          </Button>
        )}
        <Button
          variant="light"
          color="gray"
          leftSection={<IconFileSpreadsheet size={16} />}
          loading={exportando === 'excel'}
          onClick={() => handleExportar('excel')}
        >
          Exportar Excel
        </Button>
        <Button
          variant="light"
          color="gray"
          leftSection={<IconFileTypePdf size={16} />}
          loading={exportando === 'pdf'}
          onClick={() => handleExportar('pdf')}
        >
          Exportar PDF
        </Button>
        <Button
          color="emerald"
          variant="light"
          leftSection={<IconUserPlus size={16} />}
          onClick={handleNuevo}
        >
          Nuevo servidor
        </Button>
      </Group>

      <ServidorToolbar
        onSearch={setSearch}
        onContratoEstadoChange={setContratoEstado}
        onEnFuncionesChange={setEnFunciones}
        onUnidadChange={setUnidadId}
        onTipoNombramientoChange={setTipoNombramiento}
        onAnioIngresoChange={setAnioIngreso}
      />

      {!isLoading && servidores.length === 0 ? (
        <EmptyState
          icon={IconFolder}
          title="No hay servidores registrados"
          description="Comienza registrando el primer servidor del GAD."
          action={
            <Button color="emerald" variant="light"
              leftSection={<IconUserPlus size={14} />}
              onClick={handleNuevo}>
              Nuevo servidor
            </Button>
          }
        />
      ) : (
        <ServidorTable
          data={servidores}
          isLoading={isLoading}
          total={data?.total ?? 0}
          page={page}
          onPageChange={setPage}
          onView={handleView}
          onEdit={handleEdit}
          selectedRecords={selectedRecords}
          onSelectedRecordsChange={setSelectedRecords}
        />
      )}

      <ServidorModal
        key={editServidor?.id ?? 'nuevo'}
        opened={modalOpened}
        onClose={() => { setEditServidor(null); closeModal() }}
        servidor={editServidor}
      />
      <ServidorDetail
        opened={detailOpened}
        onClose={closeDetail}
        servidor={viewServidor}
        onEdit={handleEdit}
      />
      <SolicitarCertificacionLoteModal
        opened={loteOpened}
        onClose={() => { setSelectedRecords([]); closeLote() }}
        servidores={selectedRecords}
      />
    </Box>
  )
}
