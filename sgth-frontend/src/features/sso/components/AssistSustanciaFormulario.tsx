'use client'

import { Divider, Radio, Stack, Text, Title } from '@mantine/core'
import { Controller, type Control, type FieldErrors, type UseFormTrigger } from 'react-hook-form'
import type { SustanciaAssistInfo } from '../services/assistService'
import type { CuestionarioAssistFormData } from '../schemas/cuestionarioAssist.schema'

interface Props {
  /** El código de la sustancia, que es la clave dentro del formulario. */
  codigo: string
  sustancia: SustanciaAssistInfo
  opcionesFrecuencia3m: Record<string, string>
  opcionesFrecuenciaVida: Record<string, string>
  control: Control<CuestionarioAssistFormData>
  errors: FieldErrors<CuestionarioAssistFormData>
  /** Para limpiar el error de una pregunta en cuanto se responde. */
  trigger: UseFormTrigger<CuestionarioAssistFormData>
  /** P2 de esta sustancia, vigilado: decide si se preguntan P3 a P5. */
  p2: string | undefined
}

/**
 * Las preguntas del ASSIST para una sustancia, con la lógica de preguntas
 * filtro del manual: P2 decide si se preguntan P3 a P5, P5 no aplica a todas
 * las sustancias, y P6 y P7 se preguntan siempre.
 *
 * Las preguntas van en `Radio.Group` y NO como `label` de un `Select`
 * *contained*. Eran las seis etiquetas más largas de toda la aplicación —de 75
 * a 124 caracteres, cuando la siguiente del repositorio tiene 48— y el patrón
 * contained dibuja la etiqueta DENTRO del control, en una franja de unos 20 px
 * sobre un campo de 48: a 540 px una pregunta de 124 caracteres ocupa dos
 * líneas y la segunda cae encima del valor elegido; a 375 px son tres y tapan
 * el campo entero. La regla 07 lo dice: «una etiqueta más larga que su campo
 * se sale».
 *
 * El cuestionario psicosocial, su gemelo, ya lo hacía bien con
 * `Radio.Group label`, que dibuja la etiqueta en el flujo normal y la deja
 * crecer. Aquí se hace igual, y de paso las opciones se ven todas a la vez en
 * vez de esconderse tras un desplegable — que es como está el instrumento en
 * papel.
 */
export function AssistSustanciaFormulario({
  codigo, sustancia, opcionesFrecuencia3m, opcionesFrecuenciaVida, control, errors, trigger, p2,
}: Props) {
  const consumioUltimos3Meses = !! p2 && p2 !== 'nunca'
  const erroresSustancia = errors.sustancias?.[codigo]

  /** Una pregunta: su texto como etiqueta y sus opciones apiladas. */
  const pregunta = (
    campo: 'p2' | 'p3' | 'p4' | 'p5' | 'p6' | 'p7',
    texto: string,
    opciones: Record<string, string>,
  ) => (
    <Controller
      name={`sustancias.${codigo}.${campo}`}
      control={control}
      render={({ field }) => (
        <Radio.Group
          label={texto}
          required
          value={field.value ?? ''}
          // Revalidar al contestar, y no solo al intentar avanzar: el error lo
          // pone `trigger()` desde el boton Siguiente, y el modo por defecto de
          // React Hook Form solo revalida tras un ENVIO. Comprobado en el
          // navegador: sin esto, P2 seguia en rojo con la respuesta ya marcada.
          onChange={(valor) => {
            field.onChange(valor)
            void trigger(`sustancias.${codigo}.${campo}`)
          }}
          error={erroresSustancia?.[campo]?.message}
        >
          {/* Apiladas y no en fila: los textos del ASSIST son largos
              («Una o dos veces», «A diario o casi a diario») y en una fila se
              parten a media palabra. */}
          <Stack gap={6} mt="xs">
            {Object.entries(opciones).map(([valor, etiqueta]) => (
              <Radio key={valor} value={valor} label={etiqueta} />
            ))}
          </Stack>
        </Radio.Group>
      )}
    />
  )

  return (
    <Stack gap="lg">
      <div>
        <Title order={4}>{sustancia.etiqueta}</Title>
        <Text size="xs" c="dimmed">{sustancia.ejemplos}</Text>
      </div>

      {pregunta(
        'p2',
        'En los últimos 3 meses, ¿con qué frecuencia ha consumido esta sustancia?',
        opcionesFrecuencia3m,
      )}

      {consumioUltimos3Meses && (
        <>
          <Divider />
          {pregunta(
            'p3',
            'En los últimos 3 meses, ¿con qué frecuencia ha sentido un fuerte deseo o ansias de consumirla?',
            opcionesFrecuencia3m,
          )}
          {pregunta(
            'p4',
            'En los últimos 3 meses, ¿con qué frecuencia el consumo le ha causado problemas de salud, sociales, legales o económicos?',
            opcionesFrecuencia3m,
          )}
          {sustancia.incluye_pregunta_5 && pregunta(
            'p5',
            'En los últimos 3 meses, ¿con qué frecuencia dejó de hacer lo que habitualmente se esperaba de usted por el consumo?',
            opcionesFrecuencia3m,
          )}
        </>
      )}

      <Divider />

      {pregunta(
        'p6',
        '¿Un amigo, familiar o alguien más ha mostrado alguna vez preocupación por sus hábitos de consumo?',
        opcionesFrecuenciaVida,
      )}
      {pregunta(
        'p7',
        '¿Ha intentado alguna vez reducir o eliminar el consumo y no lo ha logrado?',
        opcionesFrecuenciaVida,
      )}
    </Stack>
  )
}
