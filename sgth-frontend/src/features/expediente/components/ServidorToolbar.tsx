'use client'

import { useEffect, useState } from 'react'
import { Button, Collapse, Select, Stack, TextInput } from '@mantine/core'
import { useDebouncedValue, useDisclosure } from '@mantine/hooks'
import { IconAdjustmentsHorizontal } from '@tabler/icons-react'
import { Toolbar } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useTodasUnidades } from '@/features/estructura/hooks/useUnidades'
import { SITUACION_OPTIONS, type Situacion } from '../hooks/useFiltrosServidores'
import { TIPO_NOMBRAMIENTO_OPTIONS } from '../utils/tipoNombramientoOptions'
import type { UnidadConRelaciones } from '@/types/api'

const CONTRATO_ESTADO_OPTIONS = [
  { value: 'vigente',   label: 'Vigente' },
  { value: 'terminado', label: 'Terminado' },
  { value: 'cancelado', label: 'Cancelado' },
]

const ANIO_ACTUAL = new Date().getFullYear()
const ANIO_OPTIONS = Array.from({ length: ANIO_ACTUAL - 1979 }, (_, i) => {
  const anio = ANIO_ACTUAL - i
  return { value: String(anio), label: String(anio) }
})

interface Props {
  onSearch: (v: string) => void
  situacion: Situacion | null
  onSituacionChange: (v: Situacion | null) => void
  onContratoEstadoChange: (v: string | null) => void
  onUnidadChange: (v: number | null) => void
  onTipoNombramientoChange: (v: string | null) => void
  onAnioIngresoChange: (v: number | null) => void
  /** Cuántos filtros hay puestos dentro del desplegable. */
  secundariosActivos: number
}

/**
 * Los filtros del listado. El buscador y la situación —lo que se usa a
 * diario— quedan a la vista; los otros cuatro se despliegan.
 *
 * Antes eran siete campos en fila: en un teléfono empujaban la tabla fuera de
 * la primera pantalla, y tres de ellos preguntaban variantes de lo mismo.
 */
export function ServidorToolbar({
  onSearch, situacion, onSituacionChange, onContratoEstadoChange,
  onUnidadChange, onTipoNombramientoChange, onAnioIngresoChange,
  secundariosActivos,
}: Props) {
  const contained = useContainedInput('sm')
  const [abierto, { toggle }] = useDisclosure(false)

  const [localSearch, setLocalSearch] = useState('')
  const [debounced] = useDebouncedValue(localSearch, 400)

  const { data: unidadesRaw } = useTodasUnidades({ nivel: 2 })
  const unidades = (unidadesRaw ?? []) as UnidadConRelaciones[]
  const unidadOptions = unidades.map((u) => ({
    value: String(u.id),
    label: u.nombre ?? `Unidad ${u.id}`,
  }))

  useEffect(() => {
    onSearch(debounced)
  }, [debounced, onSearch])

  return (
    <Stack gap="sm">
      <Toolbar
        actions={
          <Button
            variant={secundariosActivos > 0 ? 'light' : 'default'}
            leftSection={<IconAdjustmentsHorizontal size={16} />}
            onClick={toggle}
          >
            {secundariosActivos > 0 ? `Filtros (${secundariosActivos})` : 'Filtros'}
          </Button>
        }
      >
        <TextInput
          label="Buscar servidor"
          placeholder="Nombre completo o cédula"
          value={localSearch}
          onChange={(e) => setLocalSearch(e.currentTarget.value)}
          {...contained}
          // El buscador se queda con el espacio que sobra: es el filtro que
          // más se usa y era el campo más estrecho de la barra.
          style={{ flex: 1, minWidth: 240 }}
        />
        <Select
          label="Situación"
          placeholder="Todos"
          data={SITUACION_OPTIONS}
          value={situacion}
          onChange={(v) => onSituacionChange(v as Situacion | null)}
          clearable
          {...contained}
          style={{ minWidth: 210 }}
        />
      </Toolbar>

      <Collapse expanded={abierto}>
        <Toolbar>
          <Select
            // La descripción de Mantine se dibuja encima del control y en el
            // patrón contained queda despegada del campo: va en la etiqueta.
            label="Unidad (incluye jefaturas)"
            placeholder="Todas"
            data={unidadOptions}
            searchable
            clearable
            onChange={(v) => onUnidadChange(v ? Number(v) : null)}
            {...contained}
            style={{ minWidth: 240 }}
          />
          <Select
            label="Tipo de nombramiento"
            placeholder="Todos"
            data={TIPO_NOMBRAMIENTO_OPTIONS}
            searchable
            clearable
            onChange={onTipoNombramientoChange}
            {...contained}
            style={{ minWidth: 240 }}
          />
          <Select
            label="Vínculo actual"
            placeholder="Todos"
            data={CONTRATO_ESTADO_OPTIONS}
            clearable
            onChange={onContratoEstadoChange}
            {...contained}
            style={{ minWidth: 180 }}
          />
          <Select
            label="Año de ingreso"
            placeholder="Todos"
            data={ANIO_OPTIONS}
            searchable
            clearable
            onChange={(v) => onAnioIngresoChange(v ? Number(v) : null)}
            {...contained}
            style={{ minWidth: 150 }}
          />
        </Toolbar>
      </Collapse>
    </Stack>
  )
}
