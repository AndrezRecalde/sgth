'use client'

import { useState } from 'react'
import { Select, Stack } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconClipboardHeart } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useAuth } from '@/hooks/useAuth'
import {
  useCancelarSolicitud,
  useSolicitudesCertificacion,
} from '../hooks/useSolicitudCertificacion'
import { useCertificadoAptitud } from '../hooks/useCertificadoAptitud'
import { useTodasUnidades } from '@/features/estructura/hooks/useUnidades'
import { getCertificacionesColumns } from './certificaciones.columns'
import {
  ESTADO_SOLICITUD_FILTRO_OPTIONS,
  etiquetaTipoEvento,
  type SolicitudCertificacion,
} from '../services/solicitudCertificacionService'
import type { UnidadConRelaciones } from '@/types/api'
import {
  DataState, MotivoModal, PAGINACION_ES, SgthTable, Toolbar,
} from '@/components/ui'

const ANIO_ACTUAL = new Date().getFullYear()

/** El mismo tamaño de página que el resto de los listados del sistema. */
const POR_PAGINA = 15

function generarOpcionesAnio(): { value: string; label: string }[] {
  const opciones = [{ value: '', label: 'Todos los años' }]
  for (let anio = ANIO_ACTUAL; anio >= ANIO_ACTUAL - 5; anio--) {
    opciones.push({ value: String(anio), label: String(anio) })
  }
  return opciones
}

/*
| El seguimiento de las solicitudes enviadas al Dispensario.
|
| Era la pantalla entera de «Certificaciones médicas» y ahora es su segunda
| pestaña: la primera es el tablero de cobertura, que responde a quién le toca
| la evaluación. Esta responde a qué pasó con lo que ya se pidió.
*/
export function SeguimientoSolicitudesTab() {
  const contained = useContainedInput('sm')
  const { hasPermiso } = useAuth()

  const [page, setPage] = useState(1)
  const [filtroEstado, setFiltroEstado] = useState<string>('')
  const [filtroUnidad, setFiltroUnidad] = useState<string>('')
  const [filtroAnio, setFiltroAnio] = useState<string>(String(ANIO_ACTUAL))

  const { data: unidades = [] } = useTodasUnidades()
  const unidadOptions = [
    { value: '', label: 'Todas las unidades' },
    ...((unidades ?? []) as UnidadConRelaciones[]).map(u => ({
      value: String(u.id),
      label: u.nombre ?? `Unidad ${u.id}`,
    })),
  ]

  const { data, isLoading, error } = useSolicitudesCertificacion({
    page,
    per_page: POR_PAGINA,
    estado: filtroEstado || undefined,
    unidad_administrativa_id: filtroUnidad ? Number(filtroUnidad) : undefined,
    anio: filtroAnio ? Number(filtroAnio) : undefined,
  })

  const solicitudes = data?.data ?? []

  const cancelar = useCancelarSolicitud()
  const [cancelando, setCancelando] = useState<SolicitudCertificacion | null>(null)
  const [cancelarOpened, { open: abrirCancelar, close: cerrarCancelar }] =
    useDisclosure(false)

  // Cambiar un filtro sin volver a la primera página consultaría esa misma
  // página del resultado ya filtrado —casi siempre vacía—, así que la tabla
  // saldría en blanco aunque hubiera coincidencias.
  const filtrar = (aplicar: () => void) => {
    aplicar()
    setPage(1)
  }

  const certificado = useCertificadoAptitud()

  const columns = getCertificacionesColumns({
    puedeCancelar: hasPermiso('solicitar-certificacion-medica'),
    descargandoId: certificado.descargandoId,
    onDescargarCertificado: (s) => certificado.descargar(s.id, s.cedula_paciente),
    onCancelar: (solicitud) => {
      setCancelando(solicitud)
      abrirCancelar()
    },
  })

  const cerrarModal = () => {
    cerrarCancelar()
    setCancelando(null)
  }

  return (
    <Stack gap="md">
      <Toolbar>
        <Select
          label="Estado"
          placeholder="Todas"
          data={ESTADO_SOLICITUD_FILTRO_OPTIONS}
          style={{ minWidth: 180 }}
          {...contained}
          value={filtroEstado}
          onChange={(v) => filtrar(() => setFiltroEstado(v ?? ''))}
        />
        <Select
          label="Unidad administrativa"
          placeholder="Todas las unidades"
          data={unidadOptions}
          searchable
          style={{ minWidth: 240 }}
          {...contained}
          value={filtroUnidad}
          onChange={(v) => filtrar(() => setFiltroUnidad(v ?? ''))}
        />
        <Select
          label="Año"
          placeholder="Todos los años"
          data={generarOpcionesAnio()}
          style={{ minWidth: 150 }}
          {...contained}
          value={filtroAnio}
          onChange={(v) => filtrar(() => setFiltroAnio(v ?? ''))}
        />
      </Toolbar>

      <DataState
        loading={isLoading}
        error={error}
        empty={!solicitudes.length}
        emptyProps={{
          icon: IconClipboardHeart,
          title: 'Sin solicitudes',
          description: 'No se han enviado solicitudes de certificación médica.',
        }}
        page={page}
      >
        <SgthTable
          {...PAGINACION_ES}
          records={solicitudes}
          columns={columns}
          totalRecords={data?.total ?? solicitudes.length}
          recordsPerPage={POR_PAGINA}
          page={page}
          onPageChange={setPage}
          minHeight={200}
        />
      </DataState>

      <MotivoModal
        opened={cancelarOpened}
        onClose={cerrarModal}
        title="Cancelar solicitud de certificación"
        descripcion={
          cancelando
            ? (
                <>
                  Se retirará la evaluación <b>{etiquetaTipoEvento(cancelando.tipo_evento)}</b> de{' '}
                  <b>{cancelando.nombres_paciente}</b>. Saldrá de la bandeja del
                  Dispensario y el servidor volverá a admitir una solicitud nueva.
                </>
              )
            : 'Se retirará la solicitud seleccionada.'
        }
        confirmLabel="Cancelar solicitud"
        destructiva
        cargando={cancelar.isPending}
        onConfirm={(motivo) => {
          if (!cancelando) return
          cancelar.mutate(
            { id: cancelando.id, motivo },
            { onSuccess: cerrarModal },
          )
        }}
      />
    </Stack>
  )
}
