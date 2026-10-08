'use client'

import { useState } from 'react'
import { Stack } from '@mantine/core'
import { useDebouncedValue } from '@mantine/hooks'
import { IconCertificate } from '@tabler/icons-react'
import { DataState, PAGINACION_ES, SgthTable } from '@/components/ui'
import { useAuth } from '@/hooks/useAuth'
import { AprobarCertificadoModal } from './AprobarCertificadoModal'
import {
  CertificadosFiltros,
  FILTROS_CERTIFICADO_INICIALES,
  type FiltrosCertificado,
} from './CertificadosFiltros'
import { getCertificadosColumns } from './certificados.columns'
import { useCertificadosParaAprobar } from '../hooks/useCertificadosAprobacion'
import type { CertificadoAprobacion } from '@/types/api'

const POR_PAGINA = 15
const RETARDO_BUSQUEDA_MS = 300

/*
| La viñeta «Certificados médicos» de Asistencia › Permisos: los certificados
| que emite el dispensario, para que Talento Humano y Trabajo Social los
| aprueben y los registren en Sirha7 (decisión del 2026-10-08). Solo los del
| servidor titular, y sin diagnóstico.
*/
export function CertificadosTab({ activa = true }: { activa?: boolean }) {
  const { usuario } = useAuth()
  const [page, setPage] = useState(1)
  const [filtros, setFiltros] = useState<FiltrosCertificado>(FILTROS_CERTIFICADO_INICIALES)
  const [aprobando, setAprobando] = useState<CertificadoAprobacion | null>(null)
  const [folio] = useDebouncedValue(filtros.folio, RETARDO_BUSQUEDA_MS)

  // Sin volver a la página 1, un filtro nuevo pediría una página casi siempre vacía.
  const cambiarFiltros = (cambio: Partial<FiltrosCertificado>) => {
    setFiltros((actuales) => ({ ...actuales, ...cambio }))
    setPage(1)
  }

  const { data, isLoading, error } = useCertificadosParaAprobar({
    page,
    per_page: POR_PAGINA,
    estado: filtros.estado === 'todos' ? undefined : filtros.estado,
    unidad_administrativa_id: filtros.unidadId ? Number(filtros.unidadId) : undefined,
    fecha_desde: filtros.fechaDesde ?? undefined,
    fecha_hasta: filtros.fechaHasta ?? undefined,
    folio: folio || undefined,
  }, activa)

  const lista = data?.data ?? []

  const columns = getCertificadosColumns({
    // El propio no: el backend lo rechaza.
    puedeAprobar: (c) => c.pendiente && usuario?.servidor_id !== c.servidor_id,
    onAprobar: setAprobando,
  })

  return (
    <Stack gap="md">
      <CertificadosFiltros filtros={filtros} onCambiar={cambiarFiltros} />

      <DataState
        loading={isLoading}
        error={error}
        empty={!lista.length}
        emptyProps={{
          icon: IconCertificate,
          title: 'Sin certificados',
          description: folio
            ? `No se encontraron certificados con folio «${folio}»`
            : 'No hay certificados médicos que coincidan con los filtros.',
        }}
        page={page}
      >
        <SgthTable
          {...PAGINACION_ES}
          records={lista}
          columns={columns}
          totalRecords={data?.total ?? lista.length}
          recordsPerPage={POR_PAGINA}
          page={page}
          onPageChange={setPage}
          minHeight={200}
        />
      </DataState>

      <AprobarCertificadoModal certificado={aprobando} onClose={() => setAprobando(null)} />
    </Stack>
  )
}
