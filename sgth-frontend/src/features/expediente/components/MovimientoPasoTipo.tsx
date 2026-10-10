'use client'

import { Alert, Select, Stack } from '@mantine/core'
import { Controller, type UseFormReturn } from 'react-hook-form'
import { IconInfoCircle } from '@tabler/icons-react'
import { ModalFooter } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import type { MovimientoFormData } from '../schemas/movimiento.schema'
import type { CatalogoAccionesPersonal, CausalDelCatalogo, ClaseDelCatalogo } from '@/types/api'

interface Props {
  form: UseFormReturn<MovimientoFormData>
  catalogo: CatalogoAccionesPersonal
  /** Las clases que se le pueden registrar al servidor, ya filtradas. */
  clases: ClaseDelCatalogo[]
  /** Las causales de la clase elegida que aplican a su nombramiento. */
  causales: CausalDelCatalogo[]
  claseElegida?: ClaseDelCatalogo
  causalElegida?: string | null
  /** Falso cuando la familia elegida deja una sola clase: ya no se pregunta. */
  preguntaLaClase: boolean
  /** Sin familia elegida de antemano, las clases se agrupan por la suya. */
  agruparPorFamilia: boolean
  /** Elegir arrastra el valor inicial de la ficha de salud ocupacional. */
  elegirClase: (codigo: string | null) => void
  elegirCausal: (codigo: string | null) => void
  onCancel: () => void
  onContinuar: () => void
}

/**
 * Primer paso: qué se va a registrar.
 *
 * Con causal —la cesación— este paso hace falta aunque la clase ya venga
 * decidida: es la causal la que determina las reglas y el documento, y
 * saltárselo dejaba un formulario que el backend rechazaba por un dato que
 * nunca se pidió.
 */
export function MovimientoPasoTipo({
  form, catalogo, clases, causales, claseElegida, causalElegida, preguntaLaClase,
  agruparPorFamilia, elegirClase, elegirCausal, onCancel, onContinuar,
}: Props) {
  const contained = useContainedInput()
  const { control, formState: { errors } } = form

  const pideCausal = !!claseElegida && claseElegida.causales.length > 0
  // La base legal de la causal elegida, que es lo que cambia de una a otra
  // desde que la cesación tiene las causales de la tabla 4.3 (fase 2.1).
  const baseLegal = causales.find((c) => c.codigo === causalElegida)?.base_legal
  const puedeAvanzar = !!claseElegida && (!pideCausal || !!causalElegida)

  const opcion = (c: ClaseDelCatalogo) => ({ value: c.codigo, label: c.etiqueta })

  // Agrupadas en el orden de las familias del catálogo, y sin grupos vacíos.
  const opciones = agruparPorFamilia
    ? catalogo.familias
      .map((f) => ({
        group: f.etiqueta,
        items: clases.filter((c) => c.familia === f.codigo).map(opcion),
      }))
      .filter((g) => g.items.length > 0)
    : clases.map(opcion)

  return (
    <Stack gap="sm" mt="md">
      {preguntaLaClase && (
        <Controller
          name="clase"
          control={control}
          render={({ field }) => (
            <Select
              label="Tipo de acción de personal"
              placeholder="Seleccione el tipo de acción"
              data={opciones}
              value={field.value || null}
              onChange={elegirClase}
              error={errors.clase?.message}
              {...contained}
            />
          )}
        />
      )}

      {pideCausal && (
        <Controller
          name="causal"
          control={control}
          render={({ field }) => (
            <Select
              label="Causal"
              placeholder="Seleccione la causal"
              description={baseLegal
                ? `Base legal: ${baseLegal}.`
                : 'Es la causal la que determina las reglas y el documento que se imprime.'}
              data={causales.map((c) => ({ value: c.codigo, label: c.etiqueta }))}
              value={field.value ?? null}
              onChange={elegirCausal}
              error={errors.causal?.message}
              {...contained}
            />
          )}
        />
      )}

      {pideCausal && causales.length === 0 && (
        <Alert color="amber" variant="light" icon={<IconInfoCircle size={16} />}>
          Ninguna causal de {claseElegida.etiqueta} aplica al nombramiento vigente
          de este servidor.
        </Alert>
      )}

      <ModalFooter
        onCancel={onCancel}
        submitLabel="Continuar"
        submitDisabled={!puedeAvanzar}
        onSubmit={onContinuar}
      />
    </Stack>
  )
}
