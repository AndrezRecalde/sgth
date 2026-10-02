'use client'

import { useMemo, useState } from 'react'
import {
  Box, Container, Stack, Stepper, Title, Text, Group, Button,
  Radio, Skeleton, Alert, Paper,
} from '@mantine/core'
import { IconAlertCircle, IconCircleCheck } from '@tabler/icons-react'
import { Controller, useForm, useWatch, type Resolver } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useCuestionarioAssist, useEnviarRespuestaAssist } from '../hooks/useAssist'
import { AssistSeleccionSustancias } from './AssistSeleccionSustancias'
import { AssistSustanciaFormulario } from './AssistSustanciaFormulario'
import {
  cargaUtilAssist, esquemaCuestionarioAssist, VALORES_INICIALES_ASSIST,
  type CuestionarioAssistFormData,
} from '../schemas/cuestionarioAssist.schema'
import { getApiErrorMessage } from '@/types/api'
import { notificar } from '@/components/ui'

interface Props {
  codigo: string
}

/**
 * El tamizaje ASSIST que responde el personal, por enlace y sin sesión.
 *
 * Estaba capturado con cinco `useState` y validado a mano: «Debe responder
 * todas las preguntas de esta sección» en una notificación, sin marcar cuál
 * faltaba, sobre cinco o siete preguntas. Ahora es React Hook Form + Zod como
 * el resto del sistema (regla 07), y el hueco se señala en su pregunta.
 */
