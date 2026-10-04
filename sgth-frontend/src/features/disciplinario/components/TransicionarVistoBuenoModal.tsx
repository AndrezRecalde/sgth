'use client'

import { Alert, Select, Stack, Text } from '@mantine/core'
import { IconAlertTriangle } from '@tabler/icons-react'
import { Controller, useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal, ModalFooter, SgthModal, notificar } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresDeCampo } from '@/lib/erroresDeCampo'
import { fromDateValue } from '@/lib/fecha'
import { useDisciplinarioMutations } from '../hooks/useDisciplinarioMutations'
import {
  transicionVistoBuenoSchema,
  type TransicionVistoBuenoFormValues,
} from '../schemas/transicionVistoBueno.schema'
import { TransicionVistoBuenoCampos } from './TransicionVistoBuenoCampos'
import {
  ESTADO_VISTO_BUENO_LABELS,
  TRANSICIONES_VISTO_BUENO,
  nombreServidor,
} from '../utils/etiquetas'
import type { VistoBueno } from '@/types/api'

interface Props {
  opened: boolean
  onClose: () => void
  tramite: VistoBueno | null
}

export function TransicionarVistoBuenoModal({ opened, onClose, tramite }: Props) {
  if (!tramite) {
    return (
      <SgthModal opened={opened} onClose={onClose} title="Actualizar trámite de visto bueno" />
    )
  }

  // El formulario se remonta al cambiar de trámite (key), así arranca con los
  // valores de ese trámite sin resetear estado desde un efecto.
  return <FormularioTransicion key={tramite.id} opened={opened} onClose={onClose} tramite={tramite} />
}

function FormularioTransicion({
  opened,
  onClose,
  tramite,
}: {
  opened: boolean
  onClose: () => void
  tramite: VistoBueno
}) {
  const contained = useContainedInput()
  const { transicionarVistoBueno } = useDisciplinarioMutations()

  const opciones = TRANSICIONES_VISTO_BUENO[tramite.estado].map((e) => ({
    value: e,
    label: ESTADO_VISTO_BUENO_LABELS[e],
  }))

  const {
    control,
    register,
    handleSubmit,
    setError,
    formState: { errors },
  } = useForm<TransicionVistoBuenoFormValues>({
    resolver: zodResolver(transicionVistoBuenoSchema),
    // `estado` se omite: lo elige quien actualiza (regla 09).
    defaultValues: {
      fecha: fromDateValue(new Date()),
      resolucion_detalle: '',
      numero_tramite_mdt: tramite.numero_tramite_mdt ?? '',
      inspectoria: tramite.inspectoria ?? '',
      inspector_nombre: tramite.inspector_nombre ?? '',
    },
  })

  const destino = useWatch({ control, name: 'estado' })
  const esResolucion = destino === 'concedido' || destino === 'negado'
  const esNotificacion = destino === 'notificado'

  const guardar = async (valores: TransicionVistoBuenoFormValues) => {
    try {
      await transicionarVistoBueno.mutateAsync({
        id: tramite.id,
        data: {
          estado: valores.estado,
          resolucion_detalle: esResolucion ? valores.resolucion_detalle.trim() : null,
          fecha_resolucion: esResolucion ? valores.fecha : null,
          fecha_notificacion: esNotificacion ? valores.fecha : null,
          numero_tramite_mdt: valores.numero_tramite_mdt.trim() || null,
          inspectoria: valores.inspectoria.trim() || null,
          inspector_nombre: valores.inspector_nombre.trim() || null,
        },
      })
      onClose()
    } catch (error) {
      const campos = erroresDeCampo(error)
      if (!campos) return // el hook ya lo notificó

      const sinCampo: string[] = []
      for (const [campo, mensaje] of Object.entries(campos)) {
        if (campo in transicionVistoBuenoSchema.shape) {
          setError(campo as keyof TransicionVistoBuenoFormValues, { message: mensaje })
        } else {
          sinCampo.push(mensaje)
        }
      }

      if (sinCampo.length) {
        notificar.error('No se pudo actualizar el trámite', sinCampo.join(' '))
      }
    }
  }

  const encabezado = (
    <Text size="sm" c="dimmed">
      {nombreServidor(tramite.servidor)} — estado actual:{' '}
      <strong>{ESTADO_VISTO_BUENO_LABELS[tramite.estado]}</strong>
    </Text>
  )

  // Un trámite terminal no admite cambios: el modal queda de consulta, con
  // «Cerrar» por único botón (regla 06).
  if (opciones.length === 0) {
    return (
      <SgthModal opened={opened} onClose={onClose} title="Actualizar trámite de visto bueno">
        <Stack gap="sm">
          {encabezado}
          <Alert variant="light" color="slate">
            Este trámite ya está en un estado terminal y no admite más cambios.
          </Alert>
        </Stack>
        <ModalFooter onCancel={onClose} cancelLabel="Cerrar" sinPrincipal />
      </SgthModal>
    )
  }

  return (
    <FormModal
      opened={opened}
      onClose={onClose}
      title="Actualizar trámite de visto bueno"
      onSubmit={handleSubmit(guardar)}
      submitLabel="Actualizar trámite"
      submitting={transicionarVistoBueno.isPending}
      closeOnClickOutside={false}
    >
      <Stack gap="sm">
        {encabezado}

        <Controller
          name="estado"
          control={control}
          render={({ field }) => (
            <Select
              label="Nuevo estado"
              required
              placeholder="Seleccione"
              data={opciones}
              error={errors.estado?.message}
              {...contained}
              value={field.value ?? null}
              onChange={field.onChange}
            />
          )}
        />

        <TransicionVistoBuenoCampos
          control={control}
          register={register}
          errors={errors}
          esResolucion={esResolucion}
          esNotificacion={esNotificacion}
        />

        {destino === 'impugnado' && tramite.movimiento_personal && (
          <Alert variant="light" color="amber" icon={<IconAlertTriangle size={16} />}>
            La Cesación de Funciones generada <strong>no se anula</strong>: la
            impugnación no deja sin efecto la resolución del Inspector. Queda
            marcada como impugnada en Acciones de Personal, para que Talento
            Humano la revise con Asesoría Jurídica antes de continuar.
          </Alert>
        )}

        {destino === 'concedido' && (
          <Alert variant="light" color="amber" icon={<IconAlertTriangle size={16} />}>
            Al conceder el visto bueno se generará una Cesación de Funciones en
            borrador. El vínculo del trabajador no se cierra aquí: Talento Humano
            debe revisarla y aprobarla desde Acciones de Personal.
          </Alert>
        )}
      </Stack>
    </FormModal>
  )
}
