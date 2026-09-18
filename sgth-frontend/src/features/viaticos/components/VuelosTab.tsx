'use client'

import { useState } from 'react'
import { IconPlaneOff } from '@tabler/icons-react'
import { confirmar, DataState, MotivoModal, SgthTable } from '@/components/ui'
import { useVuelosAutorizacion } from '../hooks/useViaticos'
import { useAccionesViatico } from '../hooks/useAccionesViatico'
import { useVuelosMutations } from '../hooks/useVuelosMutations'
import { getVuelosColumns } from './vuelos.columns'
import type { AutorizacionVuelo } from '@/types/api'

/*
| Los vuelos que esperan autorización, en la bandeja de Financiero.
|
| El tipo generado ya trae el viático y el tramo con sus relaciones: antes se
| declaraba otro a mano y se forzaba con una aserción en cada columna.
*/
export function VuelosTab() {
  const { data: vuelos = [], isLoading, error } = useVuelosAutorizacion()
  const { aprobar, rechazar } = useVuelosMutations()
  const puede = useAccionesViatico()
  const [rechazando, setRechazando] = useState<AutorizacionVuelo | null>(null)

  const codigo = (v: AutorizacionVuelo | null) => v?.viatico?.codigo_viatico ?? 'este viático'

  const columns = getVuelosColumns({
    // Pendiente y de un viático en el que no viaja quien mira la pantalla.
    decide: (v) => v.estado === 'pendiente' && puede.decidirVuelo(v.viatico?.servidor_id ?? null),
    onAprobar: (v) =>
      confirmar({
        title:        'Autorizar vuelo',
        message:      `Se autoriza el vuelo de ${codigo(v)}. No se puede deshacer.`,
        confirmLabel: 'Autorizar vuelo',
        onConfirm:    () => aprobar.mutate(Number(v.id)),
      }),
    onRechazar: setRechazando,
  })

  return (
    <>
      <DataState
        loading={isLoading}
        error={error}
        empty={vuelos.length === 0}
        emptyProps={{
          icon: IconPlaneOff,
          title: 'No hay vuelos por autorizar',
          description: 'Aparecen aquí cuando un servidor registra un tramo en avión.',
        }}
      >
        <SgthTable records={vuelos} columns={columns} minHeight={200} pinLastColumn />
      </DataState>

      <MotivoModal
        opened={rechazando !== null}
        onClose={() => setRechazando(null)}
        title="Rechazar vuelo"
        confirmLabel="Rechazar vuelo"
        destructiva
        cargando={rechazar.isPending}
        descripcion={`El servidor verá este motivo en ${codigo(rechazando)}.`}
        onConfirm={(observacion) =>
          rechazando &&
          rechazar.mutate(
            { id: Number(rechazando.id), observacion },
            { onSuccess: () => setRechazando(null) },
          )
        }
      />
    </>
  )
}
