'use client'

import { useMemo, useState } from 'react'
import { Alert, Button, Select, SimpleGrid, Stack, TextInput } from '@mantine/core'
import { useDebouncedValue } from '@mantine/hooks'
import { IconAlertTriangle, IconCircleCheck, IconFileSpreadsheet, IconInfoCircle, IconSchool, IconTargetArrow } from '@tabler/icons-react'
import { useRouter } from 'next/navigation'
import { DataState, PageHeader, PageShell, SgthTable, StatCard, Toolbar } from '@/components/ui'
import { ROUTES } from '@/config/routes'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useTodasUnidades } from '@/features/estructura/hooks/useUnidades'
import { ESTADOS_INSTRUCCION } from '../constants/brechasDePerfil'
import { useBrechasDePerfil, useExcelBrechasDePerfil } from '../hooks/useBrechasDePerfil'
import type { BrechaDePerfil, EstadoInstruccion } from '../services/brechasDePerfilService'
import { BrechaDePerfilDrawer } from './BrechaDePerfilDrawer'
import { getBrechasDePerfilColumns } from './brechasDePerfil.columns'

const POR_PAGINA = 20

/** Sin tildes ni mayúsculas, para buscar «gomez» y hallar «Gómez». */
const plano = (texto: string) => texto.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()

/**
 * Las brechas de perfil (docs/diseno-manual-de-puestos.md, P7, PR 17): cada
 * servidor con contrato vigente frente al perfil de su puesto, para el plan de
 * capacitación. Es un informe: no avisa al nombrar ni al reclasificar (D9).
 */
export function BrechasDePerfilView() {
  const router = useRouter()
  const campo = useContainedInput('sm')
  const [unidad, setUnidad] = useState<string | null>(null)
  const [estado, setEstado] = useState<EstadoInstruccion | null>(null)
  const [buscar, setBuscar] = useState('')
  const [busqueda] = useDebouncedValue(buscar, 250)
  const [pagina, setPagina] = useState(1)
  const [abierta, setAbierta] = useState<BrechaDePerfil | null>(null)

  const unidadId = unidad ? Number(unidad) : null
  const { data, isLoading, error, refetch } = useBrechasDePerfil(unidadId)
  const { data: gestiones = [] } = useTodasUnidades({ nivel: 2 })
  const excel = useExcelBrechasDePerfil()
  const resumen = data?.resumen

  const filas = useMemo(() => {
    const texto = plano(busqueda.trim())
    return (data?.filas ?? []).filter((f) =>
      (estado === null || f.instruccion.estado === estado)
      && (!texto || plano([f.servidor.nombre, f.servidor.cedula, f.puesto.cargo, f.puesto.unidad].filter(Boolean).join(' ')).includes(texto)))
  }, [data, estado, busqueda])
  const visibles = filas.slice((pagina - 1) * POR_PAGINA, pagina * POR_PAGINA)

  const alVerExpediente = (b: BrechaDePerfil) => router.push(ROUTES.SGTH.EXPEDIENTE_SERVIDOR(b.servidor.id))
  const columns = getBrechasDePerfilColumns({ alVerDetalle: setAbierta, alVerExpediente })

  return (
    <PageShell>
      <PageHeader
        title="Brechas de perfil"
        description="Cada servidor frente al perfil de su puesto: la instrucción que tiene y la capacitación que ha recibido. Para el plan de capacitación."
        actions={
          <Button variant="default" leftSection={<IconFileSpreadsheet size={16} />} loading={excel.descargando}
            disabled={!data?.filas.length} onClick={() => excel.descargar(unidadId)}>
            Exportar a Excel
          </Button>
        }
      />

      <Stack gap="md">
        <Alert variant="light" color="ocean" icon={<IconInfoCircle size={16} />}>
          La instrucción compara el título validado más alto del historial académico con las alternativas del perfil:
          cumple con una que alcance. En capacitación, las temáticas del perfil son texto libre: se señalan los cursos
          validados que comparten palabras con ellas, para revisarlos. La experiencia no se compara.
        </Alert>

        <SimpleGrid cols={{ base: 1, xs: 2, md: 4 }} spacing="md">
          <StatCard label="Cumplen la instrucción" value={resumen ? `${resumen.cumple} de ${resumen.total}` : '—'}
            icon={IconCircleCheck} hint="servidores con contrato vigente" loading={isLoading} />
          <StatCard label="No la cumplen" value={resumen?.no_cumple ?? '—'} icon={IconAlertTriangle}
            tone={resumen?.no_cumple ? 'warning' : undefined} hint="ningún título validado alcanza" loading={isLoading} />
          <StatCard label="Por revisar" value={resumen?.por_revisar ?? '—'} icon={IconTargetArrow}
            hint="el puesto acepta estudios en curso" loading={isLoading} />
          <StatCard label="Sin cursos relacionados" value={resumen?.sin_capacitacion_relacionada ?? '—'} icon={IconSchool}
            hint="de quienes tienen temáticas en su perfil" loading={isLoading} />
        </SimpleGrid>

        <Toolbar>
          <Select
            label="Gestión"
            placeholder="Toda la institución"
            data={gestiones.map((g) => ({ value: String(g.id), label: g.nombre ?? `Unidad ${g.id}` }))}
            searchable
            clearable
            {...campo}
            value={unidad}
            onChange={(v) => { setUnidad(v); setPagina(1) }}
            style={{ minWidth: 280 }}
          />
          <Select
            label="Instrucción"
            placeholder="Todas"
            data={ESTADOS_INSTRUCCION.map(({ value, label }) => ({ value, label: `${label} (${resumen?.[value] ?? 0})` }))}
            clearable
            {...campo}
            value={estado}
            onChange={(v) => { setEstado(ESTADOS_INSTRUCCION.find((e) => e.value === v)?.value ?? null); setPagina(1) }}
            style={{ minWidth: 200 }}
          />
          <TextInput
            label="Servidor o puesto"
            placeholder="Buscar por nombre, cédula o puesto…"
            {...campo}
            value={buscar}
            onChange={(e) => { setBuscar(e.currentTarget.value); setPagina(1) }}
            style={{ minWidth: 260 }}
          />
        </Toolbar>

        <DataState
          loading={isLoading}
          error={error}
          errorTitle="No se pudo cargar el informe"
          onRetry={() => void refetch()}
          empty={!filas.length}
          emptyProps={{
            icon: IconTargetArrow,
            title: 'Sin servidores',
            description: data?.filas.length
              ? 'Ningún servidor coincide con el filtro.'
              : 'No hay servidores con contrato vigente en un puesto.',
          }}
        >
          <SgthTable
            records={visibles}
            columns={columns}
            minHeight={200}
            totalRecords={filas.length}
            recordsPerPage={POR_PAGINA}
            page={pagina}
            onPageChange={setPagina}
          />
        </DataState>
      </Stack>

      <BrechaDePerfilDrawer brecha={abierta} onClose={() => setAbierta(null)} alVerExpediente={alVerExpediente} />
    </PageShell>
  )
}
