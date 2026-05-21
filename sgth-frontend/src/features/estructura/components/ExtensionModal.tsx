'use client'

import { Modal, Button, Group, Stack } from '@mantine/core'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
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
  const { isMobile } = useMobileBreakpoint()
  const { crear, editar } = useExtensionMutations()
  const isEditing = !!extension

  const handleSubmit = (values: ExtensionFormData) => {
    const mutation = isEditing
      ? editar.mutateAsync({ id: Number((extension as ExtensionConRelaciones & { id: number })!.id), data: values })
      : crear.mutateAsync(values)
    mutation.then(onClose).catch(() => {})
  }

  return (
    <Modal
      opened={opened}
      onClose={onClose}
      title={isEditing ? 'Editar extensión' : 'Nueva extensión telefónica'}
      size="md"
      fullScreen={isMobile}
      radius={isMobile ? 0 : 'xl'}
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
        <Group justify="flex-end" mt="md">
          <Button variant="default" onClick={onClose}>Cancelar</Button>
          <Button
            type="submit"
            form="extension-form"
            loading={crear.isPending || editar.isPending}
            color="emerald"
          >
            {isEditing ? 'Actualizar' : 'Registrar extensión'}
          </Button>
        </Group>
      </Stack>
    </Modal>
  )
}