export function AssistCuestionarioPublico({ codigo }: Props) {
  const { data: cuestionario, isLoading, isError, error } = useCuestionarioAssist(codigo)
  const enviar = useEnviarRespuestaAssist(codigo)

  const resolver = useMemo(
    () => cuestionario
      ? zodResolver(esquemaCuestionarioAssist(
        cuestionario.sustancias,
        Object.keys(cuestionario.opciones_frecuencia_3m),
        Object.keys(cuestionario.opciones_frecuencia_vida),
      )) as Resolver<CuestionarioAssistFormData>
      : undefined,
    [cuestionario],
  )

  const {
    control, handleSubmit, setValue, trigger, formState: { errors, isSubmitSuccessful },
  } = useForm<CuestionarioAssistFormData>({
    resolver,
    defaultValues: VALORES_INICIALES_ASSIST,
  })

  // `useWatch` y no `watch()`: el segundo devuelve una función que el
  // compilador de React no puede memoizar, y ESLint lo rechaza.
  const sinConsumo = useWatch({ control, name: 'sinConsumo' })
  const seleccionadas = useWatch({ control, name: 'seleccionadas' })
  const sustanciasRespondidas = useWatch({ control, name: 'sustancias' })

  // El paso del asistente es navegación, no dato: `useState` es correcto
  // aquí. La regla 08 prohíbe `useState` para lo que viene del API, y lo que
  // se captura vive en React Hook Form.
  const [paso, setPaso] = useState(0)

  const sustanciasOrdenadas = useMemo(
    () => (cuestionario ? Object.entries(cuestionario.sustancias) : []),
    [cuestionario],
  )

  const elegidas = useMemo(
    () => sustanciasOrdenadas.filter(([clave]) => seleccionadas.includes(clave)),
    [sustanciasOrdenadas, seleccionadas],
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

  if (isSubmitSuccessful && enviar.isSuccess) {
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

  const totalPasos = elegidas.length + 2 // P1 + una por sustancia + P8
  const esPasoP1 = paso === 0
  const esPasoInyectable = paso === totalPasos - 1
  const sustanciaActual = !esPasoP1 && !esPasoInyectable ? elegidas[paso - 1] : null

  const enviarFormulario = (datos: CuestionarioAssistFormData) => {
    enviar.mutate(cargaUtilAssist(datos), {
      onError: (err) => notificar.error('No se pudo enviar el cuestionario', getApiErrorMessage(err)),
    })
  }

  const siguiente = async () => {
    if (esPasoP1) {
      // Marcar «no he consumido ninguna» detiene la entrevista en el acto
      // (manual ASSIST, Fig. 1): no se preguntan P2 a P8.
      if (sinConsumo) return handleSubmit(enviarFormulario)()
      if (!(await trigger('seleccionadas'))) return
      setPaso(1)
      return
    }

    if (sustanciaActual) {
      // Solo las preguntas de ESTA sustancia: validar el formulario entero
      // marcaría en rojo las sustancias que todavía no se han visto.
      if (!(await trigger(`sustancias.${sustanciaActual[0]}`))) return
      setPaso(paso + 1)
    }
  }

  const atras = () => setPaso(Math.max(paso - 1, 0))

  return (
    <Container size="sm" py="xl">
      <form onSubmit={handleSubmit(enviarFormulario)} noValidate>
        <Stack gap="lg">
          <Box>
            <Title order={2}>Tamizaje de consumo de sustancias (ASSIST)</Title>
            <Text size="sm" c="dimmed">
              Cuestionario anónimo y confidencial — Organización Mundial de la Salud / OPS.
              No se solicita nombre, cédula ni firma. Sea honesto: esta información se usa
              únicamente para orientar el programa de prevención de la institución.
            </Text>
          </Box>

          <Stepper active={paso} size="sm" iconSize={28} allowNextStepsSelect={false}>
            <Stepper.Step label="Sustancias" />
            {elegidas.map(([clave, info]) => (
              <Stepper.Step key={clave} label={info.etiqueta} />
            ))}
            <Stepper.Step label="Uso inyectable" />
          </Stepper>

          {esPasoP1 && (
            <AssistSeleccionSustancias
              sustancias={sustanciasOrdenadas}
              control={control}
              errors={errors}
              setValue={setValue}
              seleccionadas={seleccionadas}
              sinConsumo={sinConsumo}
            />
          )}

          {sustanciaActual && (
            <AssistSustanciaFormulario
              codigo={sustanciaActual[0]}
              sustancia={sustanciaActual[1]}
              opcionesFrecuencia3m={cuestionario.opciones_frecuencia_3m}
              opcionesFrecuenciaVida={cuestionario.opciones_frecuencia_vida}
              control={control}
              errors={errors}
              trigger={trigger}
              p2={sustanciasRespondidas?.[sustanciaActual[0]]?.p2}
            />
          )}

          {esPasoInyectable && (
            <Stack gap="sm">
              <Title order={4}>Una última pregunta</Title>
              {/* En `Radio.Group` y no como etiqueta de un `Select`: la
                  pregunta P8 del manual tiene 90 caracteres y el patrón
                  contained dibuja la etiqueta dentro del control. */}
              <Controller
                name="uso_inyectable"
                control={control}
                render={({ field }) => (
                  <Radio.Group
                    label={cuestionario.pregunta_inyectable.texto}
                    required
                    value={field.value ?? ''}
                    onChange={field.onChange}
                    error={errors.uso_inyectable?.message}
                  >
                    <Stack gap={6} mt="xs">
                      {Object.entries(cuestionario.opciones_frecuencia_vida).map(([valor, etiqueta]) => (
                        <Radio key={valor} value={valor} label={etiqueta} />
                      ))}
                    </Stack>
                  </Radio.Group>
                )}
              />
            </Stack>
          )}

          <Group justify="space-between" mt="md">
            <Button variant="default" onClick={atras} disabled={paso === 0}>
              Atrás
            </Button>
            {esPasoInyectable ? (
              <Button type="submit" loading={enviar.isPending}>
                Enviar tamizaje
              </Button>
            ) : (
              <Button
                onClick={siguiente}
                loading={esPasoP1 && sinConsumo && enviar.isPending}
              >
                Siguiente
              </Button>
            )}
          </Group>
        </Stack>
      </form>
    </Container>
  )
}
