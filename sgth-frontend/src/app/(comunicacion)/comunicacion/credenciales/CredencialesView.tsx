'use client'

import { useState } from 'react'
import { Alert, Button, Group } from '@mantine/core'
import { useDebouncedValue } from '@mantine/hooks'
import { IconAlertTriangle, IconHistory, IconId, IconPrinter, IconShieldX } from '@tabler/icons-react'
import { DataState, PAGINACION_ES, PageHeader, PageShell, SgthTable, type TableAction } from '@/components/ui'
import { CredencialesFiltros } from '@/features/comunicacion/components/CredencialesFiltros'
import { HistorialCredencialesDrawer } from '@/features/comunicacion/components/HistorialCredencialesDrawer'
import {
  ImprimirCredencialesModal, MAXIMO_POR_IMPRESION, type PedidoDeImpresion,
} from '@/features/comunicacion/components/ImprimirCredencialesModal'
import { RevocarCredencialModal } from '@/features/comunicacion/components/RevocarCredencialModal'
import { columnasCredenciales } from '@/features/comunicacion/components/credenciales.columns'
import { useCredenciales, useDireccionVerificacion } from '@/features/comunicacion/hooks/useCredenciales'
import { usePuedeGestionarCredenciales } from '@/features/comunicacion/hooks/usePuedeGestionarCredenciales'
import type { FiltrosCredenciales, ServidorConCredencial } from '@/features/comunicacion/services/tiposCredencial'

const RETARDO_BUSQUEDA_MS = 300

/** Se imprime la que vale hoy y tiene foto: es lo que pide el backend. */
const imprimible = (s: ServidorConCredencial) => !!s.credencial?.vigente && !!s.fotografia

/**
 * Credenciales de los servidores (docs/diseno-comunicacion-social.md, 6; fase
 * 3.2): la situación de cada uno, imprimir una, varias o una unidad, revocar
 * y reponer, y el historial con la devolución de la tarjeta. Talento Humano y
 * Auditoría la consultan; solo Comunicación imprime, revoca y recibe.
 */
