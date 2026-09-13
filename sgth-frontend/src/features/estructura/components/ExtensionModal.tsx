'use client'

import { Stack } from '@mantine/core'
import { ModalFooter, SgthModal } from '@/components/ui'
import { useExtensionMutations } from '../hooks/useExtensionMutations'
import { ExtensionForm } from './ExtensionForm'
import type { ExtensionFormData } from '../schemas/extension.schema'
import type { ExtensionConRelaciones } from '@/types/api'

interface Props {
  opened: boolean
  onClose: () => void
  extension?: ExtensionConRelaciones | null
}

export function ExtensionModal({ opened, onClose, extension }: Props) {
  const { crear, editar } = useExtensionMutations()
  const isEditing = !!extension

  const handleSubmit = (values: ExtensionFormData) => {
    const mutation = isEditing
      ? editar.mutateAsync({ id: Number(extension!.id), data: values })
      : crear.mutateAsync(values)
    mutation.then(onClose).catch(() => {})
  }

  return (
    <SgthModal
      opened={opened}
      onClose={onClose}
      title={isEditing ? 'Editar extensión' : 'Nueva extensión telefónica'}
      size="md"
    >
      <Stack>
        <ExtensionForm
          initialValues={isEditing ? {
            unidad_administrativa_id:
              extension?.unidad_administrativa?.id,
            numero_extension: extension?.numero_extension,
            responsable:      (extension as ExtensionConRelaciones &
              { responsable?: string }).responsable,
          } : undefined}
          onSubmit={handleSubmit}
        />
      </Stack>
      <ModalFooter
        onCancel={onClose}
        form="extension-form"
        submitLabel={isEditing ? 'Actualizar' : 'Registrar extensión'}
        submitting={crear.isPending || editar.isPending}
      />
    </SgthModal>
  )
}
