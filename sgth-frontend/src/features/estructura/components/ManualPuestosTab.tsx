'use client'

import { useMemo, useState } from 'react'
import { Alert, SegmentedControl, Select, Stack, TextInput } from '@mantine/core'
import { useDebouncedValue } from '@mantine/hooks'
import { IconBook, IconInfoCircle } from '@tabler/icons-react'
import { DataState, SgthTable, Toolbar } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { ESTADOS_FICHA_MANUAL } from '../constants/manualPuestos'
import { useEquivalenciasManual, useFichasManual } from '../hooks/useManualPuestos'
import type { EstadoFichaManual } from '../services/manualPuestosService'
import { EquivalenciasManualPanel } from './EquivalenciasManualPanel'
import { FichaManualDrawer } from './FichaManualDrawer'
import { getFichasManualColumns } from './fichaManual.columns'

const POR_PAGINA = 20

/** Sin tildes ni mayúsculas, para buscar «gestion» y hallar «Gestión». */
const plano = (texto: string) => texto.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()

/**
 * «Manual» en Puestos y cargos: la revisión de las fichas del Manual de
 * Puestos 2018-2019 (docs/diseno-manual-de-puestos.md, P4, PR 12). El sistema
 * sugiere el puesto de cada ficha; Talento Humano confirma cada una y carga su
 * perfil. Nada se carga solo.
 */
export function ManualPuestosTab() {
  const [vista, setVista] = useState<'fichas' | 'unidades'>('fichas')
  const [estado, setEstado] = useState<EstadoFichaManual | null>(null)
  const [buscar, setBuscar] = useState('')
  const [busqueda] = useDebouncedValue(buscar, 250)
  const [pagina, setPagina] = useState(1)
  const [fichaId, setFichaId] = useState<number | null>(null)
  const filtro = useContainedInput('sm')

  const { data, isLoading, error } = useFichasManual()
  const { data: gestiones = [] } = useEquivalenciasManual()
  const faltan = gestiones.filter((g) => !g.unidad).length

  const fichas = useMemo(() => {
    const texto = plano(busqueda.trim())
    return (data?.fichas ?? []).filter((f) =>
      (estado === null || f.estado === estado)
      && (!texto || plano([f.denominacion, f.unidad, f.gestion, f.puesto?.cargo, f.archivo].filter(Boolean).join(' ')).includes(texto)))
  }, [data, estado, busqueda])
  const visibles = fichas.slice((pagina - 1) * POR_PAGINA, pagina * POR_PAGINA)

  const columns = getFichasManualColumns({ alRevisar: (f) => setFichaId(f.id) })

  return (
    <Stack gap="md">
      <Alert variant="light" color="ocean" icon={<IconInfoCircle size={16} />}>
        Las 179 fichas del Manual de Puestos 2018-2019, leídas de los PDF. Revise cada una y cárguela en su
        puesto: su misión, requisitos, actividades y competencias pasan a ser el perfil del puesto y
        reemplazan lo que tenía. El grado y la unidad siguen siendo los del puesto.
      </Alert>

      <SegmentedControl
        value={vista}
        onChange={(v) => setVista(v === 'unidades' ? 'unidades' : 'fichas')}
        data={[
          { value: 'fichas', label: `Fichas${data ? ` (${data.fichas.length})` : ''}` },
          { value: 'unidades', label: `Unidades del manual${faltan ? ` (${faltan} por elegir)` : ''}` },
        ]}
        style={{ alignSelf: 'flex-start' }}
      />

      {vista === 'unidades' ? <EquivalenciasManualPanel /> : (
        <>
          <Toolbar>
            <Select
              label="Estado"
              placeholder="Todos"
              data={ESTADOS_FICHA_MANUAL.map(({ value, label }) => ({ value, label: `${label} (${data?.resumen[value] ?? 0})` }))}
              clearable
              {...filtro}
              value={estado}
              onChange={(v) => { setEstado(ESTADOS_FICHA_MANUAL.find((e) => e.value === v)?.value ?? null); setPagina(1) }}
              style={{ minWidth: 220 }}
            />
            <TextInput
              label="Ficha o puesto"
              placeholder="Buscar por denominación, unidad o puesto…"
              {...filtro}
              value={buscar}
              onChange={(e) => { setBuscar(e.currentTarget.value); setPagina(1) }}
              style={{ minWidth: 300 }}
            />
          </Toolbar>

          <DataState
            loading={isLoading}
            error={error}
            empty={!fichas.length}
            emptyProps={{
              icon: IconBook,
              title: 'Sin fichas',
              description: data?.fichas.length
                ? 'Ninguna ficha coincide con el filtro.'
                : 'Todavía no se importó el Manual de Puestos. Lo hace TIC una sola vez, en el servidor.',
            }}
          >
            <SgthTable
              records={visibles}
              columns={columns}
              minHeight={200}
              totalRecords={fichas.length}
              recordsPerPage={POR_PAGINA}
              page={pagina}
              onPageChange={setPagina}
            />
          </DataState>
        </>
      )}

      <FichaManualDrawer fichaId={fichaId} onClose={() => setFichaId(null)} />
    </Stack>
  )
}
