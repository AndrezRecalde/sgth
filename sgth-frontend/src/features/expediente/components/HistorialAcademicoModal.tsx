'use client'

import { Modal, Button, Group, Stack, TextInput, Select } from '@mantine/core'
import { useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useEffect } from 'react'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useHistorialAcademicoMutations } from '../hooks/useHistorialAcademicoMutations'
import { historialAcademicoSchema, type HistorialAcademicoFormData }
  from '../schemas/historialAcademico.schema'
import type { HistorialAcademicoServidor } from '@/types/api'

const TIPO_OPTIONS = [
  { value: 'estudio',      label: 'Título Académico' },
  { value: 'capacitacion', label: 'Capacitación / Curso' },
]

const NIVEL_OPTIONS = [
  { value: 'primaria',     label: 'Primaria' },
  { value: 'secundaria',   label: 'Secundaria' },
  { value: 'tercer_nivel', label: 'Tercer nivel (Pregrado)' },
  { value: 'cuarto_nivel', label: 'Cuarto nivel (Posgrado)' },
]

const NACIONALIDAD_OPTIONS = [
  { value: 'nacional',      label: 'Nacional' },
  { value: 'internacional', label: 'Internacional' },
]

interface Props {
  opened:     boolean
  onClose:    () => void
  servidorId: number
  initialValues?: HistorialAcademicoServidor | null
}

export function HistorialAcademicoModal({ opened, onClose, servidorId, initialValues }: Props) {
  const { isMobile } = useMobileBreakpoint()
  const contained = useContainedInput()
  const { crear, editar }  = useHistorialAcademicoMutations(servidorId)
  const isEditing = !!initialValues

  const { register, control, handleSubmit, watch, reset, formState: { errors } } =
    useForm<HistorialAcademicoFormData>({
      resolver: zodResolver(historialAcademicoSchema),
      defaultValues: {
        tipo_estudio:         'estudio',
        nivel_estudio:        'tercer_nivel',
        nacionalidad_estudio: 'nacional',
        institucion:          '',
        fecha_inicio:         '',
        fecha_fin:            '',
        titulo_capacitacion:  '',
        codigo_senescyt:      '',
      },
    })

  const tipoEstudio = watch('tipo_estudio')

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
      reset({
        tipo_estudio:         'estudio',
        nivel_estudio:        'tercer_nivel',
        nacionalidad_estudio: 'nacional',
        institucion:          '',
        fecha_inicio:         '',
        fecha_fin:            '',
        titulo_capacitacion:  '',
        codigo_senescyt:      '',
      })
    }
  }, [initialValues, reset])

  const onSubmit = (values: HistorialAcademicoFormData) => {
    const payload = {
      ...values,
      nivel_estudio: values.tipo_estudio === 'estudio' ? (values.nivel_estudio || null) : null,
      fecha_fin: values.fecha_fin || null,
      codigo_senescyt: values.codigo_senescyt || null,
    }
    const promise = initialValues
      ? editar.mutateAsync({ id: initialValues.id, data: payload as Record<string, unknown> })
      : crear.mutateAsync(payload as Record<string, unknown>)

    promise
      .then(() => { reset(); onClose() })
      .catch(() => {})
  }

  return (
    <Modal opened={opened} onClose={onClose}
      title={isEditing ? "Editar título o capacitación" : "Registrar título o capacitación"}
      size="md" fullScreen={isMobile}
      radius={isMobile ? 0 : 'xl'}>
      <form onSubmit={handleSubmit(onSubmit)}>
        <Stack gap="sm">
          <Controller name="tipo_estudio" control={control}
            render={({ field }) => (
              <Select label="Tipo de registro"
                data={TIPO_OPTIONS} {...contained}
                value={field.value} onChange={field.onChange}
                error={errors.tipo_estudio?.message} />
            )} />

          {tipoEstudio === 'estudio' && (
            <Controller name="nivel_estudio" control={control}
              render={({ field }) => (
                <Select label="Nivel de instrucción"
                  data={NIVEL_OPTIONS} {...contained}
                  value={field.value ?? ''} onChange={field.onChange}
                  error={errors.nivel_estudio?.message} />
              )} />
          )}

          <Controller name="nacionalidad_estudio" control={control}
            render={({ field }) => (
              <Select label="Nacionalidad de la institución"
                data={NACIONALIDAD_OPTIONS} {...contained}
                value={field.value} onChange={field.onChange}
                error={errors.nacionalidad_estudio?.message} />
            )} />

          <TextInput label="Título / Capacitación obtenida"
            placeholder="Ej: Ingeniero en Sistemas, Certificado Scrum Master"
            {...contained} {...register('titulo_capacitacion')}
            error={errors.titulo_capacitacion?.message} />

          <TextInput label="Institución educativa"
            placeholder="Nombre de la institución o centro de estudios"
            {...contained} {...register('institucion')}
            error={errors.institucion?.message} />

          <TextInput label="Fecha inicio"
            type="date"
            {...contained} {...register('fecha_inicio')}
            error={errors.fecha_inicio?.message} />

          <TextInput label="Fecha fin (Opcional)"
            type="date"
            placeholder="Dejar vacío si sigue cursando"
            {...contained} {...register('fecha_fin')}
            error={errors.fecha_fin?.message} />

          {tipoEstudio === 'estudio' && (
            <TextInput label="Código de registro SENESCYT (Opcional)"
              placeholder="Ej: 1005-2021-2245367"
              {...contained} {...register('codigo_senescyt')}
              error={errors.codigo_senescyt?.message} />
          )}

          <Group justify="flex-end" mt="md">
            <Button variant="default" onClick={onClose}>Cancelar</Button>
            <Button type="submit" color="emerald" variant="light"
              loading={initialValues ? editar.isPending : crear.isPending}>
              {isEditing ? "Guardar cambios" : "Registrar"}
            </Button>
          </Group>
        </Stack>
      </form>
    </Modal>
  )
}
