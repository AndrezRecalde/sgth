'use client'

import {
  Modal, Stack, Text, Group, Badge,
  NumberInput, Button, Alert, Card,
  ThemeIcon, Grid,
} from '@mantine/core'
import {
  IconPill, IconCheck, IconAlertTriangle,
} from '@tabler/icons-react'
import { useState } from 'react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { useDespacharReceta } from '../hooks/useReceta'
import { esItemExterno, nombreDeItem } from '../services/recetaService'
import type { RecetaMedica, ItemReceta } from '../services/recetaService'

interface Props {
  opened:  boolean
  onClose: () => void
  receta:  RecetaMedica | null
}

function getNombrePaciente(receta: RecetaMedica): string {
  const historia = receta.consulta_medica?.historia_clinica
  if (historia?.servidor) {
    return `${historia.servidor.nombre} ${historia.servidor.apellido}`
  }
  if (historia?.carga_familiar) {
    return `${historia.carga_familiar.nombres} ${historia.carga_familiar.apellidos}`
  }
  return '—'
}

/**
 * Cuántas unidades de este ítem se pueden entregar hoy.
 *
 * Sale de los lotes vigentes, no de la fecha de la ficha. Con lotes mezclados
 * esa fecha era la de la última entrada, así que bloqueaba el ítem entero
 * habiendo existencias buenas —o lo dejaba pasar habiendo un lote viejo ya
 * vencido—. Si el backend no manda el dato, se cae al stock total, que es lo
 * que había antes.
 */
function entregable(item: ItemReceta): number {
  return item.inventario?.stock_despachable
    ?? item.inventario?.stock_actual
    ?? 0
}

const ESTADO_ITEM: Record<string, { label: string; color: string }> = {
  pendiente:         { label: 'Pendiente',  color: 'gray'   },
  despachado_parcial:{ label: 'Parcial',    color: 'orange' },
  despachado_completo:{label: 'Completo',   color: 'emerald'},
}

