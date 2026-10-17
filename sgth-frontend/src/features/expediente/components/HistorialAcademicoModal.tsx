'use client'

import { useEffect } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal } from '@/components/ui'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { useHistorialAcademicoMutations } from '../hooks/useHistorialAcademicoMutations'
import { historialAcademicoSchema, type HistorialAcademicoFormData }
  from '../schemas/historialAcademico.schema'
import { HistorialAcademicoCampos } from './HistorialAcademicoCampos'
import type { HistorialAcademicoServidor } from '@/types/api'

interface Props {
  opened:     boolean
  onClose:    () => void
  servidorId: number
  initialValues?: HistorialAcademicoServidor | null
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
}

/**
 * Registrar o corregir un título o curso desde el expediente. Lo que registra
 * Talento Humano entra validado; los campos los comparte con «Mi perfil».
 */
export function HistorialAcademicoModal({ opened, onClose, servidorId, initialValues }: Props) {
  const { crear, editar }  = useHistorialAcademicoMutations(servidorId)
  const isEditing = !!initialValues

  const form = useForm<HistorialAcademicoFormData>({
    resolver: zodResolver(historialAcademicoSchema),
    defaultValues: VACIO,
  })
  const { handleSubmit, reset, setError } = form

  useEffect(() => {
    if (initialValues) {
      reset({
        tipo_estudio:         initialValues.tipo_estudio ?? 'estudio',
        nivel_estudio:        initialValues.nivel_estudio,
        nacionalidad_estudio: initialValues.nacionalidad_estudio ?? 'nacional',
        institucion:          initialValues.institucion ?? '',
        fecha_inicio:         initialValues.fecha_inicio ? initialValues.fecha_inicio.split('T')[0] : '',
        fecha_fin:            initialValues.fecha_fin ? initialValues.fecha_fin.split('T')[0] : '',
        titulo_capacitacion:  initialValues.titulo_capacitacion ?? '',
        codigo_senescyt:      initialValues.codigo_senescyt ?? '',
      })
    } else {
      reset(VACIO)
    }
  }, [initialValues, reset])

  const onSubmit = (values: HistorialAcademicoFormData) => {
    // El documento solo lo adjunta el servidor desde el portal.
    const { documento: _documento, ...datos } = values
    const payload = {
      ...datos,
      nivel_estudio: values.tipo_estudio === 'estudio' ? (values.nivel_estudio || null) : null,
      fecha_fin: values.fecha_fin || null,
      codigo_senescyt: values.codigo_senescyt || null,
    }
    const promise = initialValues
      ? editar.mutateAsync({ id: initialValues.id, data: payload })
      : crear.mutateAsync(payload)

    promise
      .then(() => { reset(); onClose() })
      .catch((e) => erroresAlFormulario(
        e, setError, Object.keys(historialAcademicoSchema.shape), 'No se pudo guardar el registro',
      ))
  }

  return (
    <FormModal
      opened={opened}
      onClose={onClose}
      title={isEditing ? "Editar título o capacitación" : "Registrar título o capacitación"}
      size="md"
      onSubmit={handleSubmit(onSubmit)}
      submitLabel={isEditing ? "Guardar cambios" : "Registrar"}
      submitting={initialValues ? editar.isPending : crear.isPending}
    >
      <HistorialAcademicoCampos form={form} />
    </FormModal>
  )
}
