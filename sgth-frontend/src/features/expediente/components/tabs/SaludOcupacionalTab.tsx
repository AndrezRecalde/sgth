'use client'

import { useState } from 'react'
import { Stack } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconStethoscope } from '@tabler/icons-react'
import { DataState, PAGINACION_ES, SectionCard, SgthTable } from '@/components/ui'
import { useAuth } from '@/hooks/useAuth'
import { useSolicitudesCertificacion } from '@/features/dispensario/hooks/useSolicitudCertificacion'
import { useCertificadoAptitud } from '@/features/dispensario/hooks/useCertificadoAptitud'
import { getSaludOcupacionalColumns } from '../saludOcupacional.columns'
import { AptitudVigentePanel } from '../AptitudVigentePanel'
import { AusentismoSaludPanel } from '../AusentismoSaludPanel'
import { SolicitarCertificacionLoteModal } from '../SolicitarCertificacionLoteModal'
import { aptitudVigente } from '../../utils/aptitudMedica'
import type { ServidorConRelaciones } from '@/types/api'

interface Props {
  servidor: ServidorConRelaciones
}

/** El mismo tamaño de página que el resto de los listados del sistema. */
const POR_PAGINA = 15

export function SaludOcupacionalTab({ servidor }: Props) {
  const servidorId = Number(servidor.id)
  const { hasPermiso } = useAuth()
  const [page, setPage] = useState(1)
  const [solicitarOpened, { open: abrirSolicitar, close: cerrarSolicitar }] =
    useDisclosure(false)

  const { data, isLoading, error } = useSolicitudesCertificacion({
    page,
    per_page: POR_PAGINA,
    servidor_id: servidorId,
  })
  const solicitudes = data?.data ?? []

  // La aptitud que rige se pide aparte y no se saca de la tabla: la tabla
  // pagina, y estando en la página 2 la evaluación más reciente no está ahí.
  const { data: ultimas, isLoading: cargandoAptitud } = useSolicitudesCertificacion({
    servidor_id: servidorId,
    estado: 'completada',
    per_page: 1,
  })
  const aptitud = aptitudVigente(ultimas?.data?.[0])

  const certificado = useCertificadoAptitud()

  const columns = getSaludOcupacionalColumns({
    descargandoId: certificado.descargandoId,
    onDescargarCertificado: (s) => certificado.descargar(s.id, s.cedula_paciente),
  })

  return (
    <Stack gap="md">
      <AptitudVigentePanel
        aptitud={aptitud}
        cargando={cargandoAptitud}
        onSolicitar={abrirSolicitar}
        puedeSolicitar={hasPermiso('solicitar-certificacion-medica')}
      />

      <AusentismoSaludPanel servidorId={servidorId} />

      <SectionCard
        title="Historial de evaluaciones"
        description="Certificaciones médicas ocupacionales (FEMO) solicitadas para este servidor."
      >
        <DataState
          loading={isLoading}
          error={error}
          empty={solicitudes.length === 0}
          skeletonRows={3}
          emptyProps={{
            icon: IconStethoscope,
            title: 'Sin certificaciones registradas',
            description: 'Este servidor no tiene solicitudes de certificación médica.',
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
            minHeight={100}
          />
        </DataState>
      </SectionCard>

      {/* El mismo formulario del listado, con este servidor ya elegido: antes
          había que salir de la ficha, buscarlo en la tabla y marcarlo. */}
      <SolicitarCertificacionLoteModal
        opened={solicitarOpened}
        onClose={cerrarSolicitar}
        servidores={[servidor]}
      />
    </Stack>
  )
}
