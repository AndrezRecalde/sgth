'use client'

import { useState } from 'react'
import { Button, Grid, Select, Stack, Text, TextInput } from '@mantine/core'
import { IconPlus } from '@tabler/icons-react'
import { SectionHeading, SgthTable } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useMarcas } from '@/features/inventario-ti/hooks/useCatalogosInventario'
import type { ModeloCompatibleFormData } from '../schemas/consumible.schema'
import { columnasModelosEnEdicion } from './modelos.columns'

/** Sin mayúsculas ni espacios, como lo compara el backend. */
const normalizar = (modelo: string) => modelo.replace(/\s+/g, '').toLowerCase()

interface Props {
  modelos: (ModeloCompatibleFormData & { id: string })[]
  onAgregar: (m: ModeloCompatibleFormData) => void
  onQuitar: (indice: number) => void
  error?: string
  /** El repuesto sirve a cualquier equipo: los modelos son opcionales. */
  opcional?: boolean
}

/**
 * Los modelos de equipo a los que sirve un consumible, dentro del formulario:
 * la marca del catálogo y el modelo tal como lo escribe la ficha del equipo.
 */
export function ModelosCompatiblesEditor({ modelos, onAgregar, onQuitar, error, opcional }: Props) {
  const contained = useContainedInput()
  const { data: marcas = [] } = useMarcas()
  const [marcaId, setMarcaId] = useState<string | null>(null)
  const [modelo, setModelo] = useState('')
  const nombreMarca = (id: number) => marcas.find((m) => m.id === id)?.nombre ?? '—'
  const repetido = !!marcaId && modelos.some((m) => String(m.marca_id) === marcaId && normalizar(m.modelo) === normalizar(modelo))

  const agregar = () => {
    if (!marcaId || !modelo.trim() || repetido) return
    onAgregar({ marca_id: Number(marcaId), modelo: modelo.trim() })
    setModelo('')
  }

  return (
    <Stack gap="xs">
      <SectionHeading title="Modelos compatibles" />
      <Text size="xs" c="dimmed">
        {opcional ? 'Opcional: un repuesto puede servir a cualquier equipo.' : 'Al entregar, solo se ofrece a los equipos de estos modelos.'}
      </Text>
      <Grid align="flex-end">
        <Grid.Col span={{ base: 12, sm: 4 }}>
          <Select label="Marca" placeholder="Seleccionar" searchable data={marcas.map((m) => ({ value: String(m.id), label: m.nombre }))}
            {...contained} value={marcaId} onChange={setMarcaId} />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 5 }}>
          <TextInput label="Modelo" placeholder="Ej.: LaserJet M551" {...contained} value={modelo}
            error={repetido ? 'Ya está en la lista' : undefined}
            onChange={(e) => setModelo(e.currentTarget.value)}
            onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); agregar() } }} />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 3 }}>
          <Button variant="light" fullWidth h={48} leftSection={<IconPlus size={16} />} disabled={!marcaId || !modelo.trim() || repetido} onClick={agregar}>
            Agregar
          </Button>
        </Grid.Col>
      </Grid>
      {modelos.length > 0 && (
        <SgthTable records={modelos} minHeight={0} noRecordsText=""
          columns={columnasModelosEnEdicion({ nombreMarca, onQuitar: (id) => onQuitar(modelos.findIndex((x) => x.id === id)) })} />
      )}
      {error && <Text size="xs" c="red">{error}</Text>}
    </Stack>
  )
}
