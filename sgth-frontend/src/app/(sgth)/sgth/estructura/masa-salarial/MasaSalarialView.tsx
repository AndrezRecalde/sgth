'use client'

import { useState } from 'react'
import { Alert, Stack } from '@mantine/core'
import { IconInfoCircle, IconReportMoney } from '@tabler/icons-react'
import { DataState, PageHeader, PageShell } from '@/components/ui'
import { MasaSalarialAparte, type CajonAparte } from '@/features/estructura/components/MasaSalarialAparte'
import { MasaSalarialCajon, type CajonMasa } from '@/features/estructura/components/MasaSalarialCajon'
import {
  FILTROS_MASA_INICIALES, MasaSalarialFiltros, type FiltrosMasa,
} from '@/features/estructura/components/MasaSalarialFiltros'
import { MasaSalarialTotales } from '@/features/estructura/components/MasaSalarialTotales'
import { MasaSalarialVistas } from '@/features/estructura/components/MasaSalarialVistas'
import { useMasaSalarial } from '@/features/estructura/hooks/useMasaSalarial'
import type { AgruparMasa } from '@/features/estructura/utils/masaSalarial'

/** Lo que abre cada renglón de «Fuera del total» y «Datos por completar». */
const CAJON_APARTE: Record<CajonAparte, CajonMasa> = {
  sin_remuneracion_en_la_fecha: {
    tipo: 'vinculos', seleccion: { por: 'situacion', situacion: 'sin_remuneracion_en_la_fecha', titulo: 'Sin remuneración en la fecha' },
  },
  sin_remuneracion_registrada: {
    tipo: 'vinculos', seleccion: { por: 'situacion', situacion: 'sin_remuneracion_registrada', titulo: 'Vínculos sin remuneración registrada' },
  },
  subrogaciones: { tipo: 'subrogaciones' },
  sin_vinculo:   { tipo: 'sin_vinculo' },
}

/**
 * La masa salarial referencial (docs/diseno-masa-salarial.md): la suma de las
 * remuneraciones registradas a una fecha de corte. No es lo pagado, que está
 * en el sistema de Financiero, y la pantalla lo dice siempre (D1).
 */
export function MasaSalarialView() {
  const [filtros, setFiltros] = useState<FiltrosMasa>(FILTROS_MASA_INICIALES)
  const [agrupar, setAgrupar] = useState<AgruparMasa>('tipo_nombramiento')
  const [cajon, setCajon] = useState<CajonMasa | null>(null)

  const { data, isLoading, error, refetch } = useMasaSalarial({
    corte:             filtros.corte,
    regimen:           filtros.regimen ?? undefined,
    tipo_nombramiento: filtros.tipo ?? undefined,
    unidad_id:         filtros.unidadId ?? undefined,
  })

  return (
    <PageShell>
      <PageHeader
        title="Masa salarial"
        description="Suma de las remuneraciones registradas en los vínculos vigentes a la fecha de corte"
      />

      <MasaSalarialFiltros
        filtros={filtros}
        onCambiar={(cambio) => setFiltros((f) => ({ ...f, ...cambio }))}
        regimenes={data?.vistas.regimen ?? []}
        tipos={data?.vistas.tipo_nombramiento ?? []}
      />

      <Alert variant="light" color="ocean" icon={<IconInfoCircle size={16} />}>
        {data?.aviso ?? 'Monto referencial: no es lo pagado, que está en el sistema de Financiero.'}
      </Alert>

      <MasaSalarialTotales totales={data?.totales} loading={isLoading} />

      <DataState
        loading={isLoading}
        error={error}
        errorTitle="No se pudo calcular la masa salarial"
        onRetry={refetch}
        empty={!isLoading && !!data && data.detalle.length === 0}
        emptyProps={{
          icon: IconReportMoney,
          title: 'Sin vínculos vigentes',
          description: 'Nadie tiene un vínculo vigente en esta fecha con estos filtros. Pruebe con otra fecha o quite un filtro.',
        }}
      >
        {data && (
          <Stack gap="lg">
            <MasaSalarialVistas
              datos={data}
              agrupar={agrupar}
              onAgrupar={setAgrupar}
              onSeleccionar={(seleccion) => setCajon({ tipo: 'vinculos', seleccion })}
            />
            <MasaSalarialAparte datos={data} onAbrir={(c) => setCajon(CAJON_APARTE[c])} />
          </Stack>
        )}
      </DataState>

      <MasaSalarialCajon cajon={cajon} datos={data} onClose={() => setCajon(null)} />
    </PageShell>
  )
}
