'use client'

import { useState } from 'react'
import { Button, Text } from '@mantine/core'
import { IconEdit, IconPlus, IconTrash, IconUsersGroup } from '@tabler/icons-react'
import { DataState, SectionCard, SgthTable, confirmar } from '@/components/ui'
import { useConfiguracionMutations, useTecnicosHelpdesk } from '../hooks/useConfiguracionHelpdesk'
import { configuracionHelpdeskService } from '../services/configuracionService'
import type { TecnicoHelpdesk } from '../services/tipos'
import { columnasTecnicos } from './configuracion.columns'
import { TecnicoModal } from './TecnicoModal'

/**
 * Quién atiende y dónde (D6): la subunidad de TIC y el nivel de cada técnico,
 * para repartir y escalar. El permiso de atender lo da el rol en Usuarios.
 */
export function TecnicosPanel() {
  const { data = [], isLoading, error } = useTecnicosHelpdesk()
  const { eliminar } = useConfiguracionMutations('tecnico', configuracionHelpdeskService.tecnico)
  const [edicion, setEdicion] = useState<{ abierta: boolean; tecnico: TecnicoHelpdesk | null }>({ abierta: false, tecnico: null })

  return (
    <SectionCard
      title="Técnicos de la mesa de ayuda"
      description="Para figurar aquí, el usuario necesita antes un rol de TIC que atienda tickets."
      actions={<Button variant="light" leftSection={<IconPlus size={16} />} onClick={() => setEdicion({ abierta: true, tecnico: null })}>Agregar técnico</Button>}
    >
      <DataState loading={isLoading} error={error} empty={!data.length}
        emptyProps={{ icon: IconUsersGroup, title: 'Sin técnicos registrados', description: 'Indique la subunidad y el nivel de quienes atienden tickets.' }}>
        <SgthTable
          records={data}
          columns={columnasTecnicos((t) => [
            { label: 'Editar', icon: <IconEdit size={14} />, onClick: () => setEdicion({ abierta: true, tecnico: t }) },
            {
              label: 'Retirar', icon: <IconTrash size={14} />, color: 'red',
              onClick: () => confirmar({
                title: 'Retirar técnico',
                message: <><b>{t.nombre}</b> dejará de figurar en {t.unidad_tic ?? 'su subunidad'}. Sus tickets siguen a su nombre y el rol no cambia.</>,
                destructiva: true, onConfirm: () => eliminar.mutate(t.id),
              }),
            },
          ])}
        />
      </DataState>
      <Text size="xs" c="dimmed" mt="xs">Nivel 1 atiende primero; un ticket escalado o vencido pasa al 2 y luego al 3.</Text>
      <TecnicoModal opened={edicion.abierta} onClose={() => setEdicion({ abierta: false, tecnico: null })}
        tecnico={edicion.tecnico} registrados={data.map((t) => t.user_id)} />
    </SectionCard>
  )
}
