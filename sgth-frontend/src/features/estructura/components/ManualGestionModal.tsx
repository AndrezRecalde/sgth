'use client'

import { useState } from 'react'
import { Select, Stack, Text } from '@mantine/core'
import { ModalFooter, SgthModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useManualDeGestionPdf } from '../hooks/useDescripcionPuesto'
import { useTodasUnidades } from '../hooks/useUnidades'

interface Props {
  opened:  boolean
  onClose: () => void
  /** La gestión del filtro de la tabla, si hay una: se propone. */
  unidadId?: number | null
}

/**
 * Imprimir el Manual de Puestos de una gestión (docs/diseno-manual-de-
 * puestos.md, P5, PR 14), como la distribución del manual 2018-2019: portada,
 * distribución de los puestos de plantilla por subproceso y la ficha de cada
 * uno.
 */
export function ManualGestionModal({ opened, onClose, unidadId = null }: Props) {
  return (
    <SgthModal opened={opened} onClose={onClose} title="Imprimir el manual de puestos">
      {/* Montado solo abierto: cada vez propone la gestión del filtro. */}
      {opened && <Contenido onClose={onClose} unidadId={unidadId} />}
    </SgthModal>
  )
}

function Contenido({ onClose, unidadId }: { onClose: () => void; unidadId: number | null }) {
  const contained = useContainedInput()
  const manual = useManualDeGestionPdf()
  const { data: gestiones = [], isLoading } = useTodasUnidades({ nivel: 2 })
  const [gestion, setGestion] = useState<string | null>(unidadId ? String(unidadId) : null)

  const imprimir = async () => {
    const elegida = gestiones.find((g) => String(g.id) === gestion)
    if (!elegida) return
    if (await manual.abrir(elegida.id, elegida.nombre ?? `gestion-${elegida.id}`)) onClose()
  }

  return (
    <Stack gap="md">
      <Text size="sm" c="dimmed">
        Un PDF con la portada, la distribución de los puestos de plantilla por subproceso y la ficha
        «Descripción y perfil del puesto» de cada uno. Una gestión con muchos puestos tarda unos segundos.
      </Text>

      <Select
        label="Gestión"
        placeholder={isLoading ? 'Cargando…' : 'Elija la gestión'}
        data={gestiones.map((g) => ({ value: String(g.id), label: g.nombre ?? `Unidad ${g.id}` }))}
        searchable
        required
        {...contained}
        value={gestion}
        onChange={setGestion}
      />

      <ModalFooter
        onCancel={onClose}
        onSubmit={imprimir}
        submitLabel="Abrir el PDF"
        submitting={manual.abriendo}
        submitDisabled={gestion === null}
      />
    </Stack>
  )
}
