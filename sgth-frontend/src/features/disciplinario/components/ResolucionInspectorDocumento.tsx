'use client'

import { Button, Group, Text } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconDownload, IconFileUpload } from '@tabler/icons-react'
import { Controller, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal, notificar } from '@/components/ui'
import { guardarArchivo } from '@/lib/archivo'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { getApiErrorMessage } from '@/types/api'
import { ZonaArchivo } from '@/features/expediente/components/ZonaArchivo'
import { useDisciplinarioMutations } from '../hooks/useDisciplinarioMutations'
import { disciplinarioService } from '../services/disciplinarioService'
import {
  RESOLUCION_MAX_MB, RESOLUCION_TIPOS, resolucionVistoBuenoSchema,
  type ResolucionVistoBuenoFormData,
} from '../schemas/resolucionVistoBueno.schema'
import type { VistoBueno } from '@/types/api'

/** Los estados en los que el Inspector ya se pronunció. */
const CON_RESOLUCION = ['concedido', 'negado', 'impugnado']

/**
 * El PDF de la resolución del Inspector del Trabajo (2026-10-04). Antes había
 * una columna de texto que ninguna pantalla llenaba: la resolución, que es el
 * respaldo de la cesación, no tenía dónde quedar.
 */
export function ResolucionInspectorDocumento({ tramite }: { tramite: VistoBueno }) {
  const [abierto, { open, close }] = useDisclosure(false)

  if (!CON_RESOLUCION.includes(tramite.estado)) return null

  const descargar = async () => {
    try {
      guardarArchivo(
        await disciplinarioService.descargarResolucion(tramite.id),
        tramite.documento_nombre || `resolucion-visto-bueno-${tramite.id}.pdf`,
      )
    } catch (e) {
      notificar.error('No se pudo descargar la resolución', getApiErrorMessage(e))
    }
  }

  return (
    <>
      <Group justify="space-between" mt="xs" wrap="wrap" gap="xs">
        <Text size="sm" c={tramite.tiene_documento ? undefined : 'dimmed'}>
          {tramite.tiene_documento
            ? tramite.documento_nombre ?? 'Resolución adjunta'
            : 'Sin el PDF de la resolución'}
        </Text>
        <Group gap="xs">
          {tramite.tiene_documento && (
            <Button size="xs" variant="default" leftSection={<IconDownload size={14} />} onClick={descargar}>
              Descargar
            </Button>
          )}
          <Button size="xs" variant="light" leftSection={<IconFileUpload size={14} />} onClick={open}>
            {tramite.tiene_documento ? 'Reemplazar' : 'Adjuntar PDF'}
          </Button>
        </Group>
      </Group>

      {abierto && <AdjuntarModal tramiteId={tramite.id} onClose={close} />}
    </>
  )
}

function AdjuntarModal({ tramiteId, onClose }: { tramiteId: number; onClose: () => void }) {
  const { adjuntarResolucion } = useDisciplinarioMutations()
  const { control, handleSubmit, setError, formState: { errors } } = useForm<ResolucionVistoBuenoFormData>({
    resolver: zodResolver(resolucionVistoBuenoSchema),
  })

  const enviar = (valores: ResolucionVistoBuenoFormData) =>
    adjuntarResolucion
      .mutateAsync({ id: tramiteId, archivo: valores.archivo })
      .then(onClose)
      .catch((e) => erroresAlFormulario(
        e, setError, Object.keys(resolucionVistoBuenoSchema.shape), 'No se pudo adjuntar la resolución',
      ))

  return (
    <FormModal
      opened
      onClose={onClose}
      title="Resolución del Inspector del Trabajo"
      size="md"
      onSubmit={handleSubmit(enviar)}
      submitLabel="Adjuntar"
      submitting={adjuntarResolucion.isPending}
    >
      <Controller
        name="archivo"
        control={control}
        render={({ field }) => (
          <ZonaArchivo
            accept={RESOLUCION_TIPOS}
            maxMb={RESOLUCION_MAX_MB}
            formatos={`PDF — máx. ${RESOLUCION_MAX_MB} MB`}
            value={field.value}
            onChange={field.onChange}
            onRechazo={(mensaje) => setError('archivo', { message: mensaje })}
            error={errors.archivo?.message}
          />
        )}
      />
    </FormModal>
  )
}
