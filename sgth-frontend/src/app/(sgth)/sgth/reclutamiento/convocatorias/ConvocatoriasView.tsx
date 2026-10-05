'use client'

import { useState } from 'react'
import { Button } from '@mantine/core'
import { IconPlus, IconSpeakerphone } from '@tabler/icons-react'
import { useRouter } from 'next/navigation'
import { confirmar, DataState, PageHeader, PageShell, SgthTable } from '@/components/ui'
import { ROUTES } from '@/config/routes'
import { useAuth } from '@/hooks/useAuth'
import { columnasConvocatorias } from '@/features/seleccion/components/convocatorias.columns'
import {
  useConvocatorias, useEliminarConvocatoria, usePublicarConvocatoria,
} from '@/features/seleccion/hooks/useConvocatoria'

const POR_PAGINA = 15

export function ConvocatoriasView() {
  const router = useRouter()
  const gestiona = useAuth().hasPermiso('gestionar-convocatorias')
  const [page, setPage] = useState(1)
  const eliminar = useEliminarConvocatoria()
  const publicar = usePublicarConvocatoria()

  const { data, isLoading, error, refetch } = useConvocatorias({ page, per_page: POR_PAGINA })
  const convocatorias = data?.data ?? []

  const columns = columnasConvocatorias({
    gestiona,
    onVer: (c) => router.push(ROUTES.SGTH.CONVOCATORIA(c.id)),
    onEditar: (c) => router.push(ROUTES.SGTH.CONVOCATORIA_EDITAR(c.id)),
    onPublicar: (c) => confirmar({
      title: 'Publicar convocatoria',
      message: <>Se publicará <b>{c.titulo}</b> y quedará visible para los postulantes.</>,
      confirmLabel: 'Publicar',
      onConfirm: () => publicar.mutate(c.id),
    }),
    onEliminar: (c) => confirmar({
      title: 'Eliminar convocatoria',
      message: <>Se eliminará <b>{c.titulo}</b>. No se puede deshacer.</>,
      destructiva: true,
      onConfirm: () => eliminar.mutate(c.id),
    }),
  })

  return (
    <PageShell>
      <PageHeader
        title="Convocatorias"
        description="Concursos de méritos y oposición"
        actions={gestiona && (
          <Button leftSection={<IconPlus size={14} />} onClick={() => router.push(ROUTES.SGTH.CONVOCATORIA_NUEVA)}>
            Nueva convocatoria
          </Button>
        )}
      />

      <DataState
        loading={isLoading}
        error={error}
        errorTitle="No se pudieron cargar las convocatorias"
        onRetry={refetch}
        empty={!convocatorias.length}
        emptyProps={{
          icon: IconSpeakerphone,
          title: 'Sin convocatorias',
          description: 'No hay convocatorias registradas aún. Las modalidades sin concurso están en Reclutamiento Express.',
        }}
        page={page}
      >
        <SgthTable
          records={convocatorias}
          columns={columns}
          totalRecords={data?.total ?? 0}
          recordsPerPage={POR_PAGINA}
          page={page}
          onPageChange={setPage}
          minHeight={200}
        />
      </DataState>
    </PageShell>
  )
}
