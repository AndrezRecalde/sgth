'use client'

import { useState } from 'react'
import {
  Alert, Button, Group, Modal, Select, Stack, Text, TextInput, Textarea,
} from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { IconAlertTriangle } from '@tabler/icons-react'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useDisciplinarioMutations } from '../hooks/useDisciplinarioMutations'
import {
  ESTADO_VISTO_BUENO_LABELS,
  TRANSICIONES_VISTO_BUENO,
  nombreServidor,
} from '../utils/etiquetas'
import type { EstadoVistoBueno, VistoBueno } from '@/types/api'

interface Props {
  opened: boolean
  onClose: () => void
  tramite: VistoBueno | null
}

export function TransicionarVistoBuenoModal({ opened, onClose, tramite }: Props) {
  const { isMobile } = useMobileBreakpoint()

  return (
    <Modal
      opened={opened}
      onClose={onClose}
      title="Actualizar trámite de visto bueno"
      size="lg"
      fullScreen={isMobile}
      radius={isMobile ? 0 : 'xl'}
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
    </Modal>
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
  const [fecha, setFecha] = useState<Date | null>(new Date())
  const [numeroTramite, setNumeroTramite] = useState(tramite.numero_tramite_mdt ?? '')
  const [inspector, setInspector] = useState(tramite.inspector_nombre ?? '')
  const [error, setError] = useState<string | null>(null)

  const opciones = TRANSICIONES_VISTO_BUENO[tramite.estado].map((e) => ({
    value: e,
    label: ESTADO_VISTO_BUENO_LABELS[e],
  }))

  const esResolucion = destino === 'concedido' || destino === 'negado'
  const esNotificacion = destino === 'notificado'

  const toIso = (d: Date | null) =>
    d ? `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}` : null

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
          fecha_resolucion: esResolucion ? toIso(fecha) : null,
          fecha_notificacion: esNotificacion ? toIso(fecha) : null,
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
        <Alert variant="light" color="gray">
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
              onChange={(v) => setFecha(v as Date | null)}
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
            <Alert variant="light" color="orange" icon={<IconAlertTriangle size={16} />}>
              Al conceder el visto bueno se generará una Cesación de Funciones en
              borrador. El vínculo del trabajador no se cierra aquí: Talento Humano
              debe revisarla y aprobarla desde Acciones de Personal.
            </Alert>
          )}

          {error && <Alert variant="light" color="red">{error}</Alert>}
        </>
      )}

      <Group justify="flex-end" mt="md">
        <Button variant="default" onClick={onClose}>Cerrar</Button>
        {opciones.length > 0 && (
          <Button
            color="emerald"
            loading={transicionarVistoBueno.isPending}
            onClick={submit}
          >
            Actualizar trámite
          </Button>
        )}
      </Group>
    </Stack>
  )
}
