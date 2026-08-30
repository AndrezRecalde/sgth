'use client'

import { Modal, Button, Group, Stack } from '@mantine/core'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { useUnidadMutations } from '../hooks/useUnidadMutations'
import { etiquetaNivel, tituloNuevo } from '../utils/jerarquia'
import { UnidadForm } from './UnidadForm'
import type { UnidadFormData } from '../schemas/unidad.schema'
import type { UnidadConRelaciones } from '@/types/api'

interface Props {
  opened: boolean
  onClose: () => void
  unidad?: UnidadConRelaciones | null
  /**
   * Unidad superior preseleccionada al crear. Es lo que distingue «Nueva
   * unidad» de agregar una dependencia desde un nodo concreto del árbol, y de
   * su nivel sale el nombre de lo que se está creando.
   */
  padre?: UnidadConRelaciones | null
}

export function UnidadModal({ opened, onClose, unidad, padre }: Props) {
  const { isMobile } = useMobileBreakpoint()
  const { crear, editar } = useUnidadMutations()
  const isEditing = !!unidad

  const handleSubmit = (values: UnidadFormData) => {
    const mutation = isEditing
      ? editar.mutateAsync({ id: Number(unidad!.id), data: values })
      : crear.mutateAsync(values)

    mutation.then(onClose).catch(() => {})
  }

  const isPending = crear.isPending || editar.isPending

  // Lo que se crea se nombra por el nivel que va a ocupar, no por el botón que
  // se pulsó: colgar de la institución da una unidad administrativa, y colgar
  // de una unidad administrativa da un subproceso.
  // Sin unidad superior preseleccionada todavía no se sabe qué se va a crear
  // —depende de lo que se elija en el formulario—, así que el título se queda
  // en lo genérico en vez de prometer «Nueva institución», que es justo lo
  // único que no se puede crear cuando ya hay una.
  const titulo = isEditing
    ? `Editar ${etiquetaNivel(unidad!.nivel ?? 2).toLowerCase()}`
    : padre
      ? tituloNuevo((padre.nivel ?? 1) + 1)
      : 'Nueva unidad'

  return (
    <Modal
      opened={opened}
      onClose={onClose}
      title={titulo}
      size="lg"
      fullScreen={isMobile}
      radius={isMobile ? 0 : 'xl'}
    >
      <Stack>
        <UnidadForm
          // Sin `key`, react-hook-form conserva los valores por defecto del
          // primer montaje y el modal reabre con los datos de la unidad
          // anterior — o con el padre que ya no corresponde.
          key={isEditing ? `editar-${unidad!.id}` : `crear-${padre?.id ?? 'raiz'}`}
          unidadId={isEditing ? Number(unidad!.id) : null}
          initialValues={isEditing ? {
            nombre:          unidad!.nombre       ?? '',
            codigo:          unidad!.codigo       ?? '',
            acronimo:        unidad!.acronimo     ?? '',
            descripcion:     unidad!.descripcion  ?? '',
            tipo_unidad_id:  unidad!.tipo_unidad?.id ?? undefined,
            unidad_padre_id: unidad!.unidad_padre_id ?? null,
            estado:          unidad!.estado ?? true,
            es_unidad_talento_humano: unidad!.es_unidad_talento_humano ?? false,
            es_maxima_autoridad:      unidad!.es_maxima_autoridad      ?? false,
          } : { unidad_padre_id: padre ? Number(padre.id) : null }}
          onSubmit={handleSubmit}
          isPending={isPending}
        />
        <Group justify="flex-end" mt="md">
          <Button variant="default" onClick={onClose}>
            Cancelar
          </Button>
          <Button
            type="submit"
            form="unidad-form"
            loading={isPending}
            color="emerald"
          >
            {isEditing ? 'Actualizar' : 'Crear'}
          </Button>
        </Group>
      </Stack>
    </Modal>
  )
}
