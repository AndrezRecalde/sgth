'use client'

import { useState } from 'react'
import { Button, Stack } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconPlus } from '@tabler/icons-react'
import { SectionHeading, SgthTable } from '@/components/ui'
import { useCondicionCargaMutations } from '../hooks/useCondicionCargaMutations'
import { getDiscapacidadesColumns, getEnfermedadesColumns } from './condicion.columns'
import { DiscapacidadModal } from './DiscapacidadModal'
import { EnfermedadModal } from './EnfermedadModal'
import type {
  CargaFamiliar, DiscapacidadCargaFamiliar, EnfermedadCatastroficaCargaFamiliar,
} from '@/types/api'

interface Props {
  carga: CargaFamiliar
  servidorId: number
}

const botonAgregar = (onClick: () => void) => (
  <Button size="xs" variant="subtle" leftSection={<IconPlus size={14} />} onClick={onClick}>
    Agregar
  </Button>
)

/**
 * Las condiciones de un familiar, que se despliegan bajo su fila.
 *
 * Los dos bloques se ven siempre. Antes solo aparecía el que declaraba un
 * interruptor del formulario, y apagarlo escondía los registros que ya había.
 * Ahora la marca la deriva el backend de los registros.
 */
export function CargaFamiliarCondiciones({ carga, servidorId }: Props) {
  const [discOpened, { open: abrirDisc, close: cerrarDisc }] = useDisclosure(false)
  const [enfOpened, { open: abrirEnf, close: cerrarEnf }] = useDisclosure(false)
  const [editDisc, setEditDisc] = useState<DiscapacidadCargaFamiliar | null>(null)
  const [editEnf, setEditEnf] = useState<EnfermedadCatastroficaCargaFamiliar | null>(null)
  const {
    crearDiscapacidad, editarDiscapacidad, eliminarDiscapacidad,
    crearEnfermedad, editarEnfermedad, eliminarEnfermedad,
  } = useCondicionCargaMutations(servidorId, Number(carga.id))

  return (
    <Stack gap="md" p="md">
      <div>
        <SectionHeading
          title="Discapacidades"
          mb="xs"
          action={botonAgregar(() => { setEditDisc(null); abrirDisc() })}
        />
        <SgthTable
          records={carga.discapacidades ?? []}
          columns={getDiscapacidadesColumns<DiscapacidadCargaFamiliar>({
            onEdit: (item) => { setEditDisc(item); abrirDisc() },
            onDelete: (id) => eliminarDiscapacidad.mutate(id),
          })}
          minHeight={130}
          noRecordsText={carga.persona_con_discapacidad
            ? 'Marcado con discapacidad, sin detalle: agregue el tipo y el porcentaje.'
            : 'Sin discapacidades registradas.'}
        />
      </div>

      <div>
        <SectionHeading
          title="Enfermedades catastróficas"
          mb="xs"
          action={botonAgregar(() => { setEditEnf(null); abrirEnf() })}
        />
        <SgthTable
          records={carga.enfermedades_catastroficas ?? []}
          columns={getEnfermedadesColumns<EnfermedadCatastroficaCargaFamiliar>({
            onEdit: (item) => { setEditEnf(item); abrirEnf() },
            onDelete: (id) => eliminarEnfermedad.mutate(id),
          })}
          minHeight={130}
          noRecordsText={carga.posee_enfermedad_catastrofica
            ? 'Marcado con enfermedad catastrófica, sin detalle: agregue el diagnóstico.'
            : 'Sin enfermedades registradas.'}
        />
      </div>

      <DiscapacidadModal
        key={`disc-${editDisc?.id ?? 'nueva'}`}
        opened={discOpened}
        onClose={() => { setEditDisc(null); cerrarDisc() }}
        onGuardar={(data, id) => (id
          ? editarDiscapacidad.mutateAsync({ id, data })
          : crearDiscapacidad.mutateAsync(data))}
        guardando={crearDiscapacidad.isPending || editarDiscapacidad.isPending}
        initialValues={editDisc}
      />
      <EnfermedadModal
        key={`enf-${editEnf?.id ?? 'nueva'}`}
        opened={enfOpened}
        onClose={() => { setEditEnf(null); cerrarEnf() }}
        onGuardar={(data, id) => (id
          ? editarEnfermedad.mutateAsync({ id, data })
          : crearEnfermedad.mutateAsync(data))}
        guardando={crearEnfermedad.isPending || editarEnfermedad.isPending}
        initialValues={editEnf}
      />
    </Stack>
  )
}
