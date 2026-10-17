'use client'

import { Button } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconPlus, IconSchool } from '@tabler/icons-react'
import { DataState, SectionCard, SgthTable } from '@/components/ui'
import { useMiFormacion, useMiFormacionMutations } from '../hooks/useMiPerfil'
import { getMiFormacionColumns } from './miFormacion.columns'
import { RegistrarFormacionModal } from './RegistrarFormacionModal'

/**
 * La formación académica propia en «Mi perfil». El servidor registra sus
 * títulos y cursos con el PDF y quedan por validar; lo que registra Talento
 * Humano en el expediente sale ya validado (decisión del 2026-10-17).
 */
export function MiFormacionAcademica() {
  const [abierto, { open, close }] = useDisclosure(false)
  const { data: formacion = [], isLoading, error, refetch } = useMiFormacion()
  const { retirar, verDocumento } = useMiFormacionMutations()

  const columns = getMiFormacionColumns({
    onDocumento: (id) => verDocumento.mutate(id),
    onRetirar:   (id) => retirar.mutate(id),
  })

  const botonRegistrar = (
    <Button size="xs" variant="light" leftSection={<IconPlus size={14} />} onClick={open}>
      Registrar título o curso
    </Button>
  )

  return (
    <SectionCard
      title="Formación académica"
      description="Sus títulos y capacitaciones. Lo que registre usted espera la revisión de Talento Humano antes de salir en su hoja de vida."
      actions={botonRegistrar}
    >
      <DataState
        loading={isLoading}
        error={error}
        empty={!formacion.length}
        errorTitle="No se pudo cargar su formación académica"
        onRetry={() => void refetch()}
        skeletonRows={3}
        emptyProps={{
          icon: IconSchool,
          title: 'No tiene formación registrada',
          description: 'Registre sus títulos y cursos con el documento que los respalda; Talento Humano los validará.',
          action: botonRegistrar,
        }}
      >
        <SgthTable records={formacion} columns={columns} minHeight={100} />
      </DataState>

      <RegistrarFormacionModal opened={abierto} onClose={close} />
    </SectionCard>
  )
}
