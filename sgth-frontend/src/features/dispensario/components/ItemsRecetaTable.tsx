'use client'

import { useState } from 'react'
import { useDisclosure } from '@mantine/hooks'
import { SgthTable } from '@/components/ui'
import { EditarItemRecetaModal } from './EditarItemRecetaModal'
import { getItemsRecetaEditablesColumns } from './itemsRecetaEditables.columns'
import { useAccionesItem } from '../hooks/useReceta'
import { useAuthStore } from '@/store/auth.store'
import type { ConsultaMedica } from '../services/consultaMedicaService'
import type { ItemReceta, RecetaMedica } from '../services/recetaService'

/**
 * Estados en los que la receta todavía se puede retocar, los mismos que acepta
 * el servidor. `externa` es terminal para la farmacia, no para el médico: que
 * nada se entregue aquí no impide corregir la dosis de lo recetado.
 */
const EDITABLES = ['pendiente', 'externa']

interface Props {
  receta:   RecetaMedica
  consulta: ConsultaMedica
}

/** Los medicamentos de una receta, con su modal de edición. */
export function ItemsRecetaTable({ receta, consulta }: Props) {
  const [itemSel, setItemSel] = useState<ItemReceta | null>(null)
  const [editOpened,
    { open: abrirEdit, close: cerrarEdit }] = useDisclosure(false)
  const { quitarItem } = useAccionesItem(consulta.id)
  const { usuario } = useAuthStore()

  // Retocar la receta es cosa de quien la firmó, y el servidor lo rechaza con
  // un 403 aunque se llame a la API a mano. Aquí solo decide si se ofrecen los
  // botones: enseñar un «Editar» que siempre va a fallar es peor que no
  // enseñarlo.
  const puedeRetocar = EDITABLES.includes(receta.estado)
    && (usuario?.id === undefined || consulta.medico_id === usuario.id)

  return (
    <>
      <SgthTable
        records={receta.items}
        columns={getItemsRecetaEditablesColumns({
          puedeRetocar,
          onEditar: (item) => { setItemSel(item); abrirEdit() },
          onQuitar: (item) => quitarItem.mutate({
            recetaId: receta.id,
            itemId:   item.id!,
          }),
        })}
        minHeight={60}
      />
      <EditarItemRecetaModal
        opened={editOpened}
        onClose={() => { setItemSel(null); cerrarEdit() }}
        item={itemSel}
        recetaId={receta.id}
        consultaId={consulta.id}
      />
    </>
  )
}
