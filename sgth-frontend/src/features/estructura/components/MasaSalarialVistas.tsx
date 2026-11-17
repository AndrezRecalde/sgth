'use client'

import { useMemo, useState } from 'react'
import { SegmentedControl } from '@mantine/core'
import { SectionCard, SgthTable } from '@/components/ui'
import type { MasaSalarial, UnidadMasaSalarial } from '@/types/api'
import {
  abiertasDeEntrada, esAgruparMasa, idsDelSubarbol, unidadesVisibles,
  type AgruparMasa, type SeleccionMasa,
} from '../utils/masaSalarial'
import { columnasCasoMasa, columnasUnidadMasa } from './masaSalarial.columns'

interface Props {
  datos:         MasaSalarial
  agrupar:       AgruparMasa
  onAgrupar:     (agrupar: AgruparMasa) => void
  onSeleccionar: (seleccion: SeleccionMasa) => void
}

const VISTAS: { value: AgruparMasa; label: string }[] = [
  { value: 'tipo_nombramiento', label: 'Tipo de nombramiento' },
  { value: 'regimen',           label: 'Régimen' },
  { value: 'unidad',            label: 'Unidad' },
]

/** La distribución de la masa, de una de tres maneras. Una fila abre sus vínculos. */
export function MasaSalarialVistas({ datos, agrupar, onAgrupar, onSeleccionar }: Props) {
  return (
    <SectionCard
      title="Distribución"
      description="Pulse una fila para ver los vínculos que la forman."
      actions={
        <SegmentedControl
          size="xs"
          value={agrupar}
          onChange={(v) => onAgrupar(esAgruparMasa(v) ? v : 'tipo_nombramiento')}
          data={VISTAS}
        />
      }
    >
      {agrupar === 'unidad' ? (
        // La clave reinicia lo abierto cuando cambian los filtros: es otro árbol.
        <ArbolDeUnidades key={datos.corte + JSON.stringify(datos.filtros)} unidades={datos.vistas.unidad} onSeleccionar={onSeleccionar} />
      ) : (
        <SgthTable
          records={datos.vistas[agrupar]}
          columns={columnasCasoMasa(agrupar === 'regimen' ? 'Régimen' : 'Tipo de nombramiento', datos.totales.general)}
          idAccessor="clave"
          minHeight={120}
          onRowClick={({ record }) => onSeleccionar({ por: agrupar, clave: record.clave, titulo: record.etiqueta })}
        />
      )}
    </SectionCard>
  )
}

interface ArbolProps {
  unidades:      UnidadMasaSalarial[]
  onSeleccionar: (seleccion: SeleccionMasa) => void
}

/** Las unidades con su subtotal; se abre de entrada la primera capa. */
function ArbolDeUnidades({ unidades, onSeleccionar }: ArbolProps) {
  const [abiertas, setAbiertas] = useState(() => abiertasDeEntrada(unidades))
  const visibles = useMemo(() => unidadesVisibles(unidades, abiertas), [unidades, abiertas])

  const alternar = (id: number) => setAbiertas((antes) => {
    const despues = new Set(antes)
    if (despues.has(id)) despues.delete(id)
    else despues.add(id)
    return despues
  })

  return (
    <SgthTable
      records={visibles}
      columns={columnasUnidadMasa({ abiertas, alternar })}
      minHeight={120}
      noRecordsText="Nadie suma en esta selección."
      onRowClick={({ record }) => onSeleccionar({ por: 'unidad', ids: idsDelSubarbol(unidades, record.id), titulo: record.nombre })}
    />
  )
}
