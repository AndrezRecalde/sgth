'use client'

import { useState } from 'react'
import { Stack } from '@mantine/core'
import { IconCircleCheck, IconCircleX, IconCloudUpload, IconExternalLink } from '@tabler/icons-react'
import { DataState, MotivoModal, PAGINACION_ES, SgthTable } from '@/components/ui'
import { useAuthStore } from '@/store/auth.store'
import { useEnviosPorValidar, useEnviosResultado } from '../../hooks/useEnviosResultado'
import { pacienteDeOrden } from '../../services/bandejaExamenesService'
import { ValidarEnvioModal } from './ValidarEnvioModal'
import { columnasPorValidar } from './porValidar.columns'
import type { EnvioPorValidar } from '../../services/envioResultadoService'

const POR_PAGINA = 15

/**
 * Lo que los pacientes enviaron desde el Portal y nadie ha validado (fase 6,
 * D2). Lo validan o lo rechazan el médico y el odontólogo; Enfermería lo ve.
 */
export function PorValidarBandeja({ buscar }: { buscar: string }) {
  const { hasRole } = useAuthStore()
  const [page, setPage] = useState(1)
  const [validando, setValidando] = useState<EnvioPorValidar | null>(null)
  const [rechazando, setRechazando] = useState<EnvioPorValidar | null>(null)
  const { rechazar, verArchivo } = useEnviosResultado()

  const { data, isLoading, error, refetch } = useEnviosPorValidar({ buscar, page, per_page: POR_PAGINA })
  const envios = data?.data ?? []
  const puedeAtender = hasRole('medico') || hasRole('odontologo')

  return (
    <Stack gap="sm">
      <DataState loading={isLoading} error={error} onRetry={() => refetch()} page={page}
        errorTitle="No se pudieron cargar los resultados enviados desde el Portal" empty={!envios.length}
        emptyProps={{
          icon: IconCloudUpload,
          title: 'Nada por validar',
          description: buscar
            ? 'Ningún resultado enviado desde el Portal coincide con la búsqueda.'
            : 'Cuando un servidor envíe un resultado desde el Portal, aparecerá aquí.',
        }}>
        <SgthTable
          {...PAGINACION_ES}
          records={envios}
          minHeight={200}
          totalRecords={data?.total ?? 0}
          recordsPerPage={POR_PAGINA}
          page={page}
          onPageChange={setPage}
          columns={columnasPorValidar((e) => [
            { label: 'Validar', icon: <IconCircleCheck size={14} />, hidden: !puedeAtender, onClick: () => setValidando(e) },
            { label: 'Ver archivo', icon: <IconExternalLink size={14} />, onClick: () => verArchivo.mutate(e.id) },
            { label: 'Rechazar', icon: <IconCircleX size={14} />, color: 'red', hidden: !puedeAtender, onClick: () => setRechazando(e) },
          ])}
        />
      </DataState>

      <ValidarEnvioModal envio={validando} onClose={() => setValidando(null)} />
      <MotivoModal
        opened={rechazando !== null}
        onClose={() => setRechazando(null)}
        title="Rechazar el resultado enviado"
        descripcion={rechazando
          ? `No se agrega a la historia de ${pacienteDeOrden(rechazando.orden_examen).nombre}. Verá el motivo en el Portal y podrá enviar otro.`
          : ''}
        confirmLabel="Rechazar"
        destructiva
        cargando={rechazar.isPending}
        placeholder="Ej.: la foto no se lee; envíe el PDF del laboratorio"
        onConfirm={(motivo) => rechazando && rechazar.mutate(
          { id: rechazando.id, motivo },
          { onSuccess: () => setRechazando(null) },
        )}
      />
    </Stack>
  )
}
