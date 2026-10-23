'use client'

import { useState } from 'react'
import { Button, Divider, Group, Stack, Switch, Text, TextInput } from '@mantine/core'
import { IconEdit, IconShieldCheck } from '@tabler/icons-react'
import { DataState, SectionCard, SectionHeading, StatusBadge } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useCatalogoRiesgos } from '../../hooks/useCatalogoRiesgos'
import { useExamenesPorRiesgo } from '../../hooks/useExamenesPorRiesgo'
import { claveRiesgo } from '../../services/examenesPorRiesgoService'
import { filtrarPorNombre } from '../../utils/buscarExamen'
import { ExamenesDeRiesgoModal, type FactorElegido } from './ExamenesDeRiesgoModal'

interface Props {
  /** El médico ocupacional y la administración lo arman; el resto lo ve. */
  puedeEditar: boolean
}

/**
 * Qué exámenes pide cada factor de riesgo del puesto (D6), sobre los 61 del
 * formulario del MSP. La ficha ocupacional los propone al marcar el factor.
 */
export function ExamenesPorRiesgoPanel({ puedeEditar }: Props) {
  const contained = useContainedInput()
  const catalogo = useCatalogoRiesgos()
  const mapa = useExamenesPorRiesgo()
  const [filtro, setFiltro] = useState('')
  const [soloConExamenes, setSoloConExamenes] = useState(false)
  const [editando, setEditando] = useState<FactorElegido | null>(null)

  const porClave = new Map((mapa.data ?? []).map((r) => [claveRiesgo(r.categoria, r.factor), r.examenes]))
  const categorias = Object.entries(catalogo.data ?? {}).map(([clave, cat]) => {
    const factores = filtrarPorNombre(cat.grupos.flatMap((g) => g.factores).map((nombre) => ({ nombre })), filtro)
      .filter((f) => !soloConExamenes || (porClave.get(claveRiesgo(clave, f.nombre))?.length ?? 0) > 0)
    return { clave, etiqueta: cat.etiqueta, factores }
  }).filter((c) => c.factores.length > 0)

  return (
    <SectionCard title="Exámenes por factor de riesgo"
      description="Los que la ficha ocupacional propone cuando el puesto tiene el riesgo marcado (sección G)">
      <Stack gap="md">
        <Group gap="sm" align="flex-end">
          <TextInput label="Buscar factor" placeholder="Ruido, polvos…" {...contained} w={260}
            value={filtro} onChange={(e) => setFiltro(e.currentTarget.value)} />
          <Switch label="Solo los que tienen exámenes" checked={soloConExamenes} pb={8}
            onChange={(e) => setSoloConExamenes(e.currentTarget.checked)} />
        </Group>

        <DataState loading={catalogo.isLoading || mapa.isLoading} error={catalogo.error ?? mapa.error}
          onRetry={() => { catalogo.refetch(); mapa.refetch() }}
          errorTitle="No se pudo cargar el mapa de riesgos" empty={!categorias.length}
          emptyProps={{ icon: IconShieldCheck, title: 'Sin factores', description: 'Ningún factor coincide con el filtro.' }}>
          <Stack gap="lg">
            {categorias.map((c) => (
              <Stack key={c.clave} gap={6}>
                <SectionHeading title={c.etiqueta} />
                {c.factores.map((f, i) => {
                  const examenes = porClave.get(claveRiesgo(c.clave, f.nombre)) ?? []
                  return (
                    <Stack key={f.nombre} gap={6}>
                      {i > 0 && <Divider />}
                      <Group justify="space-between" wrap="nowrap" align="flex-start">
                        <Stack gap={4}>
                          <Text size="sm">{f.nombre}</Text>
                          <Group gap={4}>
                            {examenes.length
                              ? examenes.map((e) => <StatusBadge key={e.id} size="xs">{e.nombre}</StatusBadge>)
                              : <Text size="xs" c="dimmed">No propone exámenes</Text>}
                          </Group>
                        </Stack>
                        {puedeEditar && (
                          <Button size="compact-xs" variant="subtle" leftSection={<IconEdit size={13} />}
                            onClick={() => setEditando({
                              categoria: c.clave, etiquetaCategoria: c.etiqueta, factor: f.nombre,
                              examenes: examenes.map((e) => e.id),
                            })}>
                            Editar
                          </Button>
                        )}
                      </Group>
                    </Stack>
                  )
                })}
              </Stack>
            ))}
          </Stack>
        </DataState>
      </Stack>

      <ExamenesDeRiesgoModal factor={editando} onClose={() => setEditando(null)} />
    </SectionCard>
  )
}
