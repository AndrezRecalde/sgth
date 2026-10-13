'use client'

import { useState } from 'react'
import { SectionCard, SgthTable } from '@/components/ui'
import { useAuth } from '@/hooks/useAuth'
import { useDocumentoBaja } from '../hooks/useBajas'
import type { BajaBien } from '../services/tiposBaja'
import { BajaModales, type AccionBaja } from './BajaModales'
import { accionesBaja, columnasBajasDelBien } from './bajas.columns'

/**
 * Las bajas del equipo, también las rechazadas: un informe que no prosperó
 * dice que el equipo ya dio problemas. Se proponen desde la cabecera de la ficha.
 */
export function BienBajas({ bajas }: { bajas: BajaBien[] }) {
  const { hasPermiso } = useAuth()
  const documento = useDocumentoBaja()
  const [accion, setAccion] = useState<AccionBaja>(null)

  if (bajas.length === 0) return null

  const accionesDe = (b: BajaBien) => accionesBaja(b, {
    puedeResolver: hasPermiso('dar-baja-bien'),
    abrir: setAccion,
    documento: (x, que) => documento.mutate({ id: x.id, numero: x.numero, que }),
  })

  return (
    <SectionCard title="Bajas">
      <SgthTable records={bajas} columns={columnasBajasDelBien(accionesDe)} minHeight={100} />
      <BajaModales accion={accion} onClose={() => setAccion(null)} />
    </SectionCard>
  )
}
