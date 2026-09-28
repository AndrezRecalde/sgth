'use client'

import { Alert, Stack } from '@mantine/core'
import { IconInfoCircle } from '@tabler/icons-react'
import { useForm, type DefaultValues } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal, notificar } from '@/components/ui'
import { erroresDeCampo } from '@/lib/erroresDeCampo'
import { useSubrogacionMutations } from '../hooks/useSubrogacionMutations'
import { useFormularioSubrogacion } from '../hooks/useFormularioSubrogacion'
import { SubrogacionFiguraYPuesto } from './SubrogacionFiguraYPuesto'
import { SubrogacionSituacion } from './SubrogacionSituacion'
import { SubrogacionPlazoYMotivo } from './SubrogacionPlazoYMotivo'
import { subrogacionSchema, type SubrogacionFormData } from '../schemas/subrogacion.schema'

/**
 * Los selectores arrancan sin valor. El esquema los exige al enviar, pero los
 * valores iniciales de un formulario son parciales por definición —de ahí
 * `DefaultValues`—: darles un número inventado los mostraría como ya elegidos.
 */
const BLANK_VALUES: DefaultValues<SubrogacionFormData> = {
  tipo: 'subrogacion',
  servidor_subrogado_id:  null,
  fecha_inicio: '',
  fecha_fin:    '',
  motivo: 'vacaciones',
  resolucion_numero: '',
  observacion: '',
}

const CAMPOS = [
  'tipo', 'servidor_subrogante_id', 'servidor_subrogado_id',
  'unidad_administrativa_id', 'puesto_subrogado_id',
  'fecha_inicio', 'fecha_fin', 'motivo', 'resolucion_numero', 'observacion',
] as const

const esCampo = (nombre: string): nombre is keyof SubrogacionFormData =>
  (CAMPOS as readonly string[]).includes(nombre)

interface Props {
  opened: boolean
  onClose: () => void
}

export function SubrogacionModal({ opened, onClose }: Props) {
  const { registrar } = useSubrogacionMutations()

  const form = useForm<SubrogacionFormData>({
    resolver: zodResolver(subrogacionSchema),
    defaultValues: BLANK_VALUES,
  })

  const datos = useFormularioSubrogacion(form)

  const cerrar = () => {
    form.reset(BLANK_VALUES)
    datos.limpiarSubrogante()
    onClose()
  }

  /**
   * Las reglas de negocio las valida el backend —que el titular ocupe el
   * puesto, que no haya traslape, que la figura corresponda— y su respuesta
   * vuelve al campo que la provocó (regla 07). Notificarlas y ya dejaba a quien
   * llena el formulario buscando cuál de los diez campos está mal.
   */
  const guardar = async (valores: SubrogacionFormData) => {
    try {
      await registrar.mutateAsync(valores)
      cerrar()
    } catch (error) {
      const campos = erroresDeCampo(error)
      if (!campos) return // el hook ya lo notificó

      const sinCampo: string[] = []
      for (const [campo, mensaje] of Object.entries(campos)) {
        if (esCampo(campo)) form.setError(campo, { message: mensaje })
        else sinCampo.push(mensaje)
      }
      if (sinCampo.length) {
        notificar.error('No se pudo registrar la subrogación', sinCampo.join(' '))
      }
    }
  }

  return (
    <FormModal
      opened={opened}
      onClose={cerrar}
      title="Nueva subrogación / encargo"
      size="xl"
      onSubmit={form.handleSubmit(guardar)}
      submitLabel="Registrar"
      submitting={registrar.isPending}
      submitDisabled={datos.figuraEquivocada}
    >
      <Stack gap="sm">
        <Alert variant="light" color="ocean" icon={<IconInfoCircle size={16} />}>
          Queda <strong>pendiente de aprobación</strong>: el servidor asume el
          puesto —y con él la facultad de firmar— recién cuando su Acción de
          Personal se registre, con el dictamen presupuestario correspondiente.
        </Alert>

        <SubrogacionFiguraYPuesto form={form} datos={datos} />

        <SubrogacionSituacion datos={datos} />

        <SubrogacionPlazoYMotivo form={form} tipo={datos.tipo} />
      </Stack>
    </FormModal>
  )
}
