'use client'

import { Button } from '@mantine/core'
import { IconFolderOff, IconLock } from '@tabler/icons-react'
import { isAxiosError } from 'axios'
import { DataState, EmptyState } from '@/components/ui'

interface Props {
  error: unknown
  onRetry: () => void
  onIrAlListado: () => void
}

/**
 * Por qué no se puede enseñar una ficha.
 *
 * Antes cualquier fallo decía «Expediente no encontrado… o fue dada de baja».
 * Un 403 —quien no es de Talento Humano, admin-ti incluido desde el
 * 2026-10-03— y una red caída se leían como si la ficha no existiera.
 */
export function ExpedienteNoDisponible({ error, onRetry, onIrAlListado }: Props) {
  const estado = isAxiosError(error) ? error.response?.status : undefined

  if (estado === 403) {
    return (
      <EmptyState
        icon={IconLock}
        title="Sin acceso a este expediente"
        description="El Expediente Digital lo gestiona Talento Humano. Si necesita un dato de esta ficha, pídalo a la UATH."
      />
    )
  }

  if (!error || estado === 404) {
    return (
      <EmptyState
        icon={IconFolderOff}
        title="Expediente no encontrado"
        description="La ficha no existe o fue dada de baja. Vuelva al listado y ábrala desde allí."
        action={<Button variant="light" onClick={onIrAlListado}>Ir al listado</Button>}
      />
    )
  }

  return (
    <DataState
      loading={false}
      error={error}
      errorTitle="No se pudo abrir el expediente"
      errorHint="No quiere decir que la ficha no exista: no se pudo consultar."
      onRetry={onRetry}
    />
  )
}
