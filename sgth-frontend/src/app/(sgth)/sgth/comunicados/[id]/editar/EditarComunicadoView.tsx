'use client'

import { Button, Grid, Stack } from '@mantine/core'
import { IconLock } from '@tabler/icons-react'
import { useRouter } from 'next/navigation'
import { DataState, EmptyState, PageHeader, PageShell } from '@/components/ui'
import { ROUTES } from '@/config/routes'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { AdjuntosComunicado } from '@/features/comunicados/components/AdjuntosComunicado'
import { ComunicadoForm } from '@/features/comunicados/components/ComunicadoForm'
import { PortadaComunicado } from '@/features/comunicados/components/PortadaComunicado'
import { useComunicado } from '@/features/comunicados/hooks/useComunicados'
import { useMutacionesComunicado } from '@/features/comunicados/hooks/useMutacionesComunicado'
import { usePermisosComunicados } from '@/features/comunicados/hooks/usePermisosComunicados'
import { CAMPOS_COMUNICADO } from '@/features/comunicados/schemas/comunicado.schema'
import { aFormulario, aPayload } from '@/features/comunicados/utils/formulario'

/**
 * Seguir un borrador: el texto, los destinatarios y las fechas a la izquierda;
 * la portada y los documentos, que se guardan al subirlos, a la derecha. Al
 * guardar se pasa al detalle, que es la vista previa y donde se publica.
 * Lo publicado no se edita (D5): se retira y se duplica.
 */
export function EditarComunicadoView({ id }: { id: string }) {
  const router = useRouter()
  const comunicadoId = Number(id)
  const { data: comunicado, isLoading, error, refetch } = useComunicado(comunicadoId)
  const { actualizar } = useMutacionesComunicado()
  const { redacta } = usePermisosComunicados()
  const alDetalle = () => router.push(ROUTES.SGTH.COMUNICADO(comunicadoId))

  const editable = !!comunicado && comunicado.estado === 'borrador' && redacta

  return (
    <PageShell>
      <PageHeader
        title="Editar borrador"
        description={comunicado?.titulo}
        backHref={ROUTES.SGTH.COMUNICADOS}
      />

      <DataState
        loading={isLoading}
        error={error}
        errorTitle="No se pudo cargar la publicación"
        onRetry={refetch}
        skeletonRows={6}
      >
        {comunicado && !editable && (
          <EmptyState
            icon={IconLock}
            title="Esta publicación no se puede editar"
            description={redacta
              ? 'Solo se edita un borrador. Para corregir lo publicado, retírelo y duplíquelo.'
              : 'No tiene permiso para redactar noticias y comunicados.'}
            action={<Button variant="light" onClick={alDetalle}>Ver la publicación</Button>}
          />
        )}

        {comunicado && editable && (
          <Grid gap="md">
            <Grid.Col span={{ base: 12, lg: 8 }}>
              <ComunicadoForm
                // La clave fuerza un formulario nuevo si cambia el registro.
                key={comunicado.id}
                valoresIniciales={aFormulario(comunicado)}
                enviando={actualizar.isPending}
                onCancelar={alDetalle}
                onSubmit={(valores, setError) =>
                  actualizar.mutateAsync({ id: comunicado.id, datos: aPayload(valores) })
                    .then(alDetalle)
                    .catch((e) => erroresAlFormulario(e, setError, CAMPOS_COMUNICADO, 'No se pudo guardar el borrador'))
                }
              />
            </Grid.Col>
            <Grid.Col span={{ base: 12, lg: 4 }}>
              <Stack gap="md">
                <PortadaComunicado comunicado={comunicado} />
                <AdjuntosComunicado comunicado={comunicado} />
              </Stack>
            </Grid.Col>
          </Grid>
        )}
      </DataState>
    </PageShell>
  )
}
