'use client'

import { useState, type BaseSyntheticEvent } from 'react'
import { Button, Group, Skeleton, Stack } from '@mantine/core'
import { IconCopyPlus } from '@tabler/icons-react'
import { useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { useTodasUnidades } from '@/features/estructura/hooks/useUnidades'
import { useGuardarBien } from '../hooks/useBienes'
import { useCatalogosFormulario } from '../hooks/useCatalogosInventario'
import { bienSchema, CAMPOS_BIEN, type BienFormData } from '../schemas/bien.schema'
import { aDatosDeBien, valoresDeBien, valoresDeCopia, valoresParaOtro } from '../utils/bienFormulario'
import type { BienInformatico } from '../services/tipos'
import { BienFormIdentificacion } from './BienFormIdentificacion'
import { BienFormUbicacion } from './BienFormUbicacion'
import { BienFormCaracteristicas } from './BienFormCaracteristicas'

interface Props {
  /** El que se corrige; sin él, se registra uno nuevo. */
  bien: BienInformatico | null
  /** Al registrar: el equipo del que se copian tipo, marca, modelo, ubicación y características. */
  plantilla?: BienInformatico | null
  /** Al registrar: valores puestos de antemano (el alta en sitio trae el código y la ubicación). */
  iniciales?: Partial<BienFormData>
  onGuardado: (bien: BienInformatico) => void
  /**
   * Al registrar en serie: el que se acaba de guardar. El formulario se queda
   * con lo común del lote y en blanco lo que es de cada equipo.
   */
  onGuardadoYOtro?: (bien: BienInformatico) => void
  onCancelar: () => void
}

/**
 * La ficha de un equipo en tres secciones: qué es, dónde está y cómo es. Las
 * características son las de la ficha de su tipo (5.3). Los catálogos llegan
 * en una sola consulta (`/inventario/catalogos`).
 */
export function BienForm({ bien, plantilla = null, iniciales, onGuardado, onGuardadoYOtro, onCancelar }: Props) {
  const catalogos = useCatalogosFormulario()
  const unidades = useTodasUnidades()
  const guardar = useGuardarBien()
  // Cuál de los dos botones de envío se pulsó, para su indicador de carga. El
  // envío no lo lee de aquí —el clic y el envío llegan en el mismo evento—,
  // sino del botón que lo disparó (`submitter`).
  const [otro, setOtro] = useState(false)

  const form = useForm<BienFormData>({
    resolver: zodResolver(bienSchema),
    defaultValues: bien ? valoresDeBien(bien) : { ...(plantilla ? valoresDeCopia(plantilla) : valoresDeBien(null)), ...iniciales },
  })
  const tipoId = useWatch({ control: form.control, name: 'tipo_bien_id' })
  const ficha = catalogos.data?.tipos.find((t) => t.id === tipoId)?.ficha ?? 'general'

  const enviar = (v: BienFormData, e?: BaseSyntheticEvent) => {
    const nativo = e?.nativeEvent
    const enSerie = nativo instanceof SubmitEvent && nativo.submitter?.dataset.serie === 'si'

    return guardar.mutateAsync({ id: bien?.id, data: aDatosDeBien(v, bien !== null, ficha) })
      .then((guardado) => {
        if (enSerie && onGuardadoYOtro) {
          form.reset(valoresParaOtro(v))
          onGuardadoYOtro(guardado)
          return
        }
        onGuardado(guardado)
      })
      .catch((error) => erroresAlFormulario(error, form.setError, CAMPOS_BIEN, 'No se pudo guardar el equipo'))
  }

  if (catalogos.isLoading || !catalogos.data) {
    return <Skeleton height={420} radius="lg" />
  }

  return (
    <form onSubmit={form.handleSubmit(enviar)} noValidate>
      <Stack gap="lg">
        <BienFormIdentificacion form={form} catalogos={catalogos.data} />
        <BienFormUbicacion
          form={form}
          catalogos={catalogos.data}
          unidades={unidades.data ?? []}
          bien={bien}
        />
        <BienFormCaracteristicas form={form} ficha={ficha} />

        <Group justify="flex-end">
          <Button variant="default" onClick={onCancelar}>Cancelar</Button>
          {!bien && onGuardadoYOtro && (
            <Button type="submit" variant="light" leftSection={<IconCopyPlus size={16} />}
              data-serie="si" loading={guardar.isPending && otro} disabled={guardar.isPending && !otro} onClick={() => setOtro(true)}>
              Registrar y agregar otro
            </Button>
          )}
          <Button type="submit" loading={guardar.isPending && !otro} disabled={guardar.isPending && otro} onClick={() => setOtro(false)}>
            {bien ? 'Guardar cambios' : 'Registrar equipo'}
          </Button>
        </Group>
      </Stack>
    </form>
  )
}
