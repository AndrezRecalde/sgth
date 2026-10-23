'use client'

import { useState } from 'react'
import { SegmentedControl, Stack, Text, Textarea } from '@mantine/core'
import { Controller, useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal, ModalFooter, SgthModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { formatFechaMes } from '@/lib/fecha'
import { useAuthStore } from '@/store/auth.store'
import { useCrearTurno } from '../../hooks/useAgenda'
import { useRevisarResultado } from '../../hooks/useResultadoMedico'
import { revisionResultadoSchema, type RevisionResultadoFormData } from '../../schemas/revisionResultado.schema'
import { INTERPRETACION, type ResultadoMedico } from '../../services/resultadoMedicoService'

interface Props {
  /** El resultado que se revisa; sin él, el modal no se muestra. */
  resultado: ResultadoMedico | null
  onClose:   () => void
}

/**
 * Leer un resultado (D3): normal, alterado o crítico, con un comentario. Si no
 * salió normal, ofrece darle al paciente un turno de control hoy con quien lo
 * revisó, por el flujo de turnos de siempre.
 */
export function RevisarResultadoModal({ resultado, onClose }: Props) {
  return resultado ? <Revision key={resultado.id} resultado={resultado} onClose={onClose} /> : null
}

function Revision({ resultado, onClose }: { resultado: ResultadoMedico; onClose: () => void }) {
  const contained = useContainedInput()
  const revisar = useRevisarResultado()
  const crearTurno = useCrearTurno()
  const { usuario, hasRole } = useAuthStore()
  const [control, setControl] = useState<'alterado' | 'critico' | null>(null)

  const { control: form, register, handleSubmit, setError, formState: { errors } } = useForm<RevisionResultadoFormData>({
    resolver: zodResolver(revisionResultadoSchema),
    defaultValues: {
      interpretacion:    resultado.interpretacion ?? 'normal',
      comentario_medico: resultado.comentario_medico ?? '',
    },
  })
  const interpretacion = useWatch({ control: form, name: 'interpretacion' })

  const paciente = resultado.historia_clinica
  const puedeDarTurno = !!usuario && !!(paciente?.servidor_id || paciente?.carga_familiar_id)

  const enviar = (v: RevisionResultadoFormData) =>
    revisar.mutateAsync({
      id: resultado.id,
      data: { interpretacion: v.interpretacion, comentario_medico: v.comentario_medico.trim() || null },
    }).then(() => {
      if (v.interpretacion !== 'normal' && puedeDarTurno) setControl(v.interpretacion)
      else onClose()
    }).catch((e) => erroresAlFormulario(e, setError, Object.keys(revisionResultadoSchema.shape), 'No se pudo guardar la revisión'))

  const darTurno = () => {
    if (!usuario || !control) return
    crearTurno.mutate({
      medico_id:         usuario.id,
      servidor_id:       paciente?.servidor_id ?? null,
      carga_familiar_id: paciente?.carga_familiar_id ?? null,
      tipo_atencion:     hasRole('medico') ? 'medicina_general' : 'odontologia',
      motivo_solicitud:  `Control por resultado ${INTERPRETACION[control].label.toLowerCase()}: ${resultado.descripcion}`.slice(0, 500),
      requiere_triaje:   true,
    }, { onSuccess: onClose })
  }

  if (control) {
    return (
      <SgthModal opened onClose={onClose} title="Turno de control" size="sm">
        <Stack gap="xs">
          <Text size="sm">
            El resultado quedó como <Text span fw={600}>{INTERPRETACION[control].label.toLowerCase()}</Text>.
            ¿Le da al paciente un turno de control hoy, con usted?
          </Text>
          <Text size="xs" c="dimmed">Entra en la cola de hoy, como cualquier otro turno, y pasa por el triaje.</Text>
        </Stack>
        <ModalFooter onCancel={onClose} cancelLabel="Ahora no" submitLabel="Dar turno de control"
          submitting={crearTurno.isPending} onSubmit={darTurno} />
      </SgthModal>
    )
  }

  return (
    <FormModal opened onClose={onClose} title="Revisar resultado" size="md"
      onSubmit={handleSubmit(enviar)} submitLabel="Guardar revisión" submitting={revisar.isPending}>
      <Stack gap="sm">
        <Stack gap={0}>
          <Text size="sm" fw={500}>{resultado.descripcion}</Text>
          <Text size="xs" c="dimmed">
            {formatFechaMes(resultado.fecha_resultado)}
            {resultado.orden_examen ? ` · Orden ${resultado.orden_examen.folio}` : ''}
          </Text>
        </Stack>

        <Stack gap={4}>
          <Text size="xs" fw={500}>Interpretación</Text>
          <Controller name="interpretacion" control={form} render={({ field }) => (
            <SegmentedControl fullWidth value={field.value}
              onChange={(v) => field.onChange(v === 'alterado' || v === 'critico' ? v : 'normal')}
              data={[
                { value: 'normal', label: 'Normal' },
                { value: 'alterado', label: 'Alterado' },
                { value: 'critico', label: 'Crítico' },
              ]} />
          )} />
        </Stack>

        <Textarea label="Comentario" autosize minRows={3} maxLength={2000} {...contained}
          withAsterisk={interpretacion !== 'normal'}
          placeholder={interpretacion === 'normal' ? 'Opcional' : 'Qué valor está fuera de rango y qué se hará'}
          description={interpretacion === 'critico'
            ? 'Si lo pidió otro médico, le queda fijo en «Mi jornada» hasta que lo vea.'
            : undefined}
          {...register('comentario_medico')} error={errors.comentario_medico?.message} />
      </Stack>
    </FormModal>
  )
}
