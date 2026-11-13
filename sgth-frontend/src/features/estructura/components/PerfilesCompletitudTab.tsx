'use client'

import { useMemo, useState } from 'react'
import { Alert, Select, SimpleGrid, Stack, Switch, TextInput } from '@mantine/core'
import { useDebouncedValue } from '@mantine/hooks'
import { useQueryClient } from '@tanstack/react-query'
import { IconAlertTriangle, IconChecklist, IconFileDescription, IconInfoCircle, IconListCheck } from '@tabler/icons-react'
import { DataState, SgthTable, StatCard, Toolbar } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { COMPLETITUD, useCompletitudPerfiles } from '../hooks/usePerfilesPuestos'
import type { CompletitudPerfiles, CompletitudPuesto, ParteDelPerfil } from '../services/perfilesPuestosService'
import { getCompletitudPerfilColumns } from './completitudPerfil.columns'
import { PuestoPerfilDrawer } from './PuestoPerfilDrawer'

const POR_PAGINA = 20

type Filtro = 'incompletos' | 'completos' | ParteDelPerfil

const FILTROS: { value: Filtro; label: string; cuenta: (r: CompletitudPerfiles['resumen']) => number }[] = [
  { value: 'incompletos',  label: 'Incompletos',      cuenta: (r) => r.incompletos },
  { value: 'mision',       label: 'Sin misión',       cuenta: (r) => r.sin_mision },
  { value: 'requisitos',   label: 'Sin instrucción',  cuenta: (r) => r.sin_requisitos },
  { value: 'actividades',  label: 'Sin actividades',  cuenta: (r) => r.sin_actividades },
  { value: 'competencias', label: 'Sin competencias', cuenta: (r) => r.sin_competencias },
  { value: 'completos',    label: 'Completos',        cuenta: (r) => r.completos },
]

const cumple = (p: CompletitudPuesto, filtro: Filtro | null) => {
  if (filtro === null) return true
  if (filtro === 'incompletos') return !p.completo
  if (filtro === 'completos') return p.completo
  return p.faltan.includes(filtro)
}

/** Sin tildes ni mayúsculas, para buscar «gestion» y hallar «Gestión». */
const plano = (texto: string) => texto.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()

/**
 * «Perfiles» en Puestos y cargos: el tablero de completitud
 * (docs/diseno-manual-de-puestos.md, P6, PR 15). Qué le falta al perfil de
 * cada puesto activo para quedar listo para evaluar. Primero los incompletos,
 * y entre ellos los ocupados, que son los que se evalúan.
 */
export function PerfilesCompletitudTab() {
  const qc = useQueryClient()
  const [filtro, setFiltro] = useState<Filtro | null>(null)
  const [soloOcupados, setSoloOcupados] = useState(false)
  const [buscar, setBuscar] = useState('')
  const [busqueda] = useDebouncedValue(buscar, 250)
  const [pagina, setPagina] = useState(1)
  const [abierto, setAbierto] = useState<CompletitudPuesto | null>(null)
  const campo = useContainedInput('sm')

  const { data, isLoading, error } = useCompletitudPerfiles()
  const resumen = data?.resumen

  const puestos = useMemo(() => {
    const texto = plano(busqueda.trim())
    return (data?.puestos ?? []).filter((p) =>
      cumple(p, filtro)
      && (!soloOcupados || p.ocupado)
      && (!texto || plano([p.cargo?.nombre, p.unidad_administrativa?.nombre].filter(Boolean).join(' ')).includes(texto)))
  }, [data, filtro, soloOcupados, busqueda])
  const visibles = puestos.slice((pagina - 1) * POR_PAGINA, pagina * POR_PAGINA)

  const columns = getCompletitudPerfilColumns({ alAbrir: setAbierto })

  // Lo que se completó en el cajón cambia el tablero.
  const cerrar = () => {
    setAbierto(null)
    qc.invalidateQueries({ queryKey: COMPLETITUD })
  }

  return (
    <Stack gap="md">
      <Alert variant="light" color="ocean" icon={<IconInfoCircle size={16} />}>
        Un perfil está completo cuando tiene misión, al menos una alternativa de instrucción, una actividad activa y
        una competencia: es lo que la evaluación del desempeño necesita. Abra un puesto para completarlo a mano o
        copiando el perfil de otro parecido.
      </Alert>

      <SimpleGrid cols={{ base: 1, xs: 3 }} spacing="md">
        <StatCard
          label="Completos"
          value={resumen ? `${resumen.completos} de ${resumen.total}` : '—'}
          icon={IconChecklist}
          hint="puestos activos con el perfil listo"
          loading={isLoading}
        />
        <StatCard
          label="Incompletos"
          value={resumen?.incompletos ?? '—'}
          icon={IconListCheck}
          hint="les falta al menos una parte"
          loading={isLoading}
        />
        <StatCard
          label="Ocupados sin completar"
          value={resumen?.ocupados_incompletos ?? '—'}
          icon={IconAlertTriangle}
          tone={resumen?.ocupados_incompletos ? 'warning' : undefined}
          hint="tienen a quién evaluar"
          loading={isLoading}
        />
      </SimpleGrid>

      <Toolbar>
        <Select
          label="Perfil"
          placeholder="Todos"
          data={FILTROS.map(({ value, label, cuenta }) => ({ value, label: `${label} (${resumen ? cuenta(resumen) : 0})` }))}
          clearable
          {...campo}
          value={filtro}
          onChange={(v) => { setFiltro(FILTROS.find((f) => f.value === v)?.value ?? null); setPagina(1) }}
          style={{ minWidth: 220 }}
        />
        <TextInput
          label="Puesto o unidad"
          placeholder="Buscar por cargo o unidad…"
          {...campo}
          value={buscar}
          onChange={(e) => { setBuscar(e.currentTarget.value); setPagina(1) }}
          style={{ minWidth: 280 }}
        />
        <Switch
          label="Solo ocupados"
          checked={soloOcupados}
          onChange={(e) => { setSoloOcupados(e.currentTarget.checked); setPagina(1) }}
        />
      </Toolbar>

      <DataState
        loading={isLoading}
        error={error}
        empty={!puestos.length}
        emptyProps={{
          icon: IconFileDescription,
          title: 'Sin puestos',
          description: data?.puestos.length ? 'Ningún puesto coincide con el filtro.' : 'No hay puestos activos.',
        }}
      >
        <SgthTable
          records={visibles}
          columns={columns}
          minHeight={200}
          totalRecords={puestos.length}
          recordsPerPage={POR_PAGINA}
          page={pagina}
          onPageChange={setPagina}
        />
      </DataState>

      <PuestoPerfilDrawer opened={abierto !== null} onClose={cerrar} puesto={abierto} />
    </Stack>
  )
}
