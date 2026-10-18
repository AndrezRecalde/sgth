'use client'

import { useState } from 'react'
import { Button, Center, Pagination, Select, SimpleGrid, Tabs, TextInput } from '@mantine/core'
import { useDebouncedValue } from '@mantine/hooks'
import { IconArchive, IconNews } from '@tabler/icons-react'
import { DataState, PageHeader, PageShell, Toolbar } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { OPCIONES_TIPO } from '@/features/comunicados/constants/comunicados'
import { POR_PAGINA_MIS_COMUNICADOS, useMisComunicados } from '@/features/comunicados/hooks/useMisComunicados'
import type { FiltroLectura, FiltrosMisComunicados, TipoComunicado } from '@/features/comunicados/services/tipos'
import { TarjetaComunicado } from './TarjetaComunicado'

const RETARDO_BUSQUEDA_MS = 300

const OPCIONES_LECTURA: { value: FiltroLectura; label: string }[] = [
  { value: 'sin_leer', label: 'Sin leer' },
  { value: 'por_confirmar', label: 'Por confirmar' },
]

const esTipo = (v: string | null): v is TipoComunicado => OPCIONES_TIPO.some((o) => o.value === v)
const esLectura = (v: string | null): v is FiltroLectura => OPCIONES_LECTURA.some((o) => o.value === v)

/**
 * «Noticias y comunicados» del Portal (docs/diseno-noticias-comunicados.md,
 * 5.2): lo vigente, con las fijadas primero, y en el archivo lo que venció.
 * Abrir una la deja leída.
 */
export function MisComunicadosView({ lecturaInicial }: { lecturaInicial?: FiltroLectura }) {
  const compacto = useContainedInput('sm')
  const [archivo, setArchivo] = useState(false)
  const [page, setPage] = useState(1)
  const [tipo, setTipo] = useState<TipoComunicado | undefined>()
  const [lectura, setLectura] = useState<FiltroLectura | undefined>(lecturaInicial)
  const [busqueda, setBusqueda] = useState('')
  const [buscar] = useDebouncedValue(busqueda.trim(), RETARDO_BUSQUEDA_MS)

  // Lo vencido ya no se confirma: en el archivo, «por confirmar» no tiene sentido.
  const lecturaVigente = archivo && lectura === 'por_confirmar' ? undefined : lectura
  const filtros: FiltrosMisComunicados = {
    archivo, tipo, lectura: lecturaVigente, ...(buscar.length >= 2 ? { buscar } : {}),
  }
  const { data, isLoading, error, refetch } = useMisComunicados(filtros, page)
  const comunicados = data?.data ?? []
  const paginas = Math.ceil((data?.total ?? 0) / POR_PAGINA_MIS_COMUNICADOS)

  const hayFiltros = !!tipo || !!lecturaVigente || busqueda.trim() !== ''
  const limpiar = () => { setTipo(undefined); setLectura(undefined); setBusqueda(''); setPage(1) }

  return (
    <PageShell>
      <PageHeader title="Noticias y comunicados" description="Lo que Talento Humano publica para usted" />

      <Tabs value={archivo ? 'archivo' : 'vigentes'} onChange={(v) => { setArchivo(v === 'archivo'); setPage(1) }}>
        <Tabs.List>
          <Tabs.Tab value="vigentes" leftSection={<IconNews size={16} />}>Vigentes</Tabs.Tab>
          <Tabs.Tab value="archivo" leftSection={<IconArchive size={16} />}>Archivo</Tabs.Tab>
        </Tabs.List>
      </Tabs>

      <Toolbar actions={hayFiltros ? <Button variant="subtle" onClick={limpiar}>Quitar los filtros</Button> : undefined}>
        <TextInput
          label="Buscar"
          placeholder="Asunto, título o n.º de documento"
          {...compacto}
          value={busqueda}
          onChange={(e) => { setBusqueda(e.currentTarget.value); setPage(1) }}
        />
        <Select
          label="Tipo"
          placeholder="Todos"
          data={OPCIONES_TIPO.map(({ value, label }) => ({ value, label }))}
          clearable
          {...compacto}
          value={tipo ?? null}
          onChange={(v) => { setTipo(esTipo(v) ? v : undefined); setPage(1) }}
          style={{ minWidth: 160 }}
        />
        <Select
          label="Mostrar"
          placeholder="Todas"
          data={archivo ? OPCIONES_LECTURA.filter((o) => o.value !== 'por_confirmar') : OPCIONES_LECTURA}
          clearable
          {...compacto}
          value={lecturaVigente ?? null}
          onChange={(v) => { setLectura(esLectura(v) ? v : undefined); setPage(1) }}
          style={{ minWidth: 170 }}
        />
      </Toolbar>

      <DataState
        loading={isLoading}
        error={error}
        errorTitle="No se pudieron cargar las publicaciones"
        onRetry={refetch}
        empty={!comunicados.length}
        emptyProps={hayFiltros
          ? { icon: IconNews, title: 'Nada con estos filtros', description: 'Cambie o quite los filtros para ver las demás publicaciones.' }
          : archivo
            ? { icon: IconArchive, title: 'El archivo está vacío', description: 'Aquí quedan las publicaciones cuando pasa su fecha de fin.' }
            : { icon: IconNews, title: 'Sin publicaciones', description: 'Talento Humano no ha publicado novedades para usted. Aparecerán aquí cuando lo haga.' }}
        page={page}
      >
        <SimpleGrid cols={{ base: 1, sm: 2, lg: 3 }} spacing="md">
          {comunicados.map((c) => <TarjetaComunicado key={c.id} c={c} />)}
        </SimpleGrid>
        {paginas > 1 && (
          <Center mt="md">
            <Pagination total={paginas} value={page} onChange={setPage} />
          </Center>
        )}
      </DataState>
    </PageShell>
  )
}
