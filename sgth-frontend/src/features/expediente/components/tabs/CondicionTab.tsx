'use client'

import { useState } from 'react'
import { Button, Stack } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconHeart, IconPlus } from '@tabler/icons-react'
import { DataState, SectionCard, SgthTable } from '@/components/ui'
import { useDiscapacidades } from '../../hooks/useDiscapacidades'
import { useEnfermedades } from '../../hooks/useEnfermedades'
import { useCondicionMutations } from '../../hooks/useCondicionMutations'
import {
  getDiscapacidadesColumns,
  getEnfermedadesColumns,
} from '../condicion.columns'
import { DiscapacidadModal } from '../DiscapacidadModal'
import { EnfermedadModal } from '../EnfermedadModal'
import type {
  DiscapacidadServidor,
  EnfermedadCatastroficaServidor,
} from '@/types/api'

interface Props { servidorId: number }

const botonRegistrar = (onClick: () => void) => (
  <Button size="xs" variant="light" leftSection={<IconPlus size={14} />} onClick={onClick}>
    Registrar
  </Button>
)

export function CondicionTab({ servidorId }: Props) {
  const [discOpened, { open: openDisc, close: closeDisc }] = useDisclosure(false)
  const [enfOpened, { open: openEnf, close: closeEnf }] = useDisclosure(false)
  const [editDisc, setEditDisc] = useState<DiscapacidadServidor | null>(null)
  const [editEnf, setEditEnf] = useState<EnfermedadCatastroficaServidor | null>(null)

  const { data: discapacidades = [], isLoading: cargandoDisc, error: errorDisc } =
    useDiscapacidades(servidorId)
  const { data: enfermedades = [], isLoading: cargandoEnf, error: errorEnf } =
    useEnfermedades(servidorId)
  const { eliminarDiscapacidad, eliminarEnfermedad } = useCondicionMutations(servidorId)

  return (
    <Stack gap="lg">
      <SectionCard title="Discapacidades" actions={botonRegistrar(openDisc)}>
        <DataState
          loading={cargandoDisc}
          error={errorDisc}
          empty={discapacidades.length === 0}
          skeletonRows={2}
          emptyProps={{
            icon: IconHeart,
            title: 'Sin discapacidades registradas',
            description: 'Registra el carnet del CONADIS y el porcentaje reconocido.',
          }}
        >
          <SgthTable
            records={discapacidades}
            columns={getDiscapacidadesColumns({
              onEdit: (item) => { setEditDisc(item); openDisc() },
              onDelete: (id) => eliminarDiscapacidad.mutate(id),
            })}
            minHeight={80}
          />
        </DataState>
      </SectionCard>

      <SectionCard title="Enfermedades catastróficas" actions={botonRegistrar(openEnf)}>
        <DataState
          loading={cargandoEnf}
          error={errorEnf}
          empty={enfermedades.length === 0}
          skeletonRows={2}
          emptyProps={{
            icon: IconHeart,
            title: 'Sin enfermedades registradas',
            description: 'Registra el diagnóstico y su código CIE-10.',
          }}
        >
          <SgthTable
            records={enfermedades}
            columns={getEnfermedadesColumns({
              onEdit: (item) => { setEditEnf(item); openEnf() },
              onDelete: (id) => eliminarEnfermedad.mutate(id),
            })}
            minHeight={80}
          />
        </DataState>
      </SectionCard>

      <DiscapacidadModal
        key={editDisc?.id ?? 'nueva'}
        opened={discOpened}
        onClose={() => { setEditDisc(null); closeDisc() }}
        servidorId={servidorId}
        initialValues={editDisc}
      />
      <EnfermedadModal
        key={editEnf?.id ?? 'nueva'}
        opened={enfOpened}
        onClose={() => { setEditEnf(null); closeEnf() }}
        servidorId={servidorId}
        initialValues={editEnf}
      />
    </Stack>
  )
}
