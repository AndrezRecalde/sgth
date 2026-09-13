'use client'

import { useState } from 'react'
import { Badge, Stack, Tabs } from '@mantine/core'
import { useDebouncedValue } from '@mantine/hooks'
import { PageHeader, PageShell } from '@/components/ui'
import { useAccionesViatico } from '../../hooks/useAccionesViatico'
import { useBandejaResumen } from '../../hooks/useBandejaViaticos'
import { VuelosTab } from '../VuelosTab'
import { BandejaEtapaTabla } from './BandejaEtapaTabla'
import { BandejaFiltros, FILTROS_BANDEJA_INICIALES, type FiltrosBandejaForm } from './BandejaFiltros'
import { BandejaMontos } from './BandejaMontos'
import type { EtapaBandeja, FiltrosBandeja } from '@/types/api'

/*
| La bandeja de Financiero: una pestaña por lo que hay que hacer, con su
| contador, los montos de cada etapa y el plazo de las liquidaciones.
|
| Antes Financiero trabajaba sobre el mismo listado que el servidor, filtrando
| por estado. «Mis viáticos» queda para los propios de cada uno.
*/

const ETAPAS: { valor: EtapaBandeja; etiqueta: string }[] = [
  { valor: 'por_aprobar',  etiqueta: 'Por aprobar' },
  { valor: 'por_anticipo', etiqueta: 'Por entregar anticipo' },
  { valor: 'por_iniciar',  etiqueta: 'Listos para salir' },
  { valor: 'en_comision',  etiqueta: 'En comisión' },
  { valor: 'por_liquidar', etiqueta: 'Por liquidar' },
  { valor: 'por_revisar',  etiqueta: 'Por revisar' },
  { valor: 'cerrados',     etiqueta: 'Cerrados' },
]

/** Pestañas en las que un contador distinto de cero pide atención. */
const PIDEN_ACCION = new Set(['por_aprobar', 'por_anticipo', 'por_revisar', 'vuelos'])

export function BandejaViaticosView() {
  const puede = useAccionesViatico()
  const [form, setForm] = useState<FiltrosBandejaForm>(FILTROS_BANDEJA_INICIALES)
  const [etapa, setEtapa] = useState<string>('por_aprobar')
  const [busqueda] = useDebouncedValue(form.busqueda.trim(), 400)

  const filtros: FiltrosBandeja = {
    unidad_id: form.unidadId ? Number(form.unidadId) : null,
    desde:     form.desde,
    hasta:     form.hasta,
    search:    busqueda || null,
  }

  const { data: resumen, isLoading } = useBandejaResumen(filtros)

  const contador = (clave: EtapaBandeja | 'vuelos') => {
    const n = resumen?.conteos[clave] ?? 0
    if (n === 0) return null

    return (
      <Badge size="sm" variant={PIDEN_ACCION.has(clave) ? 'filled' : 'light'} color={PIDEN_ACCION.has(clave) ? 'orange' : 'gray'}>
        {n}
      </Badge>
    )
  }

  return (
    <PageShell>
      <PageHeader
        title="Bandeja de viáticos"
        description="Lo que espera a Financiero, el dinero de cada etapa y los plazos de liquidación"
      />

      <Stack gap="md">
        <BandejaMontos resumen={resumen} loading={isLoading} />

        <BandejaFiltros
          filtros={form}
          unidades={resumen?.unidades ?? []}
          onCambiar={(cambio) => setForm((f) => ({ ...f, ...cambio }))}
        />

        <Tabs value={etapa} onChange={(v) => setEtapa(v ?? 'por_aprobar')} keepMounted={false}>
          <Tabs.List>
            {ETAPAS.map((e) => (
              <Tabs.Tab key={e.valor} value={e.valor} rightSection={contador(e.valor)}>
                {e.etiqueta}
              </Tabs.Tab>
            ))}
            {puede.autorizarVuelos && (
              <Tabs.Tab value="vuelos" rightSection={contador('vuelos')}>
                Vuelos por autorizar
              </Tabs.Tab>
            )}
          </Tabs.List>

          {ETAPAS.map((e) => (
            <Tabs.Panel key={e.valor} value={e.valor} pt="md">
              {/* La clave reinicia la página y el interruptor al cambiar un filtro. */}
              <BandejaEtapaTabla key={JSON.stringify(filtros)} etapa={e.valor} filtros={filtros} />
            </Tabs.Panel>
          ))}
          {puede.autorizarVuelos && (
            <Tabs.Panel value="vuelos" pt="md">
              <VuelosTab />
            </Tabs.Panel>
          )}
        </Tabs>
      </Stack>
    </PageShell>
  )
}
