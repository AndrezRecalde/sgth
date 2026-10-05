'use client'

import { useEffect } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal } from '@/components/ui'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { useActualizarPostulante } from '../../hooks/usePostulanteMutations'
import { CAMPOS_INSCRIPCION, esquemaInscripcion, type InscripcionFormData } from '../../schemas/postulante.schema'
import type { Postulante } from '../../services/convocatoriaService'
import { DatosPostulanteCampos } from '../inscripcion/DatosPostulanteCampos'

interface Props {
  opened:     boolean
  onClose:    () => void
  postulante: Postulante
}

const aFormulario = (p: Postulante): InscripcionFormData => ({
  cedula: p.cedula, nombres: p.nombres, segundo_nombre: p.segundo_nombre ?? '',
  apellidos: p.apellidos, segundo_apellido: p.segundo_apellido ?? '', correo: p.correo,
  telefono: p.telefono ?? '', genero: p.genero as InscripcionFormData['genero'],
  estado_civil: p.estado_civil ?? null, fecha_nacimiento: p.fecha_nacimiento?.slice(0, 10) ?? null,
  tipo_sangre: p.tipo_sangre ?? null, puesto_id: null, fecha_inscripcion: null,
})

/**
 * Corregir los datos de un candidato (2026-10-05): una cédula o un correo mal
 * escritos al inscribir no tenían arreglo. Los mismos campos que la
 * inscripción; la cédula, el backend solo la deja cambiar antes del Dispensario.
 */
export function EditarPostulanteModal({ opened, onClose, postulante }: Props) {
  const actualizar = useActualizarPostulante(postulante.convocatoria_id, postulante.id)
  const { control, register, handleSubmit, reset, setError, formState: { errors } } = useForm<InscripcionFormData>({
    resolver: zodResolver(esquemaInscripcion(false)),
    defaultValues: aFormulario(postulante),
  })

  useEffect(() => { if (opened) reset(aFormulario(postulante)) }, [opened, postulante, reset])

  const enviar = ({ puesto_id: _p, fecha_inscripcion: _f, ...datos }: InscripcionFormData) =>
    actualizar.mutateAsync(datos)
      .then(onClose)
      .catch((e) => erroresAlFormulario(e, setError, CAMPOS_INSCRIPCION, 'No se pudieron corregir los datos'))

  return (
    <FormModal opened={opened} onClose={onClose} title="Corregir datos del candidato" size="xl"
      onSubmit={handleSubmit(enviar)} submitLabel="Guardar cambios" submitting={actualizar.isPending}>
      <DatosPostulanteCampos control={control} register={register} errors={errors} />
    </FormModal>
  )
}
