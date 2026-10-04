'use client'

import { useState } from 'react'
import { Stack } from '@mantine/core'
import { useDebouncedValue, useDisclosure } from '@mantine/hooks'
import { IconStethoscope } from '@tabler/icons-react'
import { useAuth } from '@/hooks/useAuth'
import { SolicitarCertificacionLoteModal } from '@/features/expediente/components/SolicitarCertificacionLoteModal'
import { guardarArchivo } from '@/lib/archivo'
import { getApiErrorMessage } from '@/types/api'
import {
  DataState, notificar, PAGINACION_ES, SgthTable,
} from '@/components/ui'
import { useCobertura } from '../hooks/useCoberturaCertificacion'
import { useCertificadoAptitud } from '../hooks/useCertificadoAptitud'
import { CoberturaFiltros } from './CoberturaFiltros'
import { getCoberturaColumns } from './cobertura-certificaciones.columns'
import { ResumenCoberturaTarjetas } from './ResumenCoberturaTarjetas'
import {
  coberturaCertificacionService,
  type EstadoCobertura,
  type FilaCobertura,
} from '../services/coberturaCertificacionService'

/** El mismo tamaño de página que el resto de los listados del sistema. */
const POR_PAGINA = 15

/**
 * El tablero de cobertura de las evaluaciones médicas ocupacionales.
 *
 * Una fila por servidor activo, no por solicitud: es la única pantalla del
 * sistema donde aparece quien nunca fue evaluado, que es justo a quien hay
 * que mandar. De aquí sale el lote, con las filas marcadas.
 */
export function CoberturaTab() {
  const { hasPermiso } = useAuth()

  const [page, setPage] = useState(1)
  const [buscar, setBuscar] = useState('')
  const [unidad, setUnidad] = useState('')
  const [estado, setEstado] = useState('')
  const [seleccion, setSeleccion] = useState<FilaCobertura[]>([])
  const [exportando, setExportando] = useState(false)
  const [loteOpened, { open: abrirLote, close: cerrarLote }] = useDisclosure(false)

  const certificado = useCertificadoAptitud()

  // Sin el retardo, cada tecla del buscador lanza una consulta sobre toda la
  // plantilla.
  const [buscarDiferido] = useDebouncedValue(buscar, 350)

  const filtros = {
    unidad_administrativa_id: unidad ? Number(unidad) : undefined,
    estado_cobertura: (estado || undefined) as EstadoCobertura | undefined,
    buscar: buscarDiferido || undefined,
  }

  const { data, isLoading, error } = useCobertura({
    ...filtros, page, per_page: POR_PAGINA,
  })

  const filas = data?.data ?? []

  // Cambiar un filtro sin volver a la primera página consultaría esa misma
  // página del resultado ya filtrado —casi siempre vacía—, y la selección
  // arrastraría filas que ya no se ven.
  const filtrar = (aplicar: () => void) => {
    aplicar()
    setPage(1)
    setSeleccion([])
  }

  const exportar = async () => {
    setExportando(true)
    try {
      guardarArchivo(
        await coberturaCertificacionService.exportarExcel(filtros),
        'cobertura_certificaciones.xlsx',
      )
    } catch (e) {
      notificar.error(
        'No se pudo exportar la cobertura a Excel',
        getApiErrorMessage(e, 'Inténtelo de nuevo en unos segundos.'),
      )
    } finally {
      setExportando(false)
    }
  }

  return (
    <Stack gap="md">
      <ResumenCoberturaTarjetas resumen={data?.resumen} cargando={isLoading} />

      <CoberturaFiltros
        buscar={buscar}
        unidad={unidad}
        estado={estado}
        seleccionadas={seleccion.length}
        exportando={exportando}
        puedeSolicitar={hasPermiso('solicitar-certificacion-medica')}
        onBuscar={(v) => filtrar(() => setBuscar(v))}
        onUnidad={(v) => filtrar(() => setUnidad(v))}
        onEstado={(v) => filtrar(() => setEstado(v))}
        onExportar={exportar}
        onSolicitar={abrirLote}
      />

      <DataState
        loading={isLoading}
        error={error}
        empty={!filas.length}
        emptyProps={{
          icon: IconStethoscope,
          title: 'Sin servidores',
          description: 'Ningún servidor activo coincide con estos filtros.',
        }}
        page={page}
      >
        <SgthTable
          {...PAGINACION_ES}
          records={filas}
          columns={getCoberturaColumns({
            descargandoId: certificado.descargandoId,
            onDescargarCertificado: (f) =>
              certificado.descargar(f.ultima_solicitud_id!, f.cedula),
          })}
          idAccessor="servidor_id"
          totalRecords={data?.total ?? filas.length}
          recordsPerPage={POR_PAGINA}
          page={page}
          onPageChange={(p) => { setPage(p); setSeleccion([]) }}
          selectedRecords={seleccion}
          onSelectedRecordsChange={setSeleccion}
          // Quien ya tiene una solicitud viva no se puede marcar: `storeLote`
          // la omitiría en silencio y el aviso diría «1 creada, 1 omitida»
          // sin que nadie entendiera cuál.
          isRecordSelectable={(f) => !f.solicitud_activa_id}
          minHeight={200}
        />
      </DataState>

      <SolicitarCertificacionLoteModal
        opened={loteOpened}
        onClose={() => { cerrarLote(); setSeleccion([]) }}
        servidores={seleccion.map(f => ({
          id: f.servidor_id,
          cedula: f.cedula,
          nombre: f.nombre_completo,
        }))}
      />
    </Stack>
  )
}
