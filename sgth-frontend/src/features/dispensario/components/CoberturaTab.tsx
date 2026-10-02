'use client'

import { useState } from 'react'
import { Button, Select, Stack, TextInput } from '@mantine/core'
import { useDebouncedValue, useDisclosure } from '@mantine/hooks'
import {
  IconFileSpreadsheet, IconSearch, IconStethoscope,
} from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useAuth } from '@/hooks/useAuth'
import { useTodasUnidades } from '@/features/estructura/hooks/useUnidades'
import { SolicitarCertificacionLoteModal } from '@/features/expediente/components/SolicitarCertificacionLoteModal'
import { guardarArchivo } from '@/lib/archivo'
import { getApiErrorMessage, type UnidadConRelaciones } from '@/types/api'
import {
  DataState, notificar, PAGINACION_ES, SgthTable, Toolbar,
} from '@/components/ui'
import { useCobertura } from '../hooks/useCoberturaCertificacion'
import { getCoberturaColumns } from './cobertura-certificaciones.columns'
import { ResumenCoberturaTarjetas } from './ResumenCoberturaTarjetas'
import {
  coberturaCertificacionService,
  ESTADO_COBERTURA_FILTRO_OPTIONS,
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
  const contained = useContainedInput('sm')
  const { hasPermiso } = useAuth()

  const [page, setPage] = useState(1)
  const [buscar, setBuscar] = useState('')
  const [unidad, setUnidad] = useState('')
  const [estado, setEstado] = useState('')
  const [seleccion, setSeleccion] = useState<FilaCobertura[]>([])
  const [exportando, setExportando] = useState(false)
  const [loteOpened, { open: abrirLote, close: cerrarLote }] = useDisclosure(false)

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

  const { data: unidades = [] } = useTodasUnidades()
  const unidadOptions = [
    { value: '', label: 'Todas las unidades' },
    ...((unidades ?? []) as UnidadConRelaciones[]).map(u => ({
      value: String(u.id),
      label: u.nombre ?? `Unidad ${u.id}`,
    })),
  ]

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
        getApiErrorMessage(e, 'Inténtalo de nuevo en unos segundos.'),
      )
    } finally {
      setExportando(false)
    }
  }

  return (
    <Stack gap="md">
      <ResumenCoberturaTarjetas resumen={data?.resumen} cargando={isLoading} />

      <Toolbar
        actions={
          <>
            <Button
              variant="default"
              leftSection={<IconFileSpreadsheet size={16} />}
              loading={exportando}
              onClick={exportar}
            >
              Exportar
            </Button>
            {hasPermiso('solicitar-certificacion-medica') && (
              <Button
                leftSection={<IconStethoscope size={16} />}
                disabled={seleccion.length === 0}
                onClick={abrirLote}
              >
                Solicitar ({seleccion.length})
              </Button>
            )}
          </>
        }
      >
        <TextInput
          label="Buscar"
          placeholder="Nombre o cédula"
          leftSection={<IconSearch size={16} />}
          style={{ minWidth: 220 }}
          {...contained}
          value={buscar}
          onChange={(e) => filtrar(() => setBuscar(e.currentTarget.value))}
        />
        <Select
          label="Unidad administrativa"
          placeholder="Todas las unidades"
          data={unidadOptions}
          searchable
          style={{ minWidth: 240 }}
          {...contained}
          value={unidad}
          onChange={(v) => filtrar(() => setUnidad(v ?? ''))}
        />
        <Select
          label="Estado de cobertura"
          placeholder="Toda la plantilla"
          data={ESTADO_COBERTURA_FILTRO_OPTIONS}
          style={{ minWidth: 190 }}
          {...contained}
          value={estado}
          onChange={(v) => filtrar(() => setEstado(v ?? ''))}
        />
      </Toolbar>

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
          columns={getCoberturaColumns()}
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
