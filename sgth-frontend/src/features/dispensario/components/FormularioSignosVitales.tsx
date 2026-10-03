'use client'

import type { ReactNode } from 'react'
import { Stack, Group, Button, Textarea } from '@mantine/core'
import { useForm, Controller, type DefaultValues } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { IconCheck } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { SectionCard } from '@/components/ui'
import { erroresDeCampo } from '@/lib/erroresDeCampo'
import { signosVitalesSchema, type SignosVitalesFormData } from '../schemas/signosVitales.schema'
import { CamposAntropometria } from './CamposAntropometria'
import { CamposSignosVitales } from './CamposSignosVitales'
import { AvisoSignosAlterados } from './AvisoSignosAlterados'

interface Props {
  /** Quién es el paciente: va arriba, antes de cualquier campo. */
  encabezado:    ReactNode
  /** Lo que conviene ver antes de medir (tomas previas, la visita anterior). */
  contexto?:     ReactNode
  conPerimetro?: boolean
  esMenor:       boolean
  consecuencia:  { critico: string; atencion: string }
  /** Para rehacer una toma sin volver a teclear las ocho cifras. */
  valoresIniciales?: DefaultValues<SignosVitalesFormData>
  textoEnviar:   string
  textoCancelar: string
  enviando:      boolean
  /** `mutateAsync` de la mutación: si falla con 422, cada error va a su campo. */
  enviar:        (valores: SignosVitalesFormData) => Promise<unknown>
  onCancelar:    () => void
}

/**
 * Ninguna constante vital arranca con un valor: se teclean todas. Las claves
 * se omiten en vez de escribirlas `undefined` (regla 09).
 */
const VACIO: DefaultValues<SignosVitalesFormData> = { observaciones_enfermera: '' }

function esCampo(campo: string): campo is keyof SignosVitalesFormData {
  return campo in signosVitalesSchema.shape
}

/**
 * La toma de signos vitales, una sola para el triaje de un turno y para el
 * previo al FEMO. Eran dos formularios casi copiados (358 y 200 líneas) que
 * ya se habían separado: el del FEMO no avisaba de cifras alteradas y el del
 * triaje no aceptaba la regla de la diastólica.
 */
export function FormularioSignosVitales(props: Props) {
  const contained = useContainedInput()
  const {
    control, handleSubmit, setError,
    formState: { errors },
  } = useForm<SignosVitalesFormData>({
    resolver: zodResolver(signosVitalesSchema),
    defaultValues: props.valoresIniciales ?? VACIO,
  })

  // La notificación la pone la mutación; aquí solo se reparte el 422 por
  // campos, que es lo que pide la regla 07.
  const onSubmit = (valores: SignosVitalesFormData) =>
    props.enviar(valores).catch((error: unknown) => {
      for (const [campo, mensaje] of Object.entries(erroresDeCampo(error) ?? {})) {
        if (esCampo(campo)) setError(campo, { message: mensaje })
      }
    })

  return (
    <form onSubmit={handleSubmit(onSubmit)} noValidate>
      <Stack gap="lg">
        {props.encabezado}
        {props.contexto}

        <CamposAntropometria control={control} errors={errors} conPerimetro={props.conPerimetro} />
        <CamposSignosVitales control={control} errors={errors} />

        <SectionCard title="Observaciones">
          <Controller
            name="observaciones_enfermera"
            control={control}
            render={({ field }) => (
              <Textarea
                label="Observaciones (opcional)"
                placeholder="Anotaciones relevantes durante la toma de signos vitales"
                autosize
                minRows={2}
                {...contained}
                value={field.value ?? ''}
                onChange={(e) => field.onChange(e.currentTarget.value)}
                error={errors.observaciones_enfermera?.message}
              />
            )}
          />
        </SectionCard>

        <AvisoSignosAlterados control={control} esMenor={props.esMenor} consecuencia={props.consecuencia} />

        <Group justify="flex-end" mt="sm">
          <Button variant="default" onClick={props.onCancelar}>
            {props.textoCancelar}
          </Button>
          <Button type="submit" leftSection={<IconCheck size={14} />} loading={props.enviando}>
            {props.textoEnviar}
          </Button>
        </Group>
      </Stack>
    </form>
  )
}
