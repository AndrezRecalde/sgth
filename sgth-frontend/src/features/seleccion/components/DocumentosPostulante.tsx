'use client'

import { ActionIcon, Button, FileInput, Group, Paper, Select, Stack, Text, Tooltip } from '@mantine/core'
import { IconDownload, IconFile, IconTrash, IconUpload } from '@tabler/icons-react'
import { Controller, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { confirmar, EmptyState } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { useDocumentosPostulante } from '../hooks/useDocumentosPostulante'
import { documentoPostulanteSchema, type DocumentoPostulanteFormData } from '../schemas/documentoPostulante.schema'
import { TIPOS_DOCUMENTO } from '../services/documentoPostulanteService'
import type { Postulante } from '../services/convocatoriaService'

interface Props {
  convocatoriaId: number
  postulante:     Postulante
  /** Subir y quitar es de quien gestiona; bajar, de quien ve. */
  puedeGestionar: boolean
}

const kb = (bytes?: number | null) => (bytes ? `${Math.max(1, Math.round(bytes / 1024))} KB` : '')

/**
 * Los documentos de la postulación (2026-10-05). El API para subirlos,
 * bajarlos y quitarlos existía, pero ninguna pantalla lo usaba.
 */
export function DocumentosPostulante({ convocatoriaId, postulante, puedeGestionar }: Props) {
  const contained = useContainedInput()
  const { subir, descargar, eliminar } = useDocumentosPostulante(convocatoriaId, postulante.id)
  const documentos = postulante.documentos ?? []

  const { control, handleSubmit, reset, setError, formState: { errors } } = useForm<DocumentoPostulanteFormData>({
    resolver: zodResolver(documentoPostulanteSchema),
    defaultValues: { tipo: '', archivo: undefined },
  })

  const enviar = (valores: DocumentoPostulanteFormData) =>
    subir.mutateAsync(valores)
      .then(() => reset({ tipo: '', archivo: undefined }))
      .catch((e) => erroresAlFormulario(e, setError, ['tipo', 'archivo'], 'No se pudo subir el documento'))

  return (
    <Stack gap="sm">
      {documentos.length === 0 ? (
        <EmptyState icon={IconFile} title="Sin documentos" description="Este candidato todavía no tiene documentos cargados." />
      ) : documentos.map((d) => (
        <Paper key={d.id} withBorder radius="md" p="xs">
          <Group justify="space-between" wrap="nowrap">
            <Stack gap={0} style={{ minWidth: 0 }}>
              <Text size="sm" fw={500}>{d.tipo}</Text>
              <Text size="xs" c="dimmed" truncate>{d.nombre_archivo} {kb(d.tamano_bytes)}</Text>
            </Stack>
            <Group gap={4} wrap="nowrap">
              <Tooltip label="Descargar">
                <ActionIcon variant="subtle" aria-label={`Descargar ${d.nombre_archivo}`}
                  loading={descargar.isPending && descargar.variables?.id === d.id}
                  onClick={() => descargar.mutate(d)}>
                  <IconDownload size={16} />
                </ActionIcon>
              </Tooltip>
              {puedeGestionar && (
                <Tooltip label="Eliminar">
                  <ActionIcon variant="subtle" color="red" aria-label={`Eliminar ${d.nombre_archivo}`}
                    onClick={() => confirmar({
                      title: 'Eliminar documento',
                      message: <>Se quitará <b>{d.nombre_archivo}</b> del candidato.</>,
                      destructiva: true,
                      onConfirm: () => eliminar.mutate(d.id),
                    })}>
                    <IconTrash size={16} />
                  </ActionIcon>
                </Tooltip>
              )}
            </Group>
          </Group>
        </Paper>
      ))}

      {puedeGestionar && (
        <form onSubmit={handleSubmit(enviar)} noValidate>
          <Stack gap="xs">
            <Controller name="tipo" control={control} render={({ field }) => (
              <Select label="Tipo de documento" data={TIPOS_DOCUMENTO} placeholder="Seleccione" required
                {...contained} value={field.value || null} onChange={(v) => field.onChange(v ?? '')}
                error={errors.tipo?.message} />
            )} />
            <Controller name="archivo" control={control} render={({ field }) => (
              <FileInput label="Archivo" description="PDF, imagen o Word — máximo 10 MB" placeholder="Seleccionar archivo"
                accept="application/pdf,image/png,image/jpeg,.doc,.docx" required
                {...contained} value={field.value ?? null} onChange={(f) => field.onChange(f ?? undefined)}
                error={errors.archivo?.message} />
            )} />
            <Group justify="flex-end">
              <Button type="submit" size="xs" leftSection={<IconUpload size={14} />} loading={subir.isPending}>
                Subir documento
              </Button>
            </Group>
          </Stack>
        </form>
      )}
    </Stack>
  )
}
