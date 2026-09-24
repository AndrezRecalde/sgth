'use client'

import { useState } from 'react'
import { IconVaccine } from '@tabler/icons-react'
import { DataState, PAGINACION_ES, SgthTable } from '@/components/ui'
import {
  useAtencionesEnfermeria, useAnularAtencionEnfermeria,
} from '../hooks/useAtencionEnfermeria'
import {
  AnularRegistroModal, MOTIVOS_ANULAR_ATENCION,
} from './AnularRegistroModal'
import { getAtencionesEnfermeriaColumns } from './atencionesEnfermeria.columns'
import type { AtencionEnfermeria } from '../services/atencionEnfermeriaService'

interface Props {
  fecha: string
}

const POR_PAGINA = 15

export function AtencionesEnfermeriaTable({ fecha }: Props) {
  const [page, setPage] = useState(1)
  const anular = useAnularAtencionEnfermeria()
  const [aAnular, setAAnular] = useState<AtencionEnfermeria | null>(null)

  // Al cambiar de día se vuelve a la primera página. Sin esto, quien estuviera
  // en la página 3 de un día cargado y pasara a uno tranquilo pediría la
  // página 3 de un resultado que solo tiene una: la tabla saldría vacía como
  // si ese día no se hubiera atendido a nadie. Se ajusta durante el render, no
  // en un efecto, para no pedir primero la página equivocada.
  const [fechaAplicada, setFechaAplicada] = useState(fecha)
  if (fecha !== fechaAplicada) {
    setFechaAplicada(fecha)
    setPage(1)
  }

  const { data, isLoading, error } = useAtencionesEnfermeria({
    fecha, page, per_page: POR_PAGINA,
  })

  const atenciones = data?.data ?? []

  return (
    <>
      <DataState
        loading={isLoading}
        error={error}
        empty={!atenciones.length}
        skeletonRows={3}
        emptyProps={{
          icon: IconVaccine,
          title: 'Sin servicios registrados',
          description: 'No hay atenciones de enfermería para esta fecha.',
        }}
      >
        <SgthTable
          {...PAGINACION_ES}
          records={atenciones}
          columns={getAtencionesEnfermeriaColumns({ onAnular: setAAnular })}
          totalRecords={data?.total ?? atenciones.length}
          recordsPerPage={POR_PAGINA}
          page={page}
          onPageChange={setPage}
          minHeight={120}
          rowStyle={(atencion) =>
            atencion.anulado_en ? { opacity: 0.6 } : undefined
          }
        />
      </DataState>

      <AnularRegistroModal
        opened={!!aAnular}
        onClose={() => setAAnular(null)}
        titulo="Anular atención de enfermería"
        descripcion={`Se anulará ${aAnular?.folio ?? ''}.`}
        motivos={MOTIVOS_ANULAR_ATENCION}
        loading={anular.isPending}
        onConfirmar={(motivo) => {
          if (!aAnular) return
          anular.mutate(
            { id: aAnular.id, motivo },
            { onSuccess: () => setAAnular(null) }
          )
        }}
      />
    </>
  )
}
