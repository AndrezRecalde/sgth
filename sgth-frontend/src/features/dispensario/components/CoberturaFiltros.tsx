'use client'

import { Button, Select, TextInput } from '@mantine/core'
import {
  IconFileSpreadsheet, IconSearch, IconStethoscope,
} from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useTodasUnidades } from '@/features/estructura/hooks/useUnidades'
import { Toolbar } from '@/components/ui'
import type { UnidadConRelaciones } from '@/types/api'
import { ESTADO_COBERTURA_FILTRO_OPTIONS } from '../services/coberturaCertificacionService'

interface Props {
  buscar:    string
  unidad:    string
  estado:    string
  /** Cuántas filas van marcadas: es el rótulo del botón de solicitar. */
  seleccionadas: number
  exportando: boolean
  puedeSolicitar: boolean
  /** Recibe el cambio ya envuelto: la vista vuelve a la página 1 y suelta la selección. */
  onBuscar:  (valor: string) => void
  onUnidad:  (valor: string) => void
  onEstado:  (valor: string) => void
  onExportar: () => void
  onSolicitar: () => void
}

/**
 * La barra del tablero de cobertura: tres filtros y las dos acciones del
 * conjunto.
 *
 * Sale de `CoberturaTab` porque es lo único que crecía ahí. La pestaña se
 * queda con el estado de los filtros, la consulta y la tabla.
 */
export function CoberturaFiltros({
  buscar, unidad, estado, seleccionadas, exportando, puedeSolicitar,
  onBuscar, onUnidad, onEstado, onExportar, onSolicitar,
}: Props) {
  const contained = useContainedInput('sm')
  const { data: unidades = [] } = useTodasUnidades()

  const unidadOptions = [
    { value: '', label: 'Todas las unidades' },
    ...((unidades ?? []) as UnidadConRelaciones[]).map(u => ({
      value: String(u.id),
      label: u.nombre ?? `Unidad ${u.id}`,
    })),
  ]

  return (
    <Toolbar
      actions={
        <>
          <Button
            variant="default"
            leftSection={<IconFileSpreadsheet size={16} />}
            loading={exportando}
            onClick={onExportar}
          >
            Exportar
          </Button>
          {puedeSolicitar && (
            <Button
              leftSection={<IconStethoscope size={16} />}
              disabled={seleccionadas === 0}
              onClick={onSolicitar}
            >
              Solicitar ({seleccionadas})
            </Button>
          )}
        </>
      }
    >
      <TextInput
        label="Buscar"
        placeholder="Nombre o cédula"
        leftSection={<IconSearch size={16} />}
        style={{ minWidth: 220 }}
        {...contained}
        value={buscar}
        onChange={(e) => onBuscar(e.currentTarget.value)}
      />
      <Select
        label="Unidad administrativa"
        placeholder="Todas las unidades"
        data={unidadOptions}
        searchable
        style={{ minWidth: 240 }}
        {...contained}
        value={unidad}
        onChange={(v) => onUnidad(v ?? '')}
      />
      <Select
        label="Estado de cobertura"
        placeholder="Toda la plantilla"
        data={ESTADO_COBERTURA_FILTRO_OPTIONS}
        style={{ minWidth: 190 }}
        {...contained}
        value={estado}
        onChange={(v) => onEstado(v ?? '')}
      />
    </Toolbar>
  )
}
