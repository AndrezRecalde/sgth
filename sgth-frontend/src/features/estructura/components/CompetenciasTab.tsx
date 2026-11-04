'use client'

import { useState } from 'react'
import { Button, Select, Stack, Text, TextInput } from '@mantine/core'
import { useDebouncedValue } from '@mantine/hooks'
import { IconPlus, IconStars } from '@tabler/icons-react'
import { confirmar, DataState, SgthTable, Toolbar } from '@/components/ui'
import { useAuth } from '@/hooks/useAuth'
import { useContainedInput } from '@/hooks/useContainedInput'
import type { Competencia } from '@/types/api'
import { TIPOS_COMPETENCIA } from '../constants/perfilPuesto'
import { useCompetenciaMutations, useCompetencias } from '../hooks/useCompetencias'
import { getCompetenciaColumns } from './competencia.columns'
import { CompetenciaModal } from './CompetenciaModal'

const RETARDO_BUSQUEDA_MS = 300

/** Decía «Los 1 puestos» y «Los 0 puestos». */
function conservan(puestos: number): string {
  if (puestos === 0) return 'Ningún puesto la exige todavía.'
  return puestos === 1 ? 'El puesto que ya la exige la conserva.' : `Los ${puestos} puestos que ya la exigen la conservan.`
}

/**
 * El diccionario de competencias del Ministerio, de donde salen las del perfil
 * de cada puesto. Se cargó desde el libro FOR-02 de Talento Humano, que trae
 * 34 técnicas y 8 conductuales; las que falten se agregan aquí.
 */
export function CompetenciasTab() {
  const { hasPermiso } = useAuth()
  const puedeGestionar = hasPermiso('gestionar-puestos')
  const filtro = useContainedInput('sm')

  const [tipo, setTipo] = useState<string | null>(null)
  const [buscar, setBuscar] = useState('')
  const [buscarConRetardo] = useDebouncedValue(buscar, RETARDO_BUSQUEDA_MS)
  // La competencia se conserva al cerrar, para que el título no cambie
  // durante la animación de salida.
  const [modal, setModal] = useState<{ abierto: boolean; competencia: Competencia | null }>(
    { abierto: false, competencia: null },
  )
  const abrir = (competencia: Competencia | null) => setModal({ abierto: true, competencia })

  const tipoElegido = TIPOS_COMPETENCIA.find(t => t.value === tipo)?.value
  const { data: competencias = [], isLoading, error } = useCompetencias({
    tipo:   tipoElegido,
    buscar: buscarConRetardo || undefined,
  })
  const { editar, eliminar } = useCompetenciaMutations()

  const columns = getCompetenciaColumns({
    puedeGestionar,
    onEditar: abrir,
    onRetirar: (c) => c.activo
      ? confirmar({
          title:   'Retirar competencia',
          message: <><b>{c.nombre}</b> dejará de ofrecerse para los puestos. {conservan(c.puestos_count ?? 0)}</>,
          onConfirm: () => editar.mutate({ id: c.id, data: { activo: false } }),
        })
      : editar.mutate({ id: c.id, data: { activo: true } }),
    onEliminar: (c) => confirmar({
      title:   'Eliminar competencia',
      message: <>Se eliminará <b>{c.nombre}</b> del diccionario. No se puede deshacer.</>,
      destructiva: true,
      onConfirm: () => eliminar.mutate(c.id),
    }),
  })

  return (
    <Stack gap="md">
      <Text size="sm" c="dimmed">
        Las competencias del perfil de cada puesto salen de aquí. Cada nivel
        tiene su número de comportamiento, el mismo que se anota en la
        asignación de responsabilidades de la evaluación del desempeño.
      </Text>

      <Toolbar
        actions={puedeGestionar && (
          <Button leftSection={<IconPlus size={16} />} variant="light" onClick={() => abrir(null)}>
            Nueva competencia
          </Button>
        )}
      >
        <Select
          label="Tipo"
          placeholder="Todas"
          data={TIPOS_COMPETENCIA.map(({ value, plural }) => ({ value, label: plural }))}
          clearable
          {...filtro}
          value={tipo}
          onChange={setTipo}
          style={{ minWidth: 240 }}
        />
        <TextInput
          label="Competencia"
          placeholder="Buscar por nombre…"
          {...filtro}
          value={buscar}
          onChange={(e) => setBuscar(e.currentTarget.value)}
          style={{ minWidth: 260 }}
        />
      </Toolbar>

      <DataState
        loading={isLoading}
        error={error}
        empty={!competencias.length}
        emptyProps={{
          icon: IconStars,
          title: 'Sin competencias',
          description: buscar || tipo
            ? 'Ninguna competencia coincide con el filtro.'
            : 'El diccionario está vacío.',
        }}
      >
        <SgthTable records={competencias} columns={columns} minHeight={200} />
      </DataState>

      {puedeGestionar && (
        <CompetenciaModal
          opened={modal.abierto}
          onClose={() => setModal(m => ({ ...m, abierto: false }))}
          competencia={modal.competencia}
        />
      )}
    </Stack>
  )
}
