'use client'

import { useState } from 'react'
import { PAGINACION_ES, SgthDrawer, SgthTable } from '@/components/ui'
import { useAuth } from '@/hooks/useAuth'
import { dineroConMiles } from '@/lib/dinero'
import { formatFecha } from '@/lib/fecha'
import type { MasaSalarial } from '@/types/api'
import { SUMA, vinculosDeLaSeleccion, type SeleccionMasa } from '../utils/masaSalarial'
import { columnasSinVinculo, columnasSubrogacionMasa, columnasVinculoMasa } from './masaSalarial.columns'

/** Lo que muestra el cajón: los vínculos de una fila, o una de las listas de aparte. */
export type CajonMasa =
  | { tipo: 'vinculos'; seleccion: SeleccionMasa }
  | { tipo: 'subrogaciones' }
  | { tipo: 'sin_vinculo' }

interface Props {
  cajon:   CajonMasa | null
  datos?:  MasaSalarial
  onClose: () => void
}

const POR_PAGINA = 15

const plural = (n: number, una: string, varias: string) => `${n} ${n === 1 ? una : varias}`

/** El título y la cifra que lo resume, con la fecha de corte. */
function encabezado(cajon: CajonMasa, datos: MasaSalarial): { titulo: string; resumen: string } {
  const al = `al ${formatFecha(datos.corte)}`

  if (cajon.tipo === 'subrogaciones') {
    const { casos, mensual } = datos.aparte.subrogaciones
    return { titulo: 'Subrogaciones y encargos', resumen: `${plural(casos, 'caso', 'casos')} · ${dineroConMiles(mensual)} al mes de diferencia, ${al}` }
  }

  if (cajon.tipo === 'sin_vinculo') {
    const n = datos.por_completar.servidores_sin_vinculo?.length ?? 0
    return { titulo: 'Servidores activos sin vínculo vigente', resumen: `${plural(n, 'servidor', 'servidores')}, ${al}` }
  }

  const lista = vinculosDeLaSeleccion(datos.detalle, cajon.seleccion)
  const total = lista.reduce((s, v) => s + (v.remuneracion ?? 0), 0)
  const suman = lista.every((v) => v.situacion === SUMA)

  return {
    titulo:  cajon.seleccion.titulo,
    resumen: `${plural(lista.length, 'vínculo', 'vínculos')} · ${dineroConMiles(total)} al mes${suman ? '' : ' que no suman'}, ${al}`,
  }
}

/**
 * El detalle detrás de una cifra. Los vínculos ya llegaron con la respuesta, y
 * aquí solo se paginan: una fila puede tener cientos.
 */
export function MasaSalarialCajon({ cajon, datos, onClose }: Props) {
  const { titulo, resumen } = cajon && datos ? encabezado(cajon, datos) : { titulo: '', resumen: '' }

  return (
    <SgthDrawer opened={cajon != null} onClose={onClose} title={titulo} description={resumen} ancho="lg">
      {cajon && datos && <Contenido key={JSON.stringify(cajon)} cajon={cajon} datos={datos} />}
    </SgthDrawer>
  )
}

function Contenido({ cajon, datos }: { cajon: CajonMasa; datos: MasaSalarial }) {
  const { hasPermiso } = useAuth()
  // El nombre lleva al expediente solo si quien mira lo puede abrir.
  const enlace = hasPermiso('ver-expediente-todos')
  const [page, setPage] = useState(1)
  const pagina = <T,>(lista: T[]) => lista.slice((page - 1) * POR_PAGINA, page * POR_PAGINA)
  const paginacion = { ...PAGINACION_ES, recordsPerPage: POR_PAGINA, page, onPageChange: setPage, minHeight: 160 }

  if (cajon.tipo === 'subrogaciones') {
    const lista = datos.aparte.subrogaciones.detalle
    return (
      <SgthTable {...paginacion} records={pagina(lista)} totalRecords={lista.length}
        columns={columnasSubrogacionMasa(enlace)} idAccessor="subrogacion_id" />
    )
  }

  if (cajon.tipo === 'sin_vinculo') {
    const lista = datos.por_completar.servidores_sin_vinculo ?? []
    return <SgthTable {...paginacion} records={pagina(lista)} totalRecords={lista.length} columns={columnasSinVinculo(enlace)} />
  }

  const lista = vinculosDeLaSeleccion(datos.detalle, cajon.seleccion)
  return (
    <SgthTable {...paginacion} records={pagina(lista)} totalRecords={lista.length}
      columns={columnasVinculoMasa(enlace)} idAccessor="contrato_id" noRecordsText="Ningún vínculo en esta fila." />
  )
}
