'use client'

import { Button, Group } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconEdit, IconTrash } from '@tabler/icons-react'
import { confirmar } from '@/components/ui'
import { ESTADOS_CALIFICABLES } from '../../constants/postulante'
import { useEliminarPostulante } from '../../hooks/usePostulanteMutations'
import type { Postulante } from '../../services/convocatoriaService'
import { nombreCandidato } from '../RankingCandidatoCard'
import { EditarPostulanteModal } from './EditarPostulanteModal'

interface Props {
  postulante: Postulante
  /** El concurso ya cerró: nada se corrige ni se elimina. */
  cerrado:    boolean
  onEliminado: () => void
}

/**
 * Corregir y eliminar desde el perfil (decisión de TH, 2026-10-05). Corregir,
 * hasta que se incorpore; eliminar, solo mientras el puntaje decide algo —el
 * backend aplica las mismas reglas—.
 */
export function PerfilAcciones({ postulante: p, cerrado, onEliminado }: Props) {
  const [editando, edicion] = useDisclosure(false)
  const eliminar = useEliminarPostulante(p.convocatoria_id)

  if (cerrado || p.estado === 'incorporado') return null
  const eliminable = ESTADOS_CALIFICABLES.includes(p.estado)

  return (
    <>
      <Group gap="xs">
        <Button size="xs" variant="default" leftSection={<IconEdit size={14} />} onClick={edicion.open}>
          Corregir datos
        </Button>
        {eliminable && (
          <Button size="xs" variant="default" color="red" leftSection={<IconTrash size={14} />}
            loading={eliminar.isPending}
            onClick={() => confirmar({
              title: 'Eliminar candidato',
              message: <>Se quitará la inscripción de <b>{nombreCandidato(p)}</b> de esta convocatoria.</>,
              destructiva: true,
              onConfirm: () => eliminar.mutate(p.id, { onSuccess: onEliminado }),
            })}>
            Eliminar
          </Button>
        )}
      </Group>
      <EditarPostulanteModal opened={editando} onClose={edicion.close} postulante={p} />
    </>
  )
}
