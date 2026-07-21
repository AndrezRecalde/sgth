'use client'

import { useMemo, useState } from 'react'
import {
  Box, Container, Stack, Stepper, Title, Text, Checkbox, Group, Button,
  Select, Skeleton, Alert, Paper, Divider,
} from '@mantine/core'
import { notifications } from '@mantine/notifications'
import { IconAlertCircle, IconCircleCheck } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useCuestionarioAssist, useEnviarRespuestaAssist } from '../hooks/useAssist'
import { AssistSustanciaFormulario } from './AssistSustanciaFormulario'
import { getApiErrorMessage } from '@/types/api'
import type { RespuestaAssistPayload, RespuestaSustanciaAssist } from '../services/assistService'

interface Props {
  codigo: string
}

export function AssistCuestionarioPublico({ codigo }: Props) {
  const contained = useContainedInput()
  const { data: cuestionario, isLoading, isError, error } = useCuestionarioAssist(codigo)
  const enviar = useEnviarRespuestaAssist(codigo)

  const [step, setStep] = useState(0)
  const [seleccionadas, setSeleccionadas] = useState<string[]>([])
  const [sinConsumo, setSinConsumo] = useState(false)
  const [respuestas, setRespuestas] = useState<Record<string, Partial<RespuestaSustanciaAssist>>>({})
  const [usoInyectable, setUsoInyectable] = useState<string | null>(null)
  const [enviado, setEnviado] = useState(false)

  const sustanciasOrdenadas = useMemo(
    () => (cuestionario ? Object.entries(cuestionario.sustancias) : []),
    [cuestionario]
  )

  const sustanciasSeleccionadasOrdenadas = useMemo(
    () => sustanciasOrdenadas.filter(([key]) => seleccionadas.includes(key)),
    [sustanciasOrdenadas, seleccionadas]
  )

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
        <Alert icon={<IconAlertCircle size={18} />} color="red" variant="light" title="Tamizaje no disponible">
          {getApiErrorMessage(error, 'Este tamizaje no está disponible o el enlace es incorrecto.')}
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
              Su respuesta fue registrada de forma anónima y confidencial. No se solicitó
              ningún dato que le identifique.
            </Text>
          </Stack>
        </Paper>
      </Container>
    )
  }

  const totalPasos = sustanciasSeleccionadasOrdenadas.length + 2 // P1 + una por sustancia + P8/envío
  const esPasoP1 = step === 0
  const esPasoInyectable = step === totalPasos - 1
  const sustanciaActual = !esPasoP1 && !esPasoInyectable ? sustanciasSeleccionadasOrdenadas[step - 1] : null

  const actualizarRespuestaSustancia = (key: string, valor: Partial<RespuestaSustanciaAssist>) => {
    setRespuestas((r) => ({ ...r, [key]: valor }))
  }

  const validarPasoSustancia = (key: string): boolean => {
    const [, info] = sustanciasOrdenadas.find(([k]) => k === key)!
    const r = respuestas[key] ?? {}
    if (!r.p2) return false
    if (r.p2 !== 'nunca') {
      if (!r.p3 || !r.p4) return false
      if (info.incluye_pregunta_5 && !r.p5) return false
    }
    return !!r.p6 && !!r.p7
  }

  const siguiente = () => {
    if (esPasoP1) {
      if (seleccionadas.length === 0 && !sinConsumo) {
        notifications.show({
          title: 'Falta una respuesta',
          message: 'Seleccione las sustancias que ha consumido, o marque "No he consumido ninguna de estas sustancias" antes de continuar.',
          color: 'red',
        })
        return
      }
      if (sinConsumo) {
        // Manual ASSIST (Fig. 1): si la respuesta es negativa para todas las sustancias,
        // se detiene la entrevista de inmediato — no se preguntan P2-P8.
        enviarSinConsumo()
        return
      }
      setStep(1)
      return
    }
    if (sustanciaActual) {
      const [key] = sustanciaActual
      if (!validarPasoSustancia(key)) {
        notifications.show({
          title: 'Faltan respuestas',
          message: 'Debe responder todas las preguntas de esta sección antes de continuar.',
          color: 'red',
        })
        return
      }
      setStep((s) => s + 1)
    }
  }

  const anterior = () => setStep((s) => Math.max(s - 1, 0))

  const enviarSinConsumo = () => {
    enviar.mutate(
      { sustancias: {} },
      {
        onSuccess: () => setEnviado(true),
        onError: (err) => {
          notifications.show({
            title: 'No se pudo enviar',
            message: getApiErrorMessage(err),
            color: 'red',
          })
        },
      }
    )
  }

  const handleEnviar = () => {
    if (!usoInyectable) {
      notifications.show({
        title: 'Falta una respuesta',
        message: 'Debe responder la última pregunta antes de enviar.',
        color: 'red',
      })
      return
    }

    const payload: RespuestaAssistPayload = {
      sustancias: Object.fromEntries(
        sustanciasSeleccionadasOrdenadas.map(([key]) => [key, respuestas[key] as RespuestaSustanciaAssist])
      ),
      uso_inyectable: usoInyectable,
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

  return (
    <Container size="sm" py="xl">
      <Stack gap="lg">
        <Box>
          <Title order={2}>Tamizaje de consumo de sustancias (ASSIST)</Title>
          <Text size="sm" c="dimmed">
            Cuestionario anónimo y confidencial — Organización Mundial de la Salud / OPS.
            No se solicita nombre, cédula ni firma. Sea honesto: esta información se usa
            únicamente para orientar el programa de prevención de la institución.
          </Text>
        </Box>

        <Stepper active={step} size="sm" iconSize={28} allowNextStepsSelect={false}>
          <Stepper.Step label="Sustancias" />
          {sustanciasSeleccionadasOrdenadas.map(([key, info]) => (
            <Stepper.Step key={key} label={info.etiqueta} />
          ))}
          <Stepper.Step label="Uso inyectable" />
        </Stepper>

        {esPasoP1 && (
          <Stack gap="sm">
            <Title order={4}>A lo largo de su vida, ¿cuáles de las siguientes sustancias ha consumido alguna vez?</Title>
            <Text size="sm" c="dimmed">Solo las que consumió sin receta médica. Seleccione todas las que apliquen.</Text>
            <Stack gap="xs">
              {sustanciasOrdenadas.map(([key, info]) => (
                <Checkbox
                  key={key}
                  label={`${info.etiqueta} (${info.ejemplos})`}
                  disabled={sinConsumo}
                  checked={seleccionadas.includes(key)}
                  onChange={(e) => {
                    const checked = e.currentTarget.checked
                    setSeleccionadas((s) => (checked ? [...s, key] : s.filter((k) => k !== key)))
                    if (checked) setSinConsumo(false)
                  }}
                />
              ))}
            </Stack>
            <Divider my="xs" />
            <Checkbox
              label={<Text fw={600}>No he consumido ninguna de estas sustancias</Text>}
              checked={sinConsumo}
              onChange={(e) => {
                const checked = e.currentTarget.checked
                setSinConsumo(checked)
                if (checked) setSeleccionadas([])
              }}
            />
          </Stack>
        )}

        {sustanciaActual && (
          <AssistSustanciaFormulario
            sustancia={sustanciaActual[1]}
            opcionesFrecuencia3m={cuestionario.opciones_frecuencia_3m}
            opcionesFrecuenciaVida={cuestionario.opciones_frecuencia_vida}
            value={respuestas[sustanciaActual[0]] ?? {}}
            onChange={(v) => actualizarRespuestaSustancia(sustanciaActual[0], v)}
          />
        )}

        {esPasoInyectable && (
          <Stack gap="sm">
            <Title order={4}>Una última pregunta</Title>
            <Select
              label={cuestionario.pregunta_inyectable.texto}
              data={Object.entries(cuestionario.opciones_frecuencia_vida).map(([v, label]) => ({ value: v, label }))}
              required
              {...contained}
              value={usoInyectable}
              onChange={setUsoInyectable}
            />
          </Stack>
        )}

        <Group justify="space-between" mt="md">
          <Button variant="default" onClick={anterior} disabled={step === 0}>
            Atrás
          </Button>
          {esPasoInyectable ? (
            <Button color="emerald" loading={enviar.isPending} onClick={handleEnviar}>
              Enviar tamizaje
            </Button>
          ) : (
            <Button color="emerald" loading={esPasoP1 && sinConsumo && enviar.isPending} onClick={siguiente}>
              Siguiente
            </Button>
          )}
        </Group>
      </Stack>
    </Container>
  )
}
