'use client'

import { useState } from 'react'
import { Modal, Button, Group, Stack, Select,
         Text, Textarea, TextInput } from '@mantine/core'
import { Dropzone } from '@mantine/dropzone'
import { Controller, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod/v4'
import { IconUpload, IconX, IconFile } from '@tabler/icons-react'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useDocumentoMutations } from '../hooks/useDocumentoMutations'

const TIPO_OPTIONS = [
  { group: 'Identificación', items: [
    { value: 'cedula',            label: 'Cédula de identidad' },
    { value: 'papeleta_votacion', label: 'Papeleta de votación' },
    { value: 'pasaporte',         label: 'Pasaporte' },
  ]},
  { group: 'Académico', items: [
    { value: 'titulo_academico',  label: 'Título académico' },
    { value: 'certificado',       label: 'Certificado' },
  ]},
  { group: 'Laboral', items: [
    { value: 'contrato',          label: 'Contrato' },
    { value: 'nombramiento',      label: 'Nombramiento' },
    { value: 'resolucion',        label: 'Resolución' },
  ]},
  { group: 'Declaraciones', items: [
    { value: 'declaracion',       label: 'Declaración juramentada' },
  ]},
  { group: 'Otros', items: [
    { value: 'otro',              label: 'Otro documento' },
  ]},
]

const schema = z.object({
  tipo_documento:    z.string().min(1, 'Seleccione el tipo'),
  descripcion:       z.string().optional(),
  fecha_vencimiento: z.string().optional(),
})

type FormData = z.infer<typeof schema>

interface Props {
  opened:     boolean
  onClose:    () => void
  servidorId: number
}

export function DocumentoModal({ opened, onClose, servidorId }: Props) {
  const { isMobile } = useMobileBreakpoint()
  const contained = useContainedInput()
  const { subir } = useDocumentoMutations(servidorId)
  const [archivo, setArchivo] = useState<File | null>(null)
  const [archivoError, setArchivoError] = useState('')

  const { control, register, handleSubmit, reset, formState: { errors } } =
    useForm<FormData>({
      resolver: zodResolver(schema),
      defaultValues: {
        tipo_documento:    '',
        descripcion:       '',
        fecha_vencimiento: '',
      },
    })

  const handleClose = () => {
    reset()
    setArchivo(null)
    setArchivoError('')
    onClose()
  }

  const onSubmit = (values: FormData) => {
    if (!archivo) {
      setArchivoError('Seleccione un archivo para subir')
      return
    }
    const formData = new FormData()
    formData.append('archivo',          archivo)
    formData.append('tipo_documento',   values.tipo_documento)
    if (values.descripcion)
      formData.append('descripcion',      values.descripcion)
    if (values.fecha_vencimiento)
      formData.append('fecha_vencimiento', values.fecha_vencimiento)

    subir.mutateAsync(formData)
      .then(handleClose)
      .catch(() => {})
  }

  return (
    <Modal opened={opened} onClose={handleClose}
      title="Subir documento al expediente"
      size="md" fullScreen={isMobile}
      radius={isMobile ? 0 : 'xl'}>
      <form onSubmit={handleSubmit(onSubmit)}>
        <Stack gap="sm">
          <Controller name="tipo_documento" control={control}
            render={({ field }) => (
              <Select label="Tipo de documento"
                placeholder="Seleccionar tipo"
                data={TIPO_OPTIONS} {...contained}
                value={field.value}
                onChange={(v) => field.onChange(v ?? '')}
                error={errors.tipo_documento?.message} />
            )} />

          <Dropzone
            onDrop={(files) => {
              setArchivo(files[0])
              setArchivoError('')
            }}
            onReject={() => setArchivoError('Archivo no válido')}
            maxSize={10 * 1024 * 1024}
            accept={['application/pdf','image/jpeg','image/png',
                     'application/msword',
                     'application/vnd.openxmlformats-officedocument.wordprocessingml.document']}
          >
            <Group justify="center" gap="xl" mih={80}>
              <Dropzone.Accept>
                <IconUpload size={28} color="var(--mantine-color-emerald-6)" />
              </Dropzone.Accept>
              <Dropzone.Reject>
                <IconX size={28} color="var(--mantine-color-red-6)" />
              </Dropzone.Reject>
              <Dropzone.Idle>
                <IconFile size={28} color="var(--mantine-color-dimmed)" />
              </Dropzone.Idle>
              <div>
                {archivo ? (
                  <Text size="sm" fw={500} c="emerald">
                    {archivo.name}
                  </Text>
                ) : (
                  <>
                    <Text size="sm" fw={500}>
                      Arrastra el archivo aquí o haz clic para seleccionar
                    </Text>
                    <Text size="xs" c="dimmed" mt={4}>
                      PDF, JPG, PNG, DOC, DOCX — máx. 10MB
                    </Text>
                  </>
                )}
              </div>
            </Group>
          </Dropzone>
          {archivoError && (
            <Text size="xs" c="red">{archivoError}</Text>
          )}

          <Textarea label="Descripción"
            placeholder="Descripción opcional del documento" rows={2}
            {...contained} {...register('descripcion')}
            error={errors.descripcion?.message} />

          <TextInput label="Fecha de vencimiento"
            type="date" {...contained}
            {...register('fecha_vencimiento')}
            description="Útil para pasaportes y documentos con caducidad"
            error={errors.fecha_vencimiento?.message} />

          <Group justify="flex-end" mt="md">
            <Button variant="default" onClick={handleClose}>Cancelar</Button>
            <Button type="submit" color="emerald" variant="light"
              loading={subir.isPending}
              leftSection={<IconUpload size={14} />}>
              Subir documento
            </Button>
          </Group>
        </Stack>
      </form>
    </Modal>
  )
}
