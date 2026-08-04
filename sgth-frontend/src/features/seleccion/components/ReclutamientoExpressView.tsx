'use client'

import { useState } from 'react'
import {
  Alert, Badge, Box, Button, Card, Group, SegmentedControl, Select,
  SimpleGrid, Skeleton, Stack, Text,
} from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconInfoCircle, IconPlus, IconUsers } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useAniosExpress, useResumenExpress } from '../hooks/useExpress'
import { AspirantesExpressDrawer } from './AspirantesExpressDrawer'
import { InscribirPostulanteModal } from './InscribirPostulanteModal'
import type { FiltroAnios, TarjetaExpress } from '../services/expressService'

type ModoFiltro = 'todos' | 'anio' | 'rango'

function Metrica({ etiqueta, valor, color }: { etiqueta: string; valor: number; color?: string }) {
  return (
    <Box>
      <Text size="xl" fw={700} c={color}>{valor}</Text>
      <Text size="xs" c="dimmed" tt="uppercase">{etiqueta}</Text>
    </Box>
  )
}

/**
 * Reclutamiento express: cuatro modalidades permanentes. A diferencia del
 * concurso formal, no hay una convocatoria por proceso — los aspirantes se
 * agregan sueltos y el año sale de su fecha de inscripción, que es lo que
 * filtra esta pantalla.
 */
export function ReclutamientoExpressView() {
  const contained = useContainedInput()

  const [modo, setModo] = useState<ModoFiltro>('todos')
  const [anio, setAnio] = useState<string | null>(null)
  const [desde, setDesde] = useState<string | null>(null)
  const [hasta, setHasta] = useState<string | null>(null)

  const [seleccionado, setSeleccionado] = useState<TarjetaExpress | null>(null)
  const [estadoAspirantes, setEstadoAspirantes] = useState<string | null>(null)
  const [drawerOpened, { open: abrirDrawer, close: cerrarDrawer }] = useDisclosure(false)
  const [inscribirOpened, { open: abrirInscribir, close: cerrarInscribir }] = useDisclosure(false)

  const filtro: FiltroAnios =
    modo === 'anio' && anio ? { anio: Number(anio) }
      : modo === 'rango'
        ? {
          ...(desde ? { anio_desde: Number(desde) } : {}),
          ...(hasta ? { anio_hasta: Number(hasta) } : {}),
        }
        : {}

  const { data: anios = [] } = useAniosExpress()
  const { data: resumen, isLoading } = useResumenExpress(filtro)

  const opcionesAnio = anios.map((a) => ({ value: String(a), label: String(a) }))

  const verAspirantes = (tarjeta: TarjetaExpress) => {
    setSeleccionado(tarjeta)
    setEstadoAspirantes(null)
    abrirDrawer()
  }

  const inscribirEn = (tarjeta: TarjetaExpress) => {
    setSeleccionado(tarjeta)
    abrirInscribir()
  }

  return (
    <Box>
      <Alert variant="light" color="blue" icon={<IconInfoCircle size={16} />} mb="md">
        Estas cuatro modalidades no pasan por concurso de méritos y oposición, pero
        cada aspirante sí se evalúa individualmente y, si aprueba, se despacha al
        dispensario médico para la ficha ocupacional.
      </Alert>

      <Group mb="md" align="flex-end">
        <SegmentedControl
          value={modo}
          onChange={(v) => setModo(v as ModoFiltro)}
          data={[
            { value: 'todos', label: 'Todos los años' },
            { value: 'anio', label: 'Un año' },
            { value: 'rango', label: 'Rango de años' },
          ]}
        />

        {modo === 'anio' && (
          <Select
            label="Año"
            placeholder="Seleccione"
            data={opcionesAnio}
            value={anio}
            onChange={setAnio}
            clearable
            {...contained}
            style={{ minWidth: 140 }}
          />
        )}

        {modo === 'rango' && (
          <>
            <Select
              label="Desde"
              placeholder="Año"
              data={opcionesAnio}
              value={desde}
              onChange={setDesde}
              clearable
              {...contained}
              style={{ minWidth: 130 }}
            />
            <Select
              label="Hasta"
              placeholder="Año"
              data={opcionesAnio}
              value={hasta}
              onChange={setHasta}
              clearable
              {...contained}
              style={{ minWidth: 130 }}
            />
          </>
        )}
      </Group>

      {isLoading ? (
        <SimpleGrid cols={{ base: 1, sm: 2 }}>
          {[0, 1, 2, 3].map((i) => <Skeleton key={i} height={200} radius="md" />)}
        </SimpleGrid>
      ) : (
        <SimpleGrid cols={{ base: 1, sm: 2 }} spacing="md">
          {(resumen?.contenedores ?? []).map((c) => (
            <Card key={c.convocatoria_id} withBorder radius="md" padding="md">
              <Stack gap="sm">
                <div>
                  <Group justify="space-between" align="flex-start">
                    <Text fw={600}>{c.titulo}</Text>
                    <Badge variant="light" color="gray" size="sm">{c.codigo}</Badge>
                  </Group>
                  <Text size="xs" c="dimmed" lineClamp={2} mt={4}>
                    {c.descripcion}
                  </Text>
                </div>

                <Group gap="xl">
                  <Metrica etiqueta="Aspirantes" valor={c.total_aspirantes} />
                  <Metrica etiqueta="Aprobados" valor={c.aprobados} color="emerald" />
                  <Metrica etiqueta="Incorporados" valor={c.incorporados} color="violet" />
                  <Metrica etiqueta="Pendientes" valor={c.pendientes} color="blue" />
                </Group>

                <Group>
                  <Button
                    size="xs"
                    variant="light"
                    color="emerald"
                    leftSection={<IconPlus size={14} />}
                    onClick={() => inscribirEn(c)}
                  >
                    Agregar aspirante
                  </Button>
                  <Button
                    size="xs"
                    variant="subtle"
                    leftSection={<IconUsers size={14} />}
                    onClick={() => verAspirantes(c)}
                    disabled={c.total_aspirantes === 0}
                  >
                    Ver aspirantes
                  </Button>
                </Group>
              </Stack>
            </Card>
          ))}
        </SimpleGrid>
      )}

      <AspirantesExpressDrawer
        opened={drawerOpened}
        onClose={cerrarDrawer}
        contenedor={seleccionado}
        filtro={filtro}
        estado={estadoAspirantes}
        onEstadoChange={setEstadoAspirantes}
      />

      {seleccionado && (
        <InscribirPostulanteModal
          opened={inscribirOpened}
          onClose={cerrarInscribir}
          convocatoriaId={seleccionado.convocatoria_id}
          requierePuesto
        />
      )}
    </Box>
  )
}
