'use client'

import { Alert, Button, Skeleton, Stack, Text } from '@mantine/core'
import { IconAlertTriangle, IconRefresh } from '@tabler/icons-react'
import type { Icon } from '@tabler/icons-react'
import { getApiErrorMessage } from '@/types/api'
import { EmptyState } from './EmptyState'

interface Props {
  loading: boolean
  error?: unknown
  /** `true` cuando la consulta terminó y no hay nada que mostrar. */
  empty?: boolean
  /**
   * La página actual, en una lista paginada. Solo sirve para una cosa: que el
   * estado vacío no se coma la tabla cuando se está fuera de rango.
   *
   * `empty` se calcula casi siempre como `!lista.length`, que en la página 1
   * significa «no hay nada» y en la 2 puede significar «no hay nada AQUÍ». Si
   * el resultado encoge —se filtra, o alguien borra mientras miras— la página
   * en la que estabas se queda sin filas, y entonces el estado vacío sustituye
   * a la tabla ENTERA, paginador incluido: la pantalla afirma que no hay nada
   * y encima no deja volver a la página 1.
   *
   * Pasando `page`, a partir de la segunda se pinta la tabla vacía con su
   * paginador, que es lo que permite salir.
   */
  page?: number
  /** Configuración del estado vacío. Obligatoria si se usa `empty`. */
  emptyProps?: {
    icon: Icon
    title: string
    description?: string
    action?: React.ReactNode
  }
  /**
   * Título del error. Por defecto, «No se pudo cargar la información».
   * Conviene concretarlo cuando lo que falla tiene nombre: «No se pudo cargar
   * el historial».
   */
  errorTitle?: string
  /**
   * Se añade detrás del mensaje del servidor, para decir qué **no** significa
   * el fallo. En una pantalla clínica esa frase es la diferencia entre «no se
   * pudo consultar» y «el paciente no tiene nada».
   */
  errorHint?: React.ReactNode
  /** Con esto, el error ofrece un botón de reintentar. Normalmente `refetch`. */
  onRetry?: () => void
  /** Filas de esqueleto mientras carga. Aproximar al tamaño real de la lista. */
  skeletonRows?: number
  /**
   * Opcional porque también se usa como compuerta: un panel que hace `return`
   * temprano mientras carga o falla lo monta sin hijos, y el contenido va
   * después. Sin hijos y sin error, no pinta nada.
   */
  children?: React.ReactNode
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
 *
 * El caso que más daño hace es no pasarle `error`: la consulta falla, `data`
 * se queda en su valor por defecto y la pantalla afirma que no hay nada. En
 * una historia clínica eso es decir que el paciente no tiene alergias cuando
 * lo que pasa es que no se pudieron consultar. Para eso están `errorTitle`,
 * `errorHint` y `onRetry`.
 */
export function DataState({
  loading,
  error,
  empty,
  page,
  emptyProps,
  errorTitle = 'No se pudo cargar la información',
  errorHint,
  onRetry,
  skeletonRows = 6,
  children = null,
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
        title={errorTitle}
      >
        <Stack gap="xs" align="flex-start">
          <Text size="sm">
            {/* El mensaje del backend, no el de axios: `error.message` a secas
                dejaba «Request failed with status code 500» en pantalla. */}
            {getApiErrorMessage(error, 'Ocurrió un error inesperado al cargar la información.')}
            {errorHint && <> {errorHint}</>}
          </Text>
          {onRetry && (
            <Button
              size="compact-xs"
              variant="light"
              leftSection={<IconRefresh size={13} />}
              onClick={onRetry}
            >
              Reintentar
            </Button>
          )}
        </Stack>
      </Alert>
    )
  }

  // Fuera de la primera página, una lista sin filas no quiere decir que no
  // haya nada: quiere decir que no hay nada aquí. Ver `page` en los props.
  if (empty && emptyProps && (page ?? 1) === 1) {
    return <EmptyState {...emptyProps} />
  }

  return <>{children}</>
}
