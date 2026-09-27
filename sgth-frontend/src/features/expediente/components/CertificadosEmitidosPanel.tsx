'use client'

import { IconCertificate } from '@tabler/icons-react'
import { DataState, SectionCard, SgthTable } from '@/components/ui'
import { useCertificadosEmitidos } from '../hooks/useCertificadosEmitidos'
import { getCertificadosEmitidosColumns } from './certificadosEmitidos.columns'

interface Props {
  servidorId: number
}

/**
 * Los certificados laborales que ya se entregaron de este servidor.
 *
 * Va en Documentos y no en una pantalla propia porque responde a la pregunta
 * que se hace ahí: qué papeles hay de esta persona. Sirve para dos cosas a la
 * vez —evitar emitir dos veces lo mismo y dejar registro de quién accedió a
 * datos personales, que el certificado lleva cédula y a veces remuneración—.
 *
 * Deliberadamente no se puede volver a descargar uno ya emitido: el PDF no se
 * guarda en disco, y reconstruirlo daría un documento distinto al que la
 * persona tiene en la mano. Quien necesite el contenido, que emita otro.
 */
export function CertificadosEmitidosPanel({ servidorId }: Props) {
  const { data: emisiones = [], isLoading, error } =
    useCertificadosEmitidos(servidorId)

  return (
    <SectionCard title="Certificados emitidos">
      <DataState
        loading={isLoading}
        error={error}
        empty={emisiones.length === 0}
        skeletonRows={2}
        emptyProps={{
          icon: IconCertificate,
          title: 'Sin certificados emitidos',
          description:
            'Aquí quedará constancia de cada certificado laboral que Talento Humano entregue.',
        }}
      >
        <SgthTable
          records={emisiones}
          columns={getCertificadosEmitidosColumns()}
          minHeight={100}
        />
      </DataState>
    </SectionCard>
  )
}