export function DespacharRecetaModal({
  opened, onClose, receta,
}: Props) {
  const contained  = useContainedInput()
  const despachar  = useDespacharReceta()
  const { isMobile } = useMobileBreakpoint()

  const [cantidades, setCantidades] = useState<Record<number, number>>({})

  // Las cantidades arrancan en lo que falta por despachar y a partir de ahí
  // las edita quien despacha. Se resiembran al abrir el modal sobre otra
  // receta, ajustando el estado durante el render en vez de en un efecto.
  const semilla = receta && opened ? String(receta.id) : null
  const [semillaAplicada, setSemillaAplicada] = useState<string | null>(null)

  if (semilla !== semillaAplicada) {
    setSemillaAplicada(semilla)
    const init: Record<number, number> = {}
    receta?.items.filter(item => ! esItemExterno(item)).forEach(item => {
      const faltante = item.cantidad_prescrita -
        (item.cantidad_despachada ?? 0)
      // Arranca en lo que falta, pero sin pasarse de lo que hay entregable:
      // proponer una cantidad que el mostrador va a rechazar no ayuda a nadie.
      init[item.id!] = Math.max(0, Math.min(faltante, entregable(item)))
    })
    setCantidades(init)
  }

  if (!receta) return null

  const nombrePaciente = getNombrePaciente(receta)

  // Lo que la farmacia no maneja va aparte: no se despacha ni se cuenta como
  // trabajo pendiente del mostrador, pero tiene que verse, porque es
  // justamente lo que hay que decirle al paciente que compre fuera.
  const itemsExternos = receta.items.filter(esItemExterno)

  const itemsPendientes = receta.items.filter(
    item => ! esItemExterno(item) && item.estado !== 'despachado_completo'
  )

  const handleDespachar = () => {
    const items = itemsPendientes
      .filter(item => (cantidades[item.id!] ?? 0) > 0)
      .map(item => ({
        item_receta_id: item.id!,
        cantidad:       cantidades[item.id!] ?? 0,
      }))

    if (items.length === 0) return

    despachar.mutate(
      { id: receta.id, data: { items } },
      { onSuccess: () => {
        setCantidades({})
        onClose()
      }}
    )
  }

  const handleClose = () => {
    setCantidades({})
    onClose()
  }

  return (
    // A pantalla completa en el teléfono, como el de emitir: despachar es
    // rellenar cantidades ítem por ítem, y en una ventana flotante de 375px eso
    // ocurre dentro de un recuadro que apenas deja ver dos medicamentos.
    <Modal
      opened={opened}
      onClose={handleClose}
      title="Despachar receta médica"
      size="lg"
      fullScreen={isMobile}
      radius={isMobile ? 0 : 'xl'}
    >
      <Stack gap="sm">
        <Card withBorder radius="md" p="sm">
          <Group justify="space-between">
            <Stack gap={0}>
              <Text size="sm" fw={600}>{nombrePaciente}</Text>
              <Text size="xs" c="dimmed" ff="monospace">
                Receta emitida:{' '}
                {new Date(receta.fecha_emision).toLocaleDateString('es-EC', {
                  day: '2-digit', month: 'short', year: 'numeric',
                })}
              </Text>
            </Stack>
            <Badge
              variant="light"
              color={receta.estado === 'pendiente' ? 'orange' : 'blue'}
            >
              {receta.estado.replace(/_/g, ' ')}
            </Badge>
          </Group>
          {receta.indicaciones_generales && (
            <Text size="xs" c="dimmed" mt="xs">
              {receta.indicaciones_generales}
            </Text>
          )}
        </Card>

        {/* Antes que la lista de entrega: quien atiende el mostrador tiene que
            poder decirle al paciente, de entrada, qué no va a llevarse de
            aquí. */}
        {itemsExternos.length > 0 && (
          <Alert
            icon={<IconAlertTriangle size={14} />}
            color="amber"
            variant="light"
            title={
              itemsExternos.length === 1
                ? 'Un medicamento no lo maneja la farmacia'
                : `${itemsExternos.length} medicamentos no los maneja la farmacia`
            }
          >
            <Stack gap={4} mt={4}>
              {itemsExternos.map(item => (
                <Text size="xs" key={item.id}>
                  <Text span fw={600} c="inherit">
                    {nombreDeItem(item)}
                  </Text>
                  {' — '}
                  {item.cantidad_prescrita} unid. · {item.dosis} ·{' '}
                  {item.frecuencia} · {item.duracion}
                </Text>
              ))}
              <Text size="xs" c="dimmed">
                {itemsExternos.length === 1
                  ? 'El paciente lo adquiere fuera del dispensario. No se despacha ni descuenta existencias.'
                  : 'El paciente los adquiere fuera del dispensario. No se despachan ni descuentan existencias.'}
              </Text>
            </Stack>
          </Alert>
        )}

        <Text size="sm" fw={500}>
          Medicamentos a despachar
        </Text>

        {itemsPendientes.length === 0 ? (
          <Alert color="emerald" variant="light">
            <Text size="sm">
              {itemsExternos.length === receta.items.length
                ? 'Esta receta no tiene nada que la farmacia pueda entregar.'
                : 'Todos los ítems de esta receta ya fueron despachados.'}
            </Text>
          </Alert>
        ) : (
          <Stack gap="sm">
            {itemsPendientes.map((item) => {
              const faltante = item.cantidad_prescrita -
                (item.cantidad_despachada ?? 0)
              const estadoItem = ESTADO_ITEM[item.estado ?? 'pendiente']
                ?? { label: item.estado, color: 'gray' }
              const hayEntregable = entregable(item)
              const caducadas = item.inventario?.stock_caducado ?? 0
              const tope = Math.min(faltante, hayEntregable)
              return (
                <Card key={item.id} withBorder radius="md" p="sm">
                  <Stack gap="xs">
                    {hayEntregable === 0 ? (
                      <Alert
                        icon={<IconAlertTriangle size={14} />}
                        color="red"
                        variant="light"
                        p="xs"
                      >
                        <Text size="xs">
                          {caducadas > 0
                            ? `No hay existencias entregables: las ${caducadas}
                               unidades que quedan están caducadas y deben
                               darse de baja desde Inventario.`
                            : 'No quedan existencias de este medicamento.'}
                        </Text>
                      </Alert>
                    ) : caducadas > 0 && (
                      // Hay de lo bueno y de lo vencido a la vez. Antes esto
                      // bloqueaba el ítem entero; ahora se entrega lo bueno y
                      // se avisa de lo otro, que hay que dar de baja.
                      <Alert
                        icon={<IconAlertTriangle size={14} />}
                        color="orange"
                        variant="light"
                        p="xs"
                      >
                        <Text size="xs">
                          Se entregarán {hayEntregable} unid. sin caducar. Otras{' '}
                          {caducadas} están vencidas y deben darse de baja.
                        </Text>
                      </Alert>
                    )}
                    <Group justify="space-between" wrap="nowrap">
                      <Group gap="xs" wrap="nowrap">
                        <ThemeIcon
                          size="sm" color="blue" variant="light"
                        >
                          <IconPill size={12} />
                        </ThemeIcon>
                        <Stack gap={0}>
                          <Text size="sm" fw={500}>
                            {nombreDeItem(item)}
                          </Text>
                          <Text size="xs" c="dimmed">
                            {item.inventario?.concentracion ?? ''}
                            {' · '}
                            {item.dosis} · {item.frecuencia}
                          </Text>
                        </Stack>
                      </Group>
                      <Badge
                        size="xs"
                        variant="light"
                        color={estadoItem.color}
                      >
                        {estadoItem.label}
                      </Badge>
                    </Group>

                    {/* Los tres números caben en una fila hasta en el teléfono;
                        el campo baja a ancho completo. Con `Group grow`, a
                        375px los cuatro se repartían el ancho a partes iguales
                        y la etiqueta «Cantidad a despachar» quedaba cortada
                        justo en el único elemento con el que hay que
                        interactuar. */}
                    <Grid align="flex-end" gap="sm">
                      <Grid.Col span={{ base: 4, sm: 3 }}>
                        <Text size="xs" c="dimmed">Prescrito</Text>
                        <Text size="sm" fw={500}>
                          {item.cantidad_prescrita}
                        </Text>
                      </Grid.Col>
                      <Grid.Col span={{ base: 4, sm: 3 }}>
                        <Text size="xs" c="dimmed">Ya despachado</Text>
                        <Text size="sm">
                          {item.cantidad_despachada ?? 0}
                        </Text>
                      </Grid.Col>
                      <Grid.Col span={{ base: 4, sm: 2 }}>
                        <Text size="xs" c="dimmed">Faltante</Text>
                        <Text size="sm" fw={500} c="orange">
                          {faltante}
                        </Text>
                      </Grid.Col>
                      <Grid.Col span={{ base: 12, sm: 4 }}>
                        <NumberInput
                          label="Cantidad a despachar"
                          size="xs"
                          min={0}
                          max={tope}
                          disabled={hayEntregable === 0}
                          {...contained}
                          value={cantidades[item.id!] ?? 0}
                          onChange={(v) => setCantidades(prev => ({
                            ...prev,
                            [item.id!]: Math.min(Number(v) || 0, tope),
                          }))}
                        />
                      </Grid.Col>
                    </Grid>
                  </Stack>
                </Card>
              )
            })}
          </Stack>
        )}

        <Group justify="flex-end" mt="sm">
          <Button variant="default" onClick={handleClose}>
            Cancelar
          </Button>
          <Button
            color="emerald"
            leftSection={<IconCheck size={14} />}
            loading={despachar.isPending}
            disabled={
              itemsPendientes.length === 0 ||
              itemsPendientes.every(
                item => (cantidades[item.id!] ?? 0) === 0
              )
            }
            onClick={handleDespachar}
          >
            Confirmar despacho
          </Button>
        </Group>
      </Stack>
    </Modal>
  )
}
