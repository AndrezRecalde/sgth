'use client'

import { useState } from 'react'
import { SimpleGrid, Stack } from '@mantine/core'
import { IconPalette } from '@tabler/icons-react'
import { DataState, PageHeader, PageShell, SectionCard } from '@/components/ui'
import { DatosInstitucionalesForm } from '@/features/comunicacion/components/DatosInstitucionalesForm'
import { HistorialVersionesDrawer } from '@/features/comunicacion/components/HistorialVersionesDrawer'
import { SubirVersionModal } from '@/features/comunicacion/components/SubirVersionModal'
import { VarianteCard } from '@/features/comunicacion/components/VarianteCard'
import { useEstadoIdentidad } from '@/features/comunicacion/hooks/useIdentidad'
import type { EstadoVariante } from '@/features/comunicacion/services/tiposIdentidad'

/**
 * Identidad institucional (docs/diseno-comunicacion-social.md, 4; fase 2.3):
 * las seis variantes del logo con su versión vigente, las programadas y el
 * historial, y los datos de la institución. La usa la persona designada (D2).
 */
export function IdentidadView() {
  const { data, isLoading, error } = useEstadoIdentidad()
  const [subiendo, setSubiendo] = useState<EstadoVariante | null>(null)
  const [historial, setHistorial] = useState<EstadoVariante | null>(null)

  const variantes = data?.variantes ?? []
  const enUsoDe = (v: EstadoVariante) => variantes.find((otra) => otra.variante === v.en_uso && otra.variante !== v.variante) ?? null

  return (
    <PageShell>
      <PageHeader
        title="Identidad institucional"
        description="Cada logo rige desde una fecha: las pantallas usan el de hoy y cada documento, el de su fecha"
      />

      <DataState
        loading={isLoading}
        error={error}
        empty={!variantes.length}
        emptyProps={{ icon: IconPalette, title: 'No hay variantes del logo', description: 'Recargue la página; si sigue así, avise a TIC.' }}
      >
        <Stack gap="lg">
          <SectionCard
            title="Logos"
            description="Con el isotipo y el imagotipo horizontal el sistema funciona completo; los demás son opcionales y tienen respaldo."
          >
            <SimpleGrid cols={{ base: 1, sm: 2, lg: 3 }} spacing="md">
              {variantes.map((v) => (
                <VarianteCard
                  key={v.variante}
                  variante={v}
                  enUso={enUsoDe(v)}
                  onSubir={() => setSubiendo(v)}
                  onHistorial={() => setHistorial(v)}
                />
              ))}
            </SimpleGrid>
          </SectionCard>

          {/* La clave remonta el formulario cuando cambian los datos guardados. */}
          {data && <DatosInstitucionalesForm key={data.datos.actualizado_en ?? 'inicial'} datos={data.datos} />}
        </Stack>
      </DataState>

      <SubirVersionModal variante={subiendo} datos={data?.datos} onClose={() => setSubiendo(null)} />
      <HistorialVersionesDrawer variante={historial} onClose={() => setHistorial(null)} />
    </PageShell>
  )
}
