'use client'

import { useState } from 'react'
import { IconVaccine } from '@tabler/icons-react'
import { DataState, SgthTable } from '@/components/ui'
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

export function AtencionesEnfermeriaTable({ fecha }: Props) {
  const { data, isLoading, error } = useAtencionesEnfermeria({ fecha })
  const anular = useAnularAtencionEnfermeria()
  const [aAnular, setAAnular] = useState<AtencionEnfermeria | null>(null)

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
          records={atenciones}
          columns={getAtencionesEnfermeriaColumns({ onAnular: setAAnular })}
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
