'use client'

import { useState } from 'react'
import { Button, Stack } from '@mantine/core'
import { IconBan, IconFileDownload, IconFileText, IconTool } from '@tabler/icons-react'
import { DataState, MotivoModal, SectionCard, SgthTable, type TableAction } from '@/components/ui'
import { useAuth } from '@/hooks/useAuth'
import { useHistorialBien } from '../hooks/useBienes'
import { useDocumentoCustodia } from '../hooks/useCustodia'
import { useMantenimientoMutations } from '../hooks/useMantenimientos'
import type { Asignacion, BienInformatico, Mantenimiento } from '../services/tipos'
import { BienBajas } from './BienBajas'
import { CustodiaVigente } from './CustodiaVigente'
import { DevolucionModal } from './DevolucionModal'
import { EntregaModal } from './EntregaModal'
import { MantenimientoModal } from './MantenimientoModal'
import { MemorandoModal } from './MemorandoModal'
import { columnasCustodia, columnasMantenimiento, columnasUbicaciones } from './historial.columns'

/**
 * La custodia del equipo —la vigente, con sus acciones, y las anteriores—,
 * sus mantenimientos, del más reciente al más antiguo, sus bajas y sus
 * cambios de ubicación.
 */
export function BienHistorial({ bien }: { bien: BienInformatico }) {
  const { hasPermiso } = useAuth()
  const puedeGestionar = hasPermiso('gestionar-inventario-ti')
  const puedeMantener = hasPermiso('registrar-mantenimiento') && bien.estado_operativo !== 'dado_de_baja'
  const { anular } = useMantenimientoMutations()
  const [mantenimiento, setMantenimiento] = useState(false)
  const [anularMant, setAnularMant] = useState<Mantenimiento | null>(null)
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
        <SectionCard
          title="Mantenimientos"
          actions={puedeMantener
            ? <Button variant="light" leftSection={<IconTool size={16} />} onClick={() => setMantenimiento(true)}>Registrar mantenimiento</Button>
            : undefined}
        >
          <SgthTable
            records={data?.mantenimientos ?? []}
            columns={columnasMantenimiento((m) => [
              {
                label: 'Anular', icon: <IconBan size={14} />, color: 'red',
                hidden: !hasPermiso('registrar-mantenimiento'), disabled: m.anulado, onClick: () => setAnularMant(m),
              },
            ])}
            minHeight={120}
            noRecordsText="El equipo no tiene mantenimientos registrados."
          />
        </SectionCard>
        <BienBajas bajas={data?.bajas ?? []} />
        <SectionCard title="Cambios de ubicación" description="Al editar la ficha, al entregar o traspasar la custodia y en las mudanzas.">
          <SgthTable
            records={data?.ubicaciones ?? []}
            columns={columnasUbicaciones}
            minHeight={100}
            noRecordsText="El equipo sigue donde se registró."
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
      <MantenimientoModal opened={mantenimiento} onClose={() => setMantenimiento(false)} bien={bien} />
      <MotivoModal
        opened={anularMant !== null}
        onClose={() => setAnularMant(null)}
        title="Anular mantenimiento"
        descripcion={<>Se anulará el mantenimiento «{anularMant?.descripcion}». Queda en el historial con su motivo.</>}
        confirmLabel="Anular"
        destructiva
        cargando={anular.isPending}
        onConfirm={(motivo) => anularMant && anular.mutate({ id: anularMant.id, motivo }, { onSuccess: () => setAnularMant(null) })}
      />
    </DataState>
  )
}
