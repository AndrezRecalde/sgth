'use client'

import { Alert, Skeleton, Stack } from '@mantine/core'
import { IconAlertTriangle } from '@tabler/icons-react'
import type { Icon } from '@tabler/icons-react'
import { EmptyState } from './EmptyState'

interface Props {
  loading: boolean
  error?: unknown
  /** `true` cuando la consulta terminó y no hay nada que mostrar. */
  empty?: boolean
  /** Configuración del estado vacío. Obligatoria si se usa `empty`. */
  emptyProps?: {
    icon: Icon
    title: string
    description?: string
    action?: React.ReactNode
  }
  /** Filas de esqueleto mientras carga. Aproximar al tamaño real de la lista. */
  skeletonRows?: number
  children: React.ReactNode
}

/** Mensaje legible a partir de un error de axios o de una excepción cualquiera. */
function mensajeDeError(error: unknown): string {
  if (error && typeof error === 'object' && 'message' in error) {
    return String((error as { message: unknown }).message)
  }
  return 'Ocurrió un error inesperado al cargar la información.'
}

/**
 * Los cuatro estados de una consulta al servidor, en un solo sitio:
 * cargando, error, vacío y con datos.
 *
 * Antes cada pantalla los resolvía a su manera —unas con Skeleton, otras con
 * Loader centrado, otras sin estado de error— y el vacío se confundía con el
 * "aún cargando". Envolver la tabla o la lista con esto los unifica:
 *
 *   <DataState
 *     loading={isLoading}
 *     error={error}
 *     empty={!servidores.length}
 *     emptyProps={{ icon: IconUsers, title: 'No hay servidores registrados' }}
 *   >
 *     <SgthTable records={servidores} columns={columnas} />
 *   </DataState>
 */
export function DataState({
  loading,
  error,
  empty,
  emptyProps,
  skeletonRows = 6,
  children,
}: Props) {
  if (loading) {
    return (
      <Stack gap="xs">
        {Array.from({ length: skeletonRows }).map((_, i) => (
          <Skeleton key={i} height={42} radius="md" />
        ))}
      </Stack>
    )
  }

  if (error) {
    return (
      <Alert
        color="red"
        variant="light"
        radius="lg"
        icon={<IconAlertTriangle size={18} />}
        title="No se pudo cargar la información"
      >
        {mensajeDeError(error)}
      </Alert>
    )
  }

  if (empty && emptyProps) {
    return <EmptyState {...emptyProps} />
  }

  return <>{children}</>
}
