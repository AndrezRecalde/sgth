'use client'

import { Button } from '@mantine/core'
import { IconLock } from '@tabler/icons-react'
import { useRouter } from 'next/navigation'
import { DataState, EmptyState, PageHeader, PageShell } from '@/components/ui'
import { ROUTES } from '@/config/routes'
import { useAuth } from '@/hooks/useAuth'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { ConvocatoriaForm } from '@/features/seleccion/components/ConvocatoriaForm'
import { useActualizarConvocatoria, useConvocatoriaDetalle } from '@/features/seleccion/hooks/useConvocatoria'
import { CAMPOS_CONVOCATORIA, type ConvocatoriaFormData } from '@/features/seleccion/schemas/convocatoria.schema'
import type { Convocatoria } from '@/features/seleccion/services/convocatoriaService'

const aFormulario = (c: Convocatoria): ConvocatoriaFormData => ({
  puesto_id:    c.puesto_id,
  titulo:       c.titulo,
  descripcion:  c.descripcion,
  tipo:         c.tipo as ConvocatoriaFormData['tipo'],
  vacantes:     c.vacantes,
  // El API las manda con hora; el formulario trabaja con la fecha.
  fecha_inicio: c.fecha_inicio.slice(0, 10),
  fecha_fin:    c.fecha_fin.slice(0, 10),
})

/**
 * Corregir una convocatoria en borrador (decisión 3 de TH, 2026-10-05). El
 * enlace «Editar» existía en el listado, pero esta página no: daba 404.
 * Publicada, sus condiciones ya se anunciaron y no se editan.
 */
export function EditarConvocatoriaView({ id }: { id: string }) {
  const router = useRouter()
  const convocatoriaId = Number(id)
  const { data: convocatoria, isLoading, error, refetch } = useConvocatoriaDetalle(convocatoriaId)
  const actualizar = useActualizarConvocatoria()
  const gestiona = useAuth().hasPermiso('gestionar-convocatorias')
  const volver = () => router.push(ROUTES.SGTH.CONVOCATORIA(convocatoriaId))

  const editable = !!convocatoria && convocatoria.estado === 'borrador' && gestiona

  return (
    <PageShell>
      <PageHeader
        title="Editar convocatoria"
        description={convocatoria ? `${convocatoria.codigo} — ${convocatoria.titulo}` : undefined}
        onBack={volver}
      />

      <DataState
        loading={isLoading}
        error={error}
        errorTitle="No se pudo cargar la convocatoria"
        onRetry={refetch}
        skeletonRows={6}
      >
        {convocatoria && !editable && (
          <EmptyState
            icon={IconLock}
            title="Esta convocatoria no se puede editar"
            description={gestiona
              ? 'Solo se edita mientras está en borrador: una vez publicada, sus condiciones ya se anunciaron.'
              : 'Editar convocatorias corresponde a quien las gestiona en Talento Humano.'}
            action={<Button variant="default" onClick={volver}>Volver a la convocatoria</Button>}
          />
        )}

        {editable && (
          <ConvocatoriaForm
            valoresIniciales={aFormulario(convocatoria)}
            textoEnviar="Guardar cambios"
            enviando={actualizar.isPending}
            onCancelar={volver}
            onSubmit={(valores, setError) =>
              actualizar.mutateAsync({ id: convocatoriaId, data: valores })
                .then(volver)
                .catch((e) => erroresAlFormulario(e, setError, CAMPOS_CONVOCATORIA, 'No se pudo guardar la convocatoria'))
            }
          />
        )}
      </DataState>
    </PageShell>
  )
}
