'use client'

import { useState } from 'react'
import { Stack } from '@mantine/core'
import { IconFileText, IconFileDownload } from '@tabler/icons-react'
import { DataState, SectionCard, SgthTable, type TableAction } from '@/components/ui'
import { useAuth } from '@/hooks/useAuth'
import { useHistorialBien } from '../hooks/useBienes'
import { useDocumentoCustodia } from '../hooks/useCustodia'
import type { Asignacion, BienInformatico } from '../services/tipos'
import { CustodiaVigente } from './CustodiaVigente'
import { DevolucionModal } from './DevolucionModal'
import { EntregaModal } from './EntregaModal'
import { MemorandoModal } from './MemorandoModal'
import { columnasCustodia, columnasMantenimiento } from './historial.columns'

/**
 * La custodia del equipo —la vigente, con sus acciones, y las anteriores— y
 * sus mantenimientos, del más reciente al más antiguo.
 */
export function BienHistorial({ bien }: { bien: BienInformatico }) {
  const { hasPermiso } = useAuth()
  const puedeGestionar = hasPermiso('gestionar-inventario-ti')
  const { data, isLoading, error } = useHistorialBien(bien.id)
  const documento = useDocumentoCustodia()

  const [entrega, setEntrega] = useState<{ abierto: boolean; traspasarDe: Asignacion | null }>({ abierto: false, traspasarDe: null })
  const [devolver, setDevolver] = useState<Asignacion | null>(null)
  const [memorando, setMemorando] = useState<Asignacion | null>(null)

  const vigente = data?.asignaciones.find((a) => a.vigente) ?? null
  const abrir = (a: Asignacion, que: 'acta' | 'memorando') => documento.mutate({ id: a.id, que })

  const accionesDe = (a: Asignacion): TableAction[] => [
    { label: 'Ver acta', icon: <IconFileText size={14} />, onClick: () => abrir(a, 'acta') },
    { label: 'Ver memorando', icon: <IconFileDownload size={14} />, hidden: !a.tiene_memorando_pdf, onClick: () => abrir(a, 'memorando') },
  ]

  return (
    <DataState loading={isLoading} error={error}>
      <Stack gap="lg">
        <CustodiaVigente
          bien={bien}
          vigente={vigente}
          puedeGestionar={puedeGestionar}
          onEntregar={() => setEntrega({ abierto: true, traspasarDe: null })}
          onTraspasar={(a) => setEntrega({ abierto: true, traspasarDe: a })}
          onDevolver={setDevolver}
          onMemorando={setMemorando}
          onActa={(a) => abrir(a, 'acta')}
        />
        <SectionCard title="Historial de custodia">
          <SgthTable
            records={data?.asignaciones ?? []}
            columns={columnasCustodia(accionesDe)}
            minHeight={120}
            noRecordsText="Nadie ha tenido este equipo en custodia."
          />
        </SectionCard>
        <SectionCard title="Mantenimientos">
          <SgthTable
            records={data?.mantenimientos ?? []}
            columns={columnasMantenimiento}
            minHeight={120}
            noRecordsText="El equipo no tiene mantenimientos registrados."
          />
        </SectionCard>
      </Stack>

      <EntregaModal
        opened={entrega.abierto}
        onClose={() => setEntrega({ abierto: false, traspasarDe: null })}
        bien={bien}
        traspasarDe={entrega.traspasarDe}
      />
      <DevolucionModal asignacion={devolver} onClose={() => setDevolver(null)} />
      <MemorandoModal asignacion={memorando} onClose={() => setMemorando(null)} />
    </DataState>
  )
}
