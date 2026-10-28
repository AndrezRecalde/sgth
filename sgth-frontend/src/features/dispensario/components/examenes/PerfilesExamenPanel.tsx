'use client'

import { useState } from 'react'
import { Button } from '@mantine/core'
import { IconEdit, IconEye, IconEyeOff, IconListCheck, IconPlus } from '@tabler/icons-react'
import { DataState, SectionCard, SgthTable } from '@/components/ui'
import { useAuthStore } from '@/store/auth.store'
import { useGuardarPerfilExamen, usePerfilesExamenAdmin } from '../../hooks/useExamenesAdmin'
import { PerfilExamenModal } from './PerfilExamenModal'
import { columnasPerfilesExamen } from './examenesAdmin.columns'
import type { Especialidad } from '../../services/kpisService'
import type { PerfilExamen } from '../../services/examenService'

const ESPECIALIDADES: Especialidad[] = ['medicina_general', 'odontologia']

/** Quién atiende cada especialidad (`EspecialidadAtencion::rol()`). */
const ROL: Record<Especialidad, string> = { medicina_general: 'medico', odontologia: 'odontologo' }

/**
 * Los perfiles de exámenes. Los ve todo el personal clínico; los arma la
 * administración y, de su especialidad, el médico y el odontólogo, como
 * exige el servidor.
 */
export function PerfilesExamenPanel() {
  const { data = [], isLoading, error, refetch } = usePerfilesExamenAdmin()
  const guardar = useGuardarPerfilExamen()
  const { hasRole, hasPermiso } = useAuthStore()
  const [edicion, setEdicion] = useState<{ abierta: boolean; perfil: PerfilExamen | null }>({ abierta: false, perfil: null })

  const especialidades = ESPECIALIDADES.filter((e) => hasPermiso('supervisar-dispensario') || hasRole(ROL[e]))
  const puedeArmar = (p: PerfilExamen) => especialidades.includes(p.especialidad)

  return (
    <SectionCard
      title="Perfiles de exámenes"
      description="Grupos de exámenes que se marcan de una vez al pedir"
      actions={especialidades.length > 0 && (
        <Button variant="light" leftSection={<IconPlus size={16} />} onClick={() => setEdicion({ abierta: true, perfil: null })}>
          Nuevo perfil
        </Button>
      )}
    >
      <DataState loading={isLoading} error={error} onRetry={() => refetch()}
        errorTitle="No se pudieron cargar los perfiles" empty={!data.length}
        emptyProps={{ icon: IconListCheck, title: 'Sin perfiles', description: 'Arme el primero con los exámenes que suele pedir juntos.' }}>
        <SgthTable
          records={data}
          columns={columnasPerfilesExamen((p) => [
            { label: 'Editar', icon: <IconEdit size={14} />, hidden: !puedeArmar(p), onClick: () => setEdicion({ abierta: true, perfil: p }) },
            {
              label: p.activo ? 'Desactivar' : 'Activar',
              icon: p.activo ? <IconEyeOff size={14} /> : <IconEye size={14} />,
              hidden: !puedeArmar(p),
              onClick: () => guardar.mutate({ id: p.id, data: { activo: !p.activo } }),
            },
          ])}
        />
      </DataState>

      <PerfilExamenModal opened={edicion.abierta} onClose={() => setEdicion({ abierta: false, perfil: null })}
        perfil={edicion.perfil} especialidades={especialidades} />
    </SectionCard>
  )
}
