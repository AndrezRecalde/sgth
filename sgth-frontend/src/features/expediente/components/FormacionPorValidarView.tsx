'use client'

import { useState } from 'react'
import { useRouter } from 'next/navigation'
import { IconSchool } from '@tabler/icons-react'
import { DataState, PageHeader, PageShell, SgthTable, confirmar } from '@/components/ui'
import { ROUTES } from '@/config/routes'
import { useFormacionPorValidar, useValidacionFormacion } from '../hooks/useValidacionFormacion'
import { getFormacionPorValidarColumns } from './formacionPorValidar.columns'
import { RechazarFormacionModal } from './RechazarFormacionModal'
import type { FormacionPorValidar } from '@/types/api'

/**
 * «Títulos por validar»: lo que los servidores registran desde «Mi perfil» del
 * portal, con su PDF, a la espera de Talento Humano (2026-10-17). Validado,
 * entra en la hoja de vida; rechazado, el servidor ve el motivo.
 */
export function FormacionPorValidarView() {
  const router = useRouter()
  const { data: registros = [], isLoading, error, refetch } = useFormacionPorValidar()
  const { validar, verDocumento } = useValidacionFormacion()
  const [rechazando, setRechazando] = useState<FormacionPorValidar | null>(null)

  const columns = getFormacionPorValidarColumns({
    onDocumento: (r) => verDocumento.mutate({ servidorId: r.servidor_id, id: r.id }),
    onValidar: (r) => confirmar({
      title: 'Validar título o curso',
      message: `«${r.titulo_capacitacion}» quedará en el expediente y en la hoja de vida del servidor. Valídelo solo si el documento lo respalda.`,
      confirmLabel: 'Validar',
      onConfirm: () => validar.mutate({ servidorId: r.servidor_id, id: r.id }),
    }),
    onRechazar: setRechazando,
    onExpediente: (r) => router.push(ROUTES.SGTH.EXPEDIENTE_SERVIDOR(r.servidor_id)),
  })

  return (
    <PageShell>
      <PageHeader
        title="Títulos por validar"
        description="Los títulos y cursos que los servidores registran desde su perfil, con el documento que los respalda. Del más antiguo al más nuevo."
      />

      <DataState
        loading={isLoading}
        error={error}
        empty={!registros.length}
        errorTitle="No se pudo cargar la formación por validar"
        onRetry={() => void refetch()}
        emptyProps={{
          icon: IconSchool,
          title: 'No hay títulos por validar',
          description: 'Cuando un servidor registre un título o un curso desde su perfil, aparecerá aquí.',
        }}
      >
        <SgthTable records={registros} columns={columns} minHeight={200} />
      </DataState>

      <RechazarFormacionModal registro={rechazando} onClose={() => setRechazando(null)} />
    </PageShell>
  )
}
