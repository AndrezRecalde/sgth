'use client'

import { useMemo, useState } from 'react'
import {
  Box, Container, Stack, Stepper, Title, Text, Radio, Group, Button,
  Select, Skeleton, Alert, Paper, Divider,
} from '@mantine/core'
import { notifications } from '@mantine/notifications'
import { IconAlertCircle, IconCircleCheck } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useCuestionarioPsicosocial, useEnviarRespuestaPsicosocial } from '../hooks/usePsicosocial'
import { OPCIONES_LIKERT_PSICOSOCIAL } from '../schemas/psicosocial.schema'
import { getApiErrorMessage } from '@/types/api'
import type { RespuestaPsicosocialPayload } from '../services/psicosocialService'

interface Props {
  codigo: string
}

export function CuestionarioPsicosocialPublico({ codigo }: Props) {
  const contained = useContainedInput()
  const { data: cuestionario, isLoading, isError, error } = useCuestionarioPsicosocial(codigo)
  const enviar = useEnviarRespuestaPsicosocial(codigo)

  const [step, setStep] = useState(0)
  const [respuestas, setRespuestas] = useState<Record<number, number>>({})
  const [datosGenerales, setDatosGenerales] = useState<Record<string, string | null>>({})
  const [enviado, setEnviado] = useState(false)

  const dimensionKeys = useMemo(
    () => (cuestionario ? Object.keys(cuestionario.dimensiones) : []),
    [cuestionario]
  )

  const itemsPorDimension = useMemo(() => {
    if (!cuestionario) return {} as Record<string, number[]>
    const mapa: Record<string, number[]> = {}
    for (const [numero, pregunta] of Object.entries(cuestionario.preguntas)) {
      mapa[pregunta.dimension] = mapa[pregunta.dimension] ?? []
      mapa[pregunta.dimension].push(Number(numero))
    }
    for (const key of Object.keys(mapa)) mapa[key].sort((a, b) => a - b)
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

  if (enviado) {
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

  const esUltimoPaso = step === dimensionKeys.length
  const dimensionActualKey = step > 0 ? dimensionKeys[step - 1] : null
  const itemsActuales = dimensionActualKey ? itemsPorDimension[dimensionActualKey] ?? [] : []

  const validarPasoActual = (): boolean => {
    if (!dimensionActualKey) return true
    const faltantes = itemsActuales.filter((n) => respuestas[n] === undefined)
    if (faltantes.length > 0) {
      notifications.show({
        title: 'Faltan respuestas',
        message: `Debe responder todos los ítems de esta sección (faltan ${faltantes.length}).`,
        color: 'red',
      })
      return false
    }
    return true
  }

  const siguiente = () => {
    if (!validarPasoActual()) return
    setStep((s) => Math.min(s + 1, dimensionKeys.length))
  }

  const anterior = () => setStep((s) => Math.max(s - 1, 0))

  const handleEnviar = () => {
    if (Object.keys(respuestas).length !== 58) {
      notifications.show({
        title: 'Cuestionario incompleto',
        message: 'Debe responder los 58 ítems del cuestionario antes de enviar.',
        color: 'red',
      })
      return
    }

    const payload: RespuestaPsicosocialPayload = {
      ...datosGenerales,
      respuestas,
    }

    enviar.mutate(payload, {
      onSuccess: () => setEnviado(true),
      onError: (err) => {
        notifications.show({
          title: 'No se pudo enviar',
          message: getApiErrorMessage(err),
          color: 'red',
        })
      },
    })
  }

  const opciones = cuestionario.datos_generales_opciones
  const opcionesSelect = (grupo: string) =>
    Object.entries(opciones[grupo] ?? {}).map(([value, label]) => ({ value, label }))

  return (
    <Container size="sm" py="xl">
      <Stack gap="lg">
        <Box>
          <Title order={2}>Evaluación de riesgo psicosocial</Title>
          <Text size="sm" c="dimmed">
            Cuestionario anónimo y confidencial — Ministerio del Trabajo del Ecuador.
            No se solicita información que le identifique. Complete todos los ítems; no
            existen respuestas correctas o incorrectas.
          </Text>
        </Box>

        <Stepper active={step} size="sm" iconSize={28} allowNextStepsSelect={false}>
          <Stepper.Step label="Datos generales" />
          {dimensionKeys.map((key) => (
            <Stepper.Step key={key} label={cuestionario.dimensiones[key].etiqueta} />
          ))}
        </Stepper>

        {step === 0 && (
          <Stack gap="sm">
            <Title order={4}>Datos generales</Title>
            <Text size="sm" c="dimmed">
              Estos datos son opcionales, agregados y no permiten identificarle.
            </Text>
            <Select
              label="Área de trabajo"
              data={opcionesSelect('area_trabajo')}
              clearable
              {...contained}
              value={datosGenerales.area_trabajo ?? null}
              onChange={(v) => setDatosGenerales((d) => ({ ...d, area_trabajo: v }))}
            />
            <Select
              label="Nivel de instrucción"
              data={opcionesSelect('nivel_instruccion')}
              clearable
              {...contained}
              value={datosGenerales.nivel_instruccion ?? null}
              onChange={(v) => setDatosGenerales((d) => ({ ...d, nivel_instruccion: v }))}
            />
            <Select
              label="Antigüedad en la institución"
              data={opcionesSelect('antiguedad')}
              clearable
              {...contained}
              value={datosGenerales.antiguedad ?? null}
              onChange={(v) => setDatosGenerales((d) => ({ ...d, antiguedad: v }))}
            />
            <Select
              label="Rango de edad"
              data={opcionesSelect('rango_edad')}
              clearable
              {...contained}
              value={datosGenerales.rango_edad ?? null}
              onChange={(v) => setDatosGenerales((d) => ({ ...d, rango_edad: v }))}
            />
            <Select
              label="Auto-identificación étnica"
              data={opcionesSelect('autoidentificacion_etnica')}
              clearable
              {...contained}
              value={datosGenerales.autoidentificacion_etnica ?? null}
              onChange={(v) => setDatosGenerales((d) => ({ ...d, autoidentificacion_etnica: v }))}
            />
            <Select
              label="Género"
              data={opcionesSelect('genero')}
              clearable
              {...contained}
              value={datosGenerales.genero ?? null}
              onChange={(v) => setDatosGenerales((d) => ({ ...d, genero: v }))}
            />
          </Stack>
        )}

        {dimensionActualKey && (
          <Stack gap="lg">
            <Title order={4}>{cuestionario.dimensiones[dimensionActualKey].etiqueta}</Title>
            {itemsActuales.map((numero) => (
              <Box key={numero}>
                <Radio.Group
                  label={cuestionario.preguntas[numero].texto}
                  value={respuestas[numero]?.toString() ?? ''}
                  onChange={(v) => setRespuestas((r) => ({ ...r, [numero]: Number(v) }))}
                >
                  <Group mt="xs" gap="md">
                    {OPCIONES_LIKERT_PSICOSOCIAL.map((op) => (
                      <Radio key={op.value} value={String(op.value)} label={op.label} />
                    ))}
                  </Group>
                </Radio.Group>
                <Divider mt="md" />
              </Box>
            ))}
          </Stack>
        )}

        <Group justify="space-between" mt="md">
          <Button variant="default" onClick={anterior} disabled={step === 0}>
            Atrás
          </Button>
          {esUltimoPaso ? (
            <Button
              color="emerald"
              loading={enviar.isPending}
              onClick={() => {
                if (!validarPasoActual()) return
                handleEnviar()
              }}
            >
              Enviar cuestionario
            </Button>
          ) : (
            <Button color="emerald" onClick={siguiente}>
              Siguiente
            </Button>
          )}
        </Group>
      </Stack>
    </Container>
  )
}
