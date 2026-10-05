'use client'

import { Button, Group } from '@mantine/core'
import { IconEdit, IconWorldUpload } from '@tabler/icons-react'
import { useRouter } from 'next/navigation'
import { confirmar } from '@/components/ui'
import { ROUTES } from '@/config/routes'
import { usePostulantes, usePublicarConvocatoria } from '../hooks/useConvocatoria'
import type { Convocatoria } from '../services/convocatoriaService'
import { CerrarConvocatoriaAcciones } from './CerrarConvocatoriaAcciones'

/**
 * Lo que se puede hacer con la convocatoria según su estado (2026-10-05):
 * en borrador, editarla y publicarla; publicada, declararla desierta o
 * cancelarla. Solo para quien gestiona.
 *
 * Sin «Declarar ganador oficial» (2026-10-04): no miraba el dictamen ni creaba
 * el expediente. Cada ganador apto se incorpora desde el Ranking, y con el
 * último la convocatoria se finaliza sola.
 */
export function ConvocatoriaAcciones({ convocatoria }: { convocatoria: Convocatoria }) {
  const router = useRouter()
  const publicar = usePublicarConvocatoria()
  const { data: postulantes = [] } = usePostulantes(convocatoria.id)

  if (convocatoria.estado === 'publicada') {
    return (
      <Group gap="xs">
        <CerrarConvocatoriaAcciones
          convocatoriaId={convocatoria.id}
          codigo={convocatoria.codigo}
          hayAprobados={postulantes.some((p) => p.estado === 'aprobado')}
        />
      </Group>
    )
  }

  if (convocatoria.estado !== 'borrador') return null

  return (
    <Group gap="xs">
      <Button
        variant="default"
        leftSection={<IconEdit size={14} />}
        onClick={() => router.push(ROUTES.SGTH.CONVOCATORIA_EDITAR(convocatoria.id))}
      >
        Editar
      </Button>
      <Button
        leftSection={<IconWorldUpload size={14} />}
        loading={publicar.isPending}
        onClick={() => confirmar({
          title: 'Publicar convocatoria',
          message: 'La convocatoria quedará visible para los postulantes.',
          confirmLabel: 'Publicar',
          onConfirm: () => publicar.mutate(convocatoria.id),
        })}
      >
        Publicar convocatoria
      </Button>
    </Group>
  )
}
