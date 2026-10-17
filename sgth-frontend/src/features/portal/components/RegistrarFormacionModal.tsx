'use client'

import { Alert, Stack, Text } from '@mantine/core'
import { IconInfoCircle } from '@tabler/icons-react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal } from '@/components/ui'
import { HistorialAcademicoCampos } from '@/features/expediente/components/HistorialAcademicoCampos'
import {
  historialAcademicoSchema, miFormacionSchema, type HistorialAcademicoFormData,
} from '@/features/expediente/schemas/historialAcademico.schema'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { useMiFormacionMutations } from '../hooks/useMiPerfil'

interface Props {
  opened:  boolean
  onClose: () => void
}

const VACIO: HistorialAcademicoFormData = {
  tipo_estudio:         'estudio',
  nivel_estudio:        'tercer_nivel',
  nacionalidad_estudio: 'nacional',
  institucion:          '',
  fecha_inicio:         '',
  fecha_fin:            '',
  titulo_capacitacion:  '',
  codigo_senescyt:      '',
  documento:            null,
}

/**
 * El servidor registra un título o curso desde «Mi perfil», con el PDF que lo
 * respalda. Queda por validar: a la hoja de vida llega cuando Talento Humano
 * lo revisa. Los campos son los del expediente.
 */
export function RegistrarFormacionModal({ opened, onClose }: Props) {
  const { registrar } = useMiFormacionMutations()
  const form = useForm<HistorialAcademicoFormData>({
    resolver: zodResolver(miFormacionSchema),
    defaultValues: VACIO,
  })

  const cerrar = () => {
    form.reset(VACIO)
    onClose()
  }

  const enviar = form.handleSubmit((values) => {
    // El esquema ya exige el PDF; esto solo se lo dice al compilador.
    if (!(values.documento instanceof File)) return

    registrar.mutateAsync({
      ...values,
      documento: values.documento,
      nivel_estudio: values.tipo_estudio === 'estudio' ? (values.nivel_estudio || null) : null,
    })
      .then(cerrar)
      .catch((e) => erroresAlFormulario(
        e, form.setError, Object.keys(historialAcademicoSchema.shape), 'No se pudo registrar el título',
      ))
  })

  return (
    <FormModal
      opened={opened}
      onClose={cerrar}
      title="Registrar título o curso"
      size="md"
      onSubmit={enviar}
      submitLabel="Registrar"
      submitting={registrar.isPending}
    >
      <Stack gap="sm">
        <Alert icon={<IconInfoCircle size={16} />} color="ocean" variant="light">
          <Text size="sm">
            Talento Humano lo revisará con el documento que adjunte. Mientras tanto
            queda «Por validar» y no sale en su hoja de vida.
          </Text>
        </Alert>
        <HistorialAcademicoCampos form={form} conDocumento />
      </Stack>
    </FormModal>
  )
}
