'use client'

import { useState } from 'react'
import { IconHourglassEmpty } from '@tabler/icons-react'
import {
  DataState,
  PAGINACION_ES,
  SectionCard,
  SgthTable,
  confirmar,
} from '@/components/ui'
import { useAuth } from '@/hooks/useAuth'
import { getServidoresSobreTopeColumns } from './servidoresSobreTope.columns'
import { useServidoresSobreTope } from '../hooks/useServidoresSobreTope'
import { usePeriodosMutations } from '../hooks/usePeriodosMutations'
import type { ServidorSobreTope } from '@/types/api'

/*
| Quién está cerca de su tope de acumulación o lo pasa.
|
| LOSEP (art. 29): 60 días. Código del Trabajo (art. 75): tres años de lo que
| genera. Nada vence solo: el excedente lo vence Talento Humano, servidor por
| servidor, y queda en la bitácora.
*/

/** El mismo tamaño de página que el resto de los listados del sistema. */
const POR_PAGINA = 15

const dias = (n: number) => `${n.toFixed(2)} días`

export function TopeAcumulacionCard() {
  const { hasPermiso } = useAuth()
  const puedeVencer = hasPermiso('gestionar-vacaciones')

  const [pagina, setPagina] = useState(1)

  const { data, isLoading, error, refetch } = useServidoresSobreTope()
  const { vencerExcedente } = usePeriodosMutations()
  const filas = data ?? []

  // El endpoint devuelve la lista institucional completa sin paginar, así que
  // se pagina aquí: son las personas por encima del 75 % de su tope, y cuántas
  // sean depende del año, no de un filtro. Sin paginador la tarjeta crecía sin
  // freno y empujaba el resto de la pantalla fuera de la vista.
  const visibles = filas.slice((pagina - 1) * POR_PAGINA, pagina * POR_PAGINA)

  const pedirVencimiento = (f: ServidorSobreTope) =>
    confirmar({
      title: 'Vencer el excedente',
      message: (
        <>
          <b>{f.nombre}</b> tiene <b>{dias(f.saldo)}</b> y su tope es de{' '}
          {dias(f.tope)}. Vencerán <b>{dias(f.excedente)}</b>, tomados de sus
          períodos más antiguos, y el saldo quedará en {dias(f.tope)}. Los días
          vencidos no se recuperan con una regeneración. Queda registrado en la
          bitácora.
        </>
      ),
      confirmLabel: 'Vencer',
      destructiva: true,
      onConfirm: () => vencerExcedente.mutate(f.servidor_id),
    })

  const columnas = getServidoresSobreTopeColumns({
    puedeVencer,
    onVencer: pedirVencimiento,
  })

  return (
    <SectionCard
      title="Tope de acumulación"
      description="LOSEP: 60 días. Código del Trabajo: tres años de lo que genera el servidor. Se listan desde el 75 % del tope; el excedente no vence hasta que Talento Humano lo decide."
    >
      <DataState
        loading={isLoading}
        error={error}
        empty={!filas.length}
        page={pagina}
        errorTitle="No se pudo cargar el seguimiento del tope"
        errorHint="No quiere decir que nadie esté cerca de su tope: no se pudo consultar."
        onRetry={() => void refetch()}
        skeletonRows={3}
        emptyProps={{
          icon: IconHourglassEmpty,
          title: 'Nadie está cerca de su tope',
          description: 'Ningún servidor activo llega al 75 % de su tope de acumulación.',
        }}
      >
        <SgthTable
          {...PAGINACION_ES}
          records={visibles}
          columns={columnas}
          idAccessor="servidor_id"
          minHeight={120}
          totalRecords={filas.length}
          recordsPerPage={POR_PAGINA}
          page={pagina}
          onPageChange={setPagina}
        />
      </DataState>
    </SectionCard>
  )
}
