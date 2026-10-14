'use client'

import { useState } from 'react'
import { Pill, Stack, Text } from '@mantine/core'
import { FormModal } from '@/components/ui'
import { useCampaniaMutations } from '../hooks/useCampanias'
import { BuscarEquipoSelect } from './BuscarEquipoSelect'

type Elegido = { id: number; texto: string }

interface Props {
  opened: boolean
  campaniaId: number
  onClose: () => void
}

/**
 * Sumar equipos a la campaña, de cualquier lugar: se buscan uno a uno y se
 * agregan juntos. Solo equipos principales: los componentes van con su equipo.
 */
export function AgregarEquiposCampaniaModal({ opened, ...props }: Props) {
  return opened ? <Formulario {...props} /> : null
}

function Formulario({ campaniaId, onClose }: Omit<Props, 'opened'>) {
  const { agregar } = useCampaniaMutations(campaniaId)
  const [elegidos, setElegidos] = useState<Elegido[]>([])
  // Se vuelve a montar el buscador para que quede vacío después de elegir.
  const [vuelta, setVuelta] = useState(0)

  const sumar = (id: number | null, texto?: string) => {
    if (id && !elegidos.some((e) => e.id === id)) setElegidos([...elegidos, { id, texto: texto ?? String(id) }])
    setVuelta((n) => n + 1)
  }

  const enviar = (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault()
    agregar.mutateAsync(elegidos.map((x) => x.id)).then(onClose).catch(() => undefined)
  }

  return (
    <FormModal opened onClose={onClose} title="Agregar equipos a la campaña" onSubmit={enviar}
      submitLabel="Agregar" submitting={agregar.isPending} submitDisabled={!elegidos.length}>
      <Stack gap="md">
        <BuscarEquipoSelect key={vuelta} label="Equipo" value={null} placeholder="Código, serie o modelo"
          onChange={sumar} />
        {elegidos.length ? (
          <Pill.Group>
            {elegidos.map((x) => (
              <Pill key={x.id} withRemoveButton onRemove={() => setElegidos(elegidos.filter((y) => y.id !== x.id))}>{x.texto}</Pill>
            ))}
          </Pill.Group>
        ) : (
          <Text size="sm" c="dimmed">Busque los equipos que quiere sumar; se agregan todos juntos.</Text>
        )}
      </Stack>
    </FormModal>
  )
}