export function CredencialesView({ filtrosIniciales = {} }: { filtrosIniciales?: FiltrosCredenciales }) {
  const puedeGestionar = usePuedeGestionarCredenciales()

  const [page, setPage] = useState(1)
  // Desde el Inicio llega ya filtrada: «Por reimprimir» abre las desactualizadas.
  const [filtros, setFiltros] = useState<FiltrosCredenciales>(filtrosIniciales)
  const [busqueda, setBusqueda] = useState('')
  const [buscar] = useDebouncedValue(busqueda.trim(), RETARDO_BUSQUEDA_MS)
  const [seleccion, setSeleccion] = useState<ServidorConCredencial[]>([])

  const [impresion, setImpresion] = useState<PedidoDeImpresion | null>(null)
  const [revocando, setRevocando] = useState<ServidorConCredencial | null>(null)
  const [historial, setHistorial] = useState<ServidorConCredencial | null>(null)

  const consulta = { ...filtros, ...(buscar.length >= 2 ? { buscar } : {}) }
  const { data, isLoading, error } = useCredenciales(consulta, page)
  const { data: direccion } = useDireccionVerificacion()

  const hayFiltros = busqueda.trim() !== '' || Object.values(filtros).some((v) => v !== undefined)

  // Cambiar un filtro o la página vuelve a empezar la selección: lo marcado
  // fuera de la vista no se imprimiría a ciegas.
  const cambiar = (cambio: Partial<FiltrosCredenciales>) => {
    setFiltros((f) => ({ ...f, ...cambio }))
    setPage(1)
    setSeleccion([])
  }
  const limpiar = () => {
    setFiltros({})
    setBusqueda('')
    setPage(1)
    setSeleccion([])
  }

  const accionesDe = (s: ServidorConCredencial): TableAction[] => [
    {
      label: 'Imprimir esta credencial',
      icon: <IconPrinter size={14} />,
      hidden: !puedeGestionar || !imprimible(s),
      onClick: () => setImpresion({ modo: 'seleccion', servidores: [s] }),
    },
    { label: 'Historial', icon: <IconHistory size={14} />, onClick: () => setHistorial(s) },
    {
      label: 'Revocar y reponer',
      icon: <IconShieldX size={14} />,
      color: 'red',
      hidden: !puedeGestionar || !s.credencial,
      onClick: () => setRevocando(s),
    },
  ]

  return (
    <PageShell>
      <PageHeader
        title="Credenciales"
        description="La credencial de cada servidor: nace sola con su foto; aquí se imprime, se revoca y se recibe la tarjeta cuando sale"
        actions={puedeGestionar ? (
          <Group gap="xs">
            {seleccion.length > 0 && (
              <Button
                variant="light"
                leftSection={<IconPrinter size={16} />}
                onClick={() => setImpresion({ modo: 'seleccion', servidores: seleccion })}
              >
                Imprimir {seleccion.length === 1 ? 'la elegida' : `las ${seleccion.length} elegidas`}
              </Button>
            )}
            <Button
              variant="light"
              leftSection={<IconPrinter size={16} />}
              onClick={() => setImpresion({ modo: 'unidad', unidadId: filtros.unidad_administrativa_id })}
            >
              Imprimir por unidad
            </Button>
          </Group>
        ) : undefined}
      />

      {/* El QR guarda la dirección: con una local, ningún teléfono la abre. */}
      {direccion?.local && (
        <Alert
          variant="light"
          color={direccion.impresion_bloqueada ? 'red' : 'amber'}
          icon={<IconAlertTriangle size={18} />}
          title="El QR de las credenciales lleva una dirección local"
        >
          Apunta a {direccion.url_base}…, que solo se abre en este equipo: un teléfono que lo escanee no llega a la
          verificación. {direccion.impresion_bloqueada
            ? 'Por eso no se imprime: configure FRONTEND_URL con la dirección pública del sistema.'
            : 'Sirve para probar; las tarjetas que se entreguen deben imprimirse con la dirección pública (FRONTEND_URL).'}
        </Alert>
      )}

      <CredencialesFiltros
        filtros={filtros}
        busqueda={busqueda}
        onBusqueda={(texto) => { setBusqueda(texto); setPage(1); setSeleccion([]) }}
        onCambiar={cambiar}
        onLimpiar={limpiar}
        hayFiltros={hayFiltros}
      />

      <DataState
        loading={isLoading}
        error={error}
        empty={!data?.data.length}
        page={page}
        emptyProps={hayFiltros ? {
          icon: IconId,
          title: 'Ningún servidor coincide con los filtros',
          description: filtros.situacion === 'por_recibir'
            ? 'No hay tarjetas pendientes de devolver.'
            : 'Pruebe con otro nombre o cédula, o quite alguno de los filtros.',
          action: <Button variant="subtle" onClick={limpiar}>Quitar los filtros</Button>,
        } : {
          icon: IconId,
          title: 'No hay servidores activos',
          description: 'Cuando Talento Humano registre a los servidores y Comunicación les tome la foto, sus credenciales aparecerán aquí.',
        }}
      >
        <SgthTable
          {...PAGINACION_ES}
          records={data?.data ?? []}
          columns={columnasCredenciales(accionesDe)}
          totalRecords={data?.total ?? 0}
          recordsPerPage={15}
          page={page}
          onPageChange={(p) => { setPage(p); setSeleccion([]) }}
          // Elegir es para imprimir: solo quien imprime, y solo las que se pueden.
          selectedRecords={puedeGestionar ? seleccion : undefined}
          onSelectedRecordsChange={puedeGestionar
            ? (filas) => setSeleccion(filas.slice(0, MAXIMO_POR_IMPRESION))
            : undefined}
          isRecordSelectable={imprimible}
          minHeight={200}
        />
      </DataState>

      <ImprimirCredencialesModal pedido={impresion} onClose={() => setImpresion(null)} onImpreso={() => setSeleccion([])} />
      <RevocarCredencialModal servidor={revocando} onClose={() => setRevocando(null)} />
      <HistorialCredencialesDrawer servidor={historial} onClose={() => setHistorial(null)} puedeGestionar={puedeGestionar} />
    </PageShell>
  )
}
