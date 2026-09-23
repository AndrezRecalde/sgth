'use client'

import { useState } from 'react'
import {
  Alert, Select, Stack, Text, TextInput, Textarea,
} from '@mantine/core'
import { ModalFooter, SgthModal } from '@/components/ui'
import { DatePickerInput } from '@mantine/dates'
import { IconAlertTriangle } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useDisciplinarioMutations } from '../hooks/useDisciplinarioMutations'
import {
  ESTADO_VISTO_BUENO_LABELS,
  TRANSICIONES_VISTO_BUENO,
  nombreServidor,
} from '../utils/etiquetas'
import type { EstadoVistoBueno, VistoBueno } from '@/types/api'
import { fromDateValueOrNull } from '@/lib/fecha'

interface Props {
  opened: boolean
  onClose: () => void
  tramite: VistoBueno | null
}

export function TransicionarVistoBuenoModal({ opened, onClose, tramite }: Props) {
  return (
    <SgthModal
      opened={opened}
      onClose={onClose}
      title="Actualizar trámite de visto bueno"
      size="lg"
    >
      {/* El formulario se remonta al cambiar de trámite (key), así arranca con
          los valores de ese trámite sin resetear estado desde un efecto. */}
      {tramite && (
        <FormularioTransicion
          key={tramite.id}
          tramite={tramite}
          onClose={onClose}
        />
      )}
    </SgthModal>
  )
}

function FormularioTransicion({
  tramite,
  onClose,
}: {
  tramite: VistoBueno
  onClose: () => void
}) {
  const contained = useContainedInput()
  const { transicionarVistoBueno } = useDisciplinarioMutations()

  const [destino, setDestino] = useState<EstadoVistoBueno | null>(null)
  const [resolucion, setResolucion] = useState('')
  // El selector de Mantine v9 devuelve una CADENA `YYYY-MM-DD` en cuanto se
  // elige una fecha; solo el valor inicial es un `Date`. El estado admite las
  // dos formas, que es lo que `fromDateValue` sabe leer.
  const [fecha, setFecha] = useState<Date | string | null>(new Date())
  const [numeroTramite, setNumeroTramite] = useState(tramite.numero_tramite_mdt ?? '')
  const [inspector, setInspector] = useState(tramite.inspector_nombre ?? '')
  const [error, setError] = useState<string | null>(null)

  const opciones = TRANSICIONES_VISTO_BUENO[tramite.estado].map((e) => ({
    value: e,
    label: ESTADO_VISTO_BUENO_LABELS[e],
  }))

  const esResolucion = destino === 'concedido' || destino === 'negado'
  const esNotificacion = destino === 'notificado'

  const submit = () => {
    if (!destino) return setError('Seleccione el nuevo estado del trámite.')
    if (esResolucion && resolucion.trim().length < 5) {
      return setError('Registre el detalle de la resolución del Inspector.')
    }

    setError(null)

    transicionarVistoBueno
      .mutateAsync({
        id: tramite.id,
        data: {
          estado: destino,
          resolucion_detalle: esResolucion ? resolucion.trim() : null,
          fecha_resolucion: esResolucion ? fromDateValueOrNull(fecha) : null,
          fecha_notificacion: esNotificacion ? fromDateValueOrNull(fecha) : null,
          numero_tramite_mdt: numeroTramite.trim() || null,
          inspector_nombre: inspector.trim() || null,
        },
      })
      .then(onClose)
      .catch(() => {})
  }

  return (
    <Stack gap="sm">
      <Text size="sm" c="dimmed">
        {nombreServidor(tramite.servidor)} — estado actual:{' '}
        <strong>{ESTADO_VISTO_BUENO_LABELS[tramite.estado]}</strong>
      </Text>

      {opciones.length === 0 ? (
        <Alert variant="light" color="slate">
          Este trámite ya está en un estado terminal y no admite más cambios.
        </Alert>
      ) : (
        <>
          <Select
            label="Nuevo estado"
            placeholder="Seleccione"
            data={opciones}
            value={destino}
            onChange={(v) => setDestino(v as EstadoVistoBueno | null)}
            {...contained}
          />

          {(esResolucion || esNotificacion) && (
            <DatePickerInput
              label={esResolucion ? 'Fecha de la resolución' : 'Fecha de notificación'}
              value={fecha}
              onChange={(v) => setFecha(v)}
              valueFormat="DD/MM/YYYY"
              {...contained}
            />
          )}

          {esNotificacion && (
            <>
              <TextInput
                label="Número de trámite del Ministerio del Trabajo"
                placeholder="Ej: MDT-VB-2026-0042"
                value={numeroTramite}
                onChange={(e) => setNumeroTramite(e.currentTarget.value)}
                {...contained}
              />
              <TextInput
                label="Inspector"
                placeholder="Nombre del Inspector del Trabajo"
                value={inspector}
                onChange={(e) => setInspector(e.currentTarget.value)}
                {...contained}
              />
            </>
          )}

          {esResolucion && (
            <Textarea
              label="Detalle de la resolución del Inspector"
              placeholder="Transcriba o resuma lo resuelto por el Inspector del Trabajo"
              rows={4}
              value={resolucion}
              onChange={(e) => setResolucion(e.currentTarget.value)}
              {...contained}
            />
          )}

          {destino === 'concedido' && (
            <Alert variant="light" color="amber" icon={<IconAlertTriangle size={16} />}>
              Al conceder el visto bueno se generará una Cesación de Funciones en
              borrador. El vínculo del trabajador no se cierra aquí: Talento Humano
              debe revisarla y aprobarla desde Acciones de Personal.
            </Alert>
          )}

          {error && <Alert variant="light" color="red">{error}</Alert>}
        </>
      )}

      <ModalFooter
        onCancel={onClose}
        cancelLabel="Cerrar"
        submitLabel="Actualizar trámite"
        submitting={transicionarVistoBueno.isPending}
        sinPrincipal={opciones.length === 0}
        onSubmit={submit}
      />
    </Stack>
  )
}
