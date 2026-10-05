'use client'

import { useState } from 'react'
import { Button } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconPlus, IconUsers } from '@tabler/icons-react'
import { DataState, SectionCard, SgthTable } from '@/components/ui'
import { usePostulantes } from '../hooks/useConvocatoria'
import type { Postulante } from '../services/convocatoriaService'
import { CalificarPostulanteModal } from './CalificarPostulanteModal'
import { InscribirPostulanteModal } from './InscribirPostulanteModal'
import { PerfilPostulanteDrawer } from './PerfilPostulanteDrawer'
import { columnasPostulantes } from './postulantes.columns'

interface Props {
  convocatoriaId:     number
  estadoConvocatoria: string
  gestiona:           boolean
  califica:           boolean
}

/** La pestaña «Candidatos» del detalle: la lista, la inscripción, la calificación y el perfil. */
export function TabCandidatos({ convocatoriaId, estadoConvocatoria, gestiona, califica }: Props) {
  const { data: postulantes = [], isLoading, error, refetch } = usePostulantes(convocatoriaId)
  const [inscribirAbierto, inscribir] = useDisclosure(false)
  const [aCalificar, setACalificar] = useState<Postulante | null>(null)
  const [perfilId, setPerfilId] = useState<number | null>(null)

  const columns = columnasPostulantes({
    califica,
    onCalificar: setACalificar,
    onVerPerfil: (p) => setPerfilId(p.id),
  })

  return (
    <>
      <SectionCard
        title="Candidatos inscritos"
        actions={gestiona && estadoConvocatoria === 'publicada' && (
          <Button size="xs" leftSection={<IconPlus size={13} />} onClick={inscribir.open}>
            Inscribir candidato
          </Button>
        )}
      >
        <DataState
          loading={isLoading}
          error={error}
          errorTitle="No se pudieron cargar los candidatos"
          onRetry={refetch}
          empty={!postulantes.length}
          emptyProps={{
            icon: IconUsers,
            title: 'Sin candidatos',
            description: estadoConvocatoria === 'borrador'
              ? 'Publique la convocatoria para empezar a inscribir candidatos.'
              : 'No hay candidatos inscritos aún.',
          }}
          skeletonRows={3}
        >
          <SgthTable records={postulantes} columns={columns} minHeight={150} />
        </DataState>
      </SectionCard>

      <InscribirPostulanteModal opened={inscribirAbierto} onClose={inscribir.close} convocatoriaId={convocatoriaId} />

      <PerfilPostulanteDrawer
        convocatoriaId={convocatoriaId}
        postulanteId={perfilId}
        onClose={() => setPerfilId(null)}
        puedeGestionar={gestiona}
      />

      <CalificarPostulanteModal
        opened={aCalificar !== null}
        onClose={() => setACalificar(null)}
        postulante={aCalificar}
        convocatoriaId={convocatoriaId}
      />
    </>
  )
}
