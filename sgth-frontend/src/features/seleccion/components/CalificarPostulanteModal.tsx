'use client'

import { useMemo } from 'react'
import { Alert, Card, Divider, Group, Progress, ScrollArea, Stack, Text } from '@mantine/core'
import { IconInfoCircle } from '@tabler/icons-react'
import { useForm, useWatch } from 'react-hook-form'
import { DataState, ModalFooter, SgthModal, StatusBadge, notificar } from '@/components/ui'
import { erroresDeCampo } from '@/lib/erroresDeCampo'
import { useCalificaciones, useCriterios, useGuardarCalificaciones } from '../hooks/useCriterio'
import {
  aItems, claveCriterio, puntajeCriterio, valoresIniciales, type CalificacionForm,
} from './calificacion/calificacion'
import { SeccionCalificacion } from './calificacion/SeccionCalificacion'

/**
 * Lo único que el modal necesita saber de la persona: su identidad, para
 * mostrarla en la cabecera, y su `id`, para guardar la calificación.
 *
 * Se tipa así de estrecho a propósito: los aspirantes de Reclutamiento Express
 * llegan por otro endpoint con una forma distinta aunque sean la misma
 * entidad. Ambos cumplen esta interfaz sin forzar el tipo.
 */
export interface PostulanteCalificable {
  id:                number
  cedula:            string
  nombres:           string
  segundo_nombre?:   string | null
  apellidos:         string
  segundo_apellido?: string | null
  correo:            string
}

interface Props {
  opened:         boolean
  onClose:        () => void
  postulante:     PostulanteCalificable | null
  convocatoriaId: number
}

const APROBATORIO = 70

/**
 * La calificación por criterios (2026-10-05): con React Hook Form en vez de un
 * `useState` sembrado a mano en el render, y cada 422 del backend debajo del
 * criterio que lo causó. Pasó de 463 líneas a este modal y sus piezas.
 */
export function CalificarPostulanteModal({ opened, onClose, postulante, convocatoriaId }: Props) {
  const criteriosQ = useCriterios(opened ? convocatoriaId : null)
  const previasQ = useCalificaciones(opened ? convocatoriaId : null, opened ? postulante?.id ?? null : null)
  const guardar = useGuardarCalificaciones(convocatoriaId, postulante?.id ?? 0)
  const criterios = useMemo(() => criteriosQ.data ?? [], [criteriosQ.data])

  const listo = !criteriosQ.isLoading && !previasQ.isLoading
  // `values` resiembra el formulario al abrirlo sobre otro candidato. Las
  // consultas comparten estructura, así que un refresco no pisa lo tecleado.
  const iniciales = useMemo(
    () => valoresIniciales(criterios, previasQ.data?.calificaciones),
    [criterios, previasQ.data],
  )
  const { control, handleSubmit, setError } = useForm<CalificacionForm>({ values: listo ? iniciales : undefined })
  const valores = useWatch({ control }) as CalificacionForm

  if (!postulante) return null

  const nombre = [postulante.apellidos, postulante.segundo_apellido, postulante.nombres, postulante.segundo_nombre]
    .filter(Boolean).join(' ')
  const meritos = criterios.filter(c => c.seccion === 'meritos')
  const oposicion = criterios.filter(c => c.seccion === 'oposicion')
  const total = criterios.reduce((s, c) => s + puntajeCriterio(c, valores?.[claveCriterio(c.id)]), 0)
  const aprueba = total >= APROBATORIO

  const enviar = (v: CalificacionForm) => {
    const items = aItems(criterios, v)
    guardar.mutateAsync(items).then(onClose).catch((e) => {
      // `calificaciones.N.campo` es el criterio N de lo enviado.
      const errores = erroresDeCampo(e) ?? {}
      const sueltos: string[] = []
      for (const [campo, mensaje] of Object.entries(errores)) {
        const n = /^calificaciones\.(\d+)\./.exec(campo)?.[1]
        const item = n !== undefined ? items[Number(n)] : undefined
        if (item) setError(claveCriterio(item.criterio_id), { type: 'server', message: mensaje })
        else sueltos.push(mensaje)
      }
      if (sueltos.length) notificar.error('No se pudo guardar la calificación', sueltos.join(' '))
    })
  }

  return (
    <SgthModal opened={opened} onClose={onClose} title="Calificar candidato" size="xl">
      <Stack gap="md">
        <Card withBorder radius="md" p="sm">
          <Group justify="space-between">
            <Stack gap={2}>
              <Text size="sm" fw={600}>{nombre}</Text>
              <Text size="xs" c="dimmed">{postulante.cedula} · {postulante.correo}</Text>
            </Stack>
            <StatusBadge tone={aprueba ? 'success' : 'danger'} size="lg">{total.toFixed(2)} / 100 pts</StatusBadge>
          </Group>
          <Progress value={total} color={aprueba ? undefined : 'red'} size="sm" radius="xl" mt="xs" />
          <Text size="xs" c={aprueba ? 'emerald' : 'red'} mt={4}>
            {aprueba ? `Aprueba (≥ ${APROBATORIO} puntos)` : `No aprueba (< ${APROBATORIO} puntos)`}
          </Text>
        </Card>

        <DataState
          loading={!listo}
          error={criteriosQ.error ?? previasQ.error}
          errorTitle="No se pudieron cargar los criterios"
          empty={criterios.length === 0}
          emptyProps={{
            icon: IconInfoCircle,
            title: 'Sin criterios de evaluación',
            description: 'Configúrelos primero en la pestaña «Criterios de evaluación».',
          }}
          skeletonRows={3}
        >
          <ScrollArea h={450} offsetScrollbars>
            <Stack gap="md" pr="sm">
              <SeccionCalificacion titulo="Méritos" criterios={meritos} valores={valores ?? {}} control={control} />
              {meritos.length > 0 && oposicion.length > 0 && <Divider />}
              <SeccionCalificacion titulo="Oposición" criterios={oposicion} valores={valores ?? {}} control={control} />
            </Stack>
          </ScrollArea>
        </DataState>

        {criterios.length > 0 && (
          <Alert color="ocean" variant="light" icon={<IconInfoCircle size={16} />} py={6}>
            <Text size="xs">Se califican todos los criterios. Un checklist sin marcas vale 0.</Text>
          </Alert>
        )}
      </Stack>
      <ModalFooter
        onCancel={onClose}
        submitLabel="Guardar calificación"
        submitting={guardar.isPending}
        submitDisabled={criterios.length === 0}
        onSubmit={handleSubmit(enviar)}
      />
    </SgthModal>
  )
}
