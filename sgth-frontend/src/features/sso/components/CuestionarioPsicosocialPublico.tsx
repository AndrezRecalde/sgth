'use client'

import { useMemo, useState } from 'react'
import {
  Box, Container, Stack, Stepper, Title, Text, Group, Button,
  Skeleton, Alert, Paper,
} from '@mantine/core'
import { IconAlertCircle, IconCircleCheck } from '@tabler/icons-react'
import { useForm, type Resolver } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useCuestionarioPsicosocial, useEnviarRespuestaPsicosocial } from '../hooks/usePsicosocial'
import { PsicosocialDatosGenerales } from './PsicosocialDatosGenerales'
import { PsicosocialDimension } from './PsicosocialDimension'
import {
  cargaUtilPsicosocial, esquemaCuestionarioPsicosocial, VALORES_INICIALES_PSICOSOCIAL,
  type CuestionarioPsicosocialFormData,
} from '../schemas/cuestionarioPsicosocial.schema'
import { getApiErrorMessage } from '@/types/api'
import { notificar } from '@/components/ui'

interface Props {
  codigo: string
}

/**
 * El cuestionario de riesgo psicosocial que responde el personal, por enlace y
 * sin sesión (Guía MDT, octubre 2018: 58 ítems en 8 dimensiones).
 *
 * Estaba capturado con cuatro `useState` y validado a mano, con una
 * notificación que decía cuántos ítems faltaban pero no cuáles. Ahora es
 * React Hook Form + Zod como el resto del sistema (regla 07), y cada hueco se
 * señala en su pregunta.
 */
export function CuestionarioPsicosocialPublico({ codigo }: Props) {
  const { data: cuestionario, isLoading, isError, error } = useCuestionarioPsicosocial(codigo)
  const enviar = useEnviarRespuestaPsicosocial(codigo)

  const [paso, setPaso] = useState(0)

  const resolver = useMemo(
    () => cuestionario
      ? zodResolver(
        esquemaCuestionarioPsicosocial(cuestionario.datos_generales_opciones),
      ) as Resolver<CuestionarioPsicosocialFormData>
      : undefined,
    [cuestionario],
  )

  const {
    control, handleSubmit, trigger, formState: { errors },
  } = useForm<CuestionarioPsicosocialFormData>({
    resolver,
    defaultValues: VALORES_INICIALES_PSICOSOCIAL,
  })

  const dimensiones = useMemo(
    () => (cuestionario ? Object.entries(cuestionario.dimensiones) : []),
    [cuestionario],
  )

  /** Los ítems de cada dimensión, en orden, derivados de las preguntas. */
  const itemsPorDimension = useMemo(() => {
    if (!cuestionario) return {} as Record<string, number[]>
    const mapa: Record<string, number[]> = {}
    for (const [numero, pregunta] of Object.entries(cuestionario.preguntas)) {
      mapa[pregunta.dimension] = mapa[pregunta.dimension] ?? []
      mapa[pregunta.dimension].push(Number(numero))
    }
    for (const clave of Object.keys(mapa)) mapa[clave].sort((a, b) => a - b)
    return mapa
  }, [cuestionario])

  if (isLoading) {
    return (
      <Container size="sm" py="xl">
        <Stack gap="md">
          <Skeleton height={40} width="60%" />
          <Skeleton height={200} radius="md" />
        </Stack>
      </Container>
    )
  }

  if (isError || !cuestionario) {
    return (
      <Container size="sm" py="xl">
        <Alert icon={<IconAlertCircle size={18} />} color="red" variant="light" title="Evaluación no disponible">
          {getApiErrorMessage(error, 'Esta evaluación no está disponible o el enlace es incorrecto.')}
        </Alert>
      </Container>
    )
  }

  if (enviar.isSuccess) {
    return (
      <Container size="sm" py="xl">
        <Paper withBorder radius="lg" p="xl">
          <Stack align="center" gap="sm">
            <IconCircleCheck size={48} color="var(--mantine-color-emerald-6)" />
            <Title order={3} ta="center">Gracias por su colaboración</Title>
            <Text ta="center" c="dimmed">
              Su respuesta fue registrada de forma anónima. Los resultados serán socializados
              oportunamente por Talento Humano.
            </Text>
          </Stack>
        </Paper>
      </Container>
    )
  }

  const esUltimoPaso = paso === dimensiones.length
  const dimensionActual = paso > 0 ? dimensiones[paso - 1] : null
  const itemsActuales = dimensionActual ? itemsPorDimension[dimensionActual[0]] ?? [] : []

  const enviarFormulario = (datos: CuestionarioPsicosocialFormData) => {
    enviar.mutate(cargaUtilPsicosocial(datos), {
      onError: (err) => notificar.error('No se pudo enviar el cuestionario', getApiErrorMessage(err)),
    })
  }

  const siguiente = async () => {
    // Solo los ítems de ESTA dimensión: validar el formulario entero marcaría
    // en rojo las que todavía no se han visto.
    const campos = itemsActuales.map((n) => `respuestas.${n}` as const)
    if (campos.length && !(await trigger(campos))) return
    setPaso(Math.min(paso + 1, dimensiones.length))
  }

  const atras = () => setPaso(Math.max(paso - 1, 0))

  return (
    <Container size="sm" py="xl">
      <form onSubmit={handleSubmit(enviarFormulario)} noValidate>
        <Stack gap="lg">
          <Box>
            <Title order={2}>Evaluación de riesgo psicosocial</Title>
            <Text size="sm" c="dimmed">
              Cuestionario anónimo y confidencial — Ministerio del Trabajo del Ecuador.
              No se solicita información que le identifique. Complete todos los ítems; no
              existen respuestas correctas o incorrectas.
            </Text>
          </Box>

          <Stepper active={paso} size="sm" iconSize={28} allowNextStepsSelect={false}>
            <Stepper.Step label="Datos generales" />
            {dimensiones.map(([clave, dimension]) => (
              <Stepper.Step key={clave} label={dimension.etiqueta} />
            ))}
          </Stepper>

          {paso === 0 && (
            <PsicosocialDatosGenerales
              opciones={cuestionario.datos_generales_opciones}
              control={control}
              errors={errors}
            />
          )}

          {dimensionActual && (
            <PsicosocialDimension
              etiqueta={dimensionActual[1].etiqueta}
              items={itemsActuales}
              preguntas={cuestionario.preguntas}
              control={control}
              errors={errors}
              trigger={trigger}
            />
          )}

          <Group justify="space-between" mt="md">
            <Button variant="default" onClick={atras} disabled={paso === 0}>
              Atrás
            </Button>
            {esUltimoPaso ? (
              <Button type="submit" loading={enviar.isPending}>
                Enviar cuestionario
              </Button>
            ) : (
              <Button onClick={siguiente}>
                Siguiente
              </Button>
            )}
          </Group>
        </Stack>
      </form>
    </Container>
  )
}
