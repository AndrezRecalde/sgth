'use client'

import { IconSchool } from '@tabler/icons-react'
import { DataState, SectionCard, SgthTable } from '@/components/ui'
import { getHistorialAcademicoColumns } from '@/features/expediente/components/historialAcademico.columns'
import { useMiFormacion } from '../hooks/useMiPerfil'

/**
 * La formación académica propia en «Mi perfil»: las mismas columnas que ve
 * Talento Humano en el expediente, sin editar ni eliminar.
 */
export function MiFormacionAcademica() {
  const { data: formacion = [], isLoading, error, refetch } = useMiFormacion()

  return (
    <SectionCard
      title="Formación académica"
      description="Sus títulos y capacitaciones, tal como constan en su expediente."
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
          description: 'Talento Humano registra los títulos en su expediente. Si falta alguno, pídale que lo agregue.',
        }}
      >
        <SgthTable records={formacion} columns={getHistorialAcademicoColumns()} minHeight={100} />
      </DataState>
    </SectionCard>
  )
}
