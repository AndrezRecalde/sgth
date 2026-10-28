'use client'

import { useState } from 'react'
import { Button, Select, Text, TextInput } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { useDebouncedValue } from '@mantine/hooks'
import { IconFileTypePdf, IconListCheck, IconPlus } from '@tabler/icons-react'
import {
  AvisoSinFicha, DataState, PAGINACION_ES, PageHeader, PageShell, SgthTable, Toolbar, confirmar, notificar,
} from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useTieneFicha } from '@/hooks/useTieneFicha'
import { guardarArchivo } from '@/lib/archivo'
import { formatFecha, fromDateValueOrNull, hoyIso } from '@/lib/fecha'
import { getApiErrorMessage } from '@/types/api'
import { CATEGORIAS_ACTIVIDAD } from '../constants/actividades'
import { useEliminarActividad, useMisActividades } from '../hooks/useMisActividades'
import {
  misActividadesService, type ActividadLaboral, type CategoriaActividad, type FiltrosActividades,
} from '../services/misActividadesService'
import { ActividadFormModal } from './ActividadFormModal'
import { getColumnasActividades } from './actividades.columns'

type Rango = [string | null, string | null]

/** Del primero al último día del mes en curso: el período que se informa. */
function mesEnCurso(): Rango {
  const hoy = hoyIso()
  const [anio, mes] = hoy.split('-').map(Number)
  const ultimo = new Date(anio, mes, 0).getDate()

  return [`${hoy.slice(0, 7)}-01`, `${hoy.slice(0, 7)}-${String(ultimo).padStart(2, '0')}`]
}

/**
 * «Mis actividades»: el servidor anota sus actividades laborales por fecha, las
 * filtra y exporta el informe en PDF con su firma y la de su jefe inmediato.
 * Son suyas: nadie más las ve ni las aprueba.
 */
export function MisActividadesView() {
  const tieneFicha = useTieneFicha()
  const compacto = useContainedInput('sm')
  const [rango, setRango] = useState<Rango>(mesEnCurso)
  const [categoria, setCategoria] = useState<CategoriaActividad | null>(null)
  const [buscar, setBuscar] = useState('')
  const [buscarDiferido] = useDebouncedValue(buscar.trim(), 300)
  const [page, setPage] = useState(1)
  const [formulario, setFormulario] = useState<{ abierto: boolean; actividad: ActividadLaboral | null }>(
    { abierto: false, actividad: null },
  )
  const [exportando, setExportando] = useState(false)

  const filtros: FiltrosActividades = {
    desde: rango[0] ?? undefined,
    hasta: rango[1] ?? undefined,
    categoria: categoria ?? undefined,
    buscar: buscarDiferido || undefined,
  }
  const { data, isLoading, error } = useMisActividades(filtros, page)
  const eliminar = useEliminarActividad()
  const filas = data?.data ?? []
  const total = data?.total ?? 0
  const hayFiltros = !!(categoria || buscarDiferido)

  const filtrar = <T,>(fijar: (v: T) => void) => (v: T) => { fijar(v); setPage(1) }

  const exportar = async () => {
    if (!filtros.desde || !filtros.hasta) return
    setExportando(true)
    const progreso = notificar.proceso('Exportando el informe', 'Generando el PDF…')
    try {
      const blob = await misActividadesService.pdf(filtros)
      guardarArchivo(blob, `actividades_${filtros.desde}_${filtros.hasta}.pdf`)
      progreso.exito('Informe descargado', `Del ${formatFecha(filtros.desde)} al ${formatFecha(filtros.hasta)}.`)
    } catch (e) {
      progreso.error('No se pudo exportar el informe', getApiErrorMessage(e))
    } finally {
      setExportando(false)
    }
  }

  const columnas = getColumnasActividades({
    onCorregir: (actividad) => setFormulario({ abierto: true, actividad }),
    onEliminar: (a) => confirmar({
      title: 'Eliminar la actividad',
      message: `Se elimina la actividad del ${formatFecha(a.fecha)}.`,
      confirmLabel: 'Eliminar',
      destructiva: true,
      onConfirm: () => eliminar.mutate(a.id),
    }),
  })

  // Sin ficha, anotar y exportar fallaban con 422 después de llenar el
  // formulario, y el vacío invitaba a «Anotar actividad» (PR 15).
  if (!tieneFicha) {
    return (
      <PageShell>
        <PageHeader
          title="Mis actividades"
          description="Lo que hace en su jornada, día por día. Se corrigen solo las del mes en curso."
        />
        <AvisoSinFicha motivo="Las actividades se anotan sobre el expediente del servidor, y el informe sale con sus datos." />
      </PageShell>
    )
  }

  return (
    <PageShell>
      <PageHeader
        title="Mis actividades"
        description="Lo que hace en su jornada, día por día. Se corrigen solo las del mes en curso."
        actions={
          <Button variant="light" leftSection={<IconPlus size={16} />} onClick={() => setFormulario({ abierto: true, actividad: null })}>
            Anotar actividad
          </Button>
        }
      />

      <Toolbar
        actions={
          <Button
            variant="default"
            leftSection={<IconFileTypePdf size={16} />}
            onClick={exportar}
            loading={exportando}
            disabled={!filtros.desde || !filtros.hasta}
            title={!filtros.desde || !filtros.hasta ? 'Elija el período del informe' : undefined}
          >
            Exportar PDF
          </Button>
        }
      >
        <DatePickerInput
          type="range"
          label="Período"
          placeholder="Todas las fechas"
          valueFormat="DD/MM/YYYY"
          allowSingleDateInRange
          clearable
          maxDate={hoyIso()}
          {...compacto}
          value={rango}
          onChange={filtrar((v: [Date | string | null, Date | string | null]) =>
            setRango([fromDateValueOrNull(v[0]), fromDateValueOrNull(v[1])]))}
        />
        <Select
          label="Categoría"
          placeholder="Todas"
          data={CATEGORIAS_ACTIVIDAD}
          clearable
          {...compacto}
          value={categoria}
          onChange={filtrar((v: string | null) =>
            setCategoria(CATEGORIAS_ACTIVIDAD.find((c) => c.value === v)?.value ?? null))}
        />
        <TextInput
          label="Buscar"
          placeholder="En la actividad o el producto…"
          {...compacto}
          value={buscar}
          onChange={(e) => { setBuscar(e.currentTarget.value); setPage(1) }}
        />
      </Toolbar>

      {total > 0 && (
        <Text size="sm" c="dimmed">
          {total} {total === 1 ? 'actividad registrada' : 'actividades registradas'}
        </Text>
      )}

      <DataState
        loading={isLoading}
        error={error}
        empty={filas.length === 0}
        page={page}
        emptyProps={{
          icon: IconListCheck,
          title: hayFiltros ? 'Ninguna actividad coincide' : 'No hay actividades en el período',
          description: hayFiltros
            ? 'Quite la categoría o la búsqueda, o amplíe el período.'
            : 'Anote lo que hizo en su jornada con «Anotar actividad».',
        }}
      >
        <SgthTable
          records={filas}
          columns={columnas}
          totalRecords={total}
          recordsPerPage={15}
          page={page}
          onPageChange={setPage}
          {...PAGINACION_ES}
          minHeight={200}
        />
      </DataState>

      <ActividadFormModal
        opened={formulario.abierto}
        actividad={formulario.actividad}
        onClose={() => setFormulario({ abierto: false, actividad: null })}
      />
    </PageShell>
  )
}
