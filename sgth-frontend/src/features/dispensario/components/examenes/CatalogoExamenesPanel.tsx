'use client'

import { useState } from 'react'
import { Button, Group, Select, Stack, Text, TextInput } from '@mantine/core'
import { IconEdit, IconEye, IconEyeOff, IconFlask, IconPlus } from '@tabler/icons-react'
import { DataState, SectionCard, SgthTable } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useCatalogoExamenesAdmin, useGuardarExamenCatalogo } from '../../hooks/useExamenesAdmin'
import { filtrarPorNombre } from '../../utils/buscarExamen'
import { CatalogoExamenModal } from './CatalogoExamenModal'
import { columnasCatalogoExamenes } from './examenesAdmin.columns'
import type { CatalogoExamen } from '../../services/examenService'

/**
 * El catálogo de la hoja del laboratorio (D7). Un examen no se elimina: las
 * órdenes que ya lo pidieron lo siguen nombrando. Se desactiva y deja de
 * ofrecerse.
 *
 * Llega entero —son unos doscientos y el modal de la solicitud los necesita
 * todos—, así que aquí se filtra en la pantalla en vez de paginar.
 */
export function CatalogoExamenesPanel() {
  const contained = useContainedInput()
  const { data, isLoading, error, refetch } = useCatalogoExamenesAdmin()
  const guardar = useGuardarExamenCatalogo()
  const [area, setArea] = useState<string | null>(null)
  const [filtro, setFiltro] = useState('')
  const [edicion, setEdicion] = useState<{ abierta: boolean; examen: CatalogoExamen | null }>({ abierta: false, examen: null })

  const areas = data?.areas ?? []
  const etiquetaArea = (valor: string) => areas.find((a) => a.value === valor)?.etiqueta ?? valor
  const examenes = filtrarPorNombre(data?.examenes ?? [], filtro).filter((e) => !area || e.area === area)

  return (
    <SectionCard
      title="Catálogo de exámenes"
      description="Lo que se ofrece al pedir exámenes, en el orden de la hoja del laboratorio"
      actions={
        <Button variant="light" leftSection={<IconPlus size={16} />} onClick={() => setEdicion({ abierta: true, examen: null })}>
          Nuevo examen
        </Button>
      }
    >
      <Stack gap="sm">
        <Group gap="sm" align="flex-end">
          <Select label="Área" placeholder="Todas las áreas" clearable {...contained} w={220}
            data={areas.map((a) => ({ value: a.value, label: a.etiqueta }))} value={area} onChange={setArea} />
          <TextInput label="Buscar" placeholder="Parte del nombre" {...contained} w={260}
            value={filtro} onChange={(e) => setFiltro(e.currentTarget.value)} />
          {data && <Text size="xs" c="dimmed" pb={8}>{examenes.length} de {data.examenes.length}</Text>}
        </Group>

        <DataState loading={isLoading} error={error} onRetry={() => refetch()}
          errorTitle="No se pudo cargar el catálogo" empty={!examenes.length}
          emptyProps={{ icon: IconFlask, title: 'Sin exámenes', description: 'Ningún examen coincide con el filtro.' }}>
          <SgthTable
            records={examenes}
            columns={columnasCatalogoExamenes(etiquetaArea, (e) => [
              { label: 'Editar', icon: <IconEdit size={14} />, onClick: () => setEdicion({ abierta: true, examen: e }) },
              {
                label: e.activo ? 'Desactivar' : 'Activar',
                icon: e.activo ? <IconEyeOff size={14} /> : <IconEye size={14} />,
                onClick: () => guardar.mutate({ id: e.id, data: { activo: !e.activo } }),
              },
            ])}
          />
        </DataState>
      </Stack>

      <CatalogoExamenModal opened={edicion.abierta} onClose={() => setEdicion({ abierta: false, examen: null })}
        examen={edicion.examen} areas={areas} />
    </SectionCard>
  )
}
