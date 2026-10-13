'use client'

import { Button, Group, Skeleton, Stack } from '@mantine/core'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { useTodasUnidades } from '@/features/estructura/hooks/useUnidades'
import { useGuardarBien } from '../hooks/useBienes'
import { useCatalogosFormulario } from '../hooks/useCatalogosInventario'
import { bienSchema, CAMPOS_BIEN, type BienFormData } from '../schemas/bien.schema'
import { aDatosDeBien, valoresDeBien } from '../utils/bienFormulario'
import type { BienInformatico } from '../services/tipos'
import { BienFormIdentificacion } from './BienFormIdentificacion'
import { BienFormUbicacion } from './BienFormUbicacion'
import { BienFormCaracteristicas } from './BienFormCaracteristicas'

interface Props {
  /** El que se corrige; sin él, se registra uno nuevo. */
  bien: BienInformatico | null
  onGuardado: (bien: BienInformatico) => void
  onCancelar: () => void
}

/**
 * La ficha de un equipo en tres secciones: qué es, dónde está y cómo es.
 * Los catálogos llegan en una sola consulta (`/inventario/catalogos`).
 */
export function BienForm({ bien, onGuardado, onCancelar }: Props) {
  const catalogos = useCatalogosFormulario()
  const unidades = useTodasUnidades()
  const guardar = useGuardarBien()

  const form = useForm<BienFormData>({
    resolver: zodResolver(bienSchema),
    defaultValues: valoresDeBien(bien),
  })

  const enviar = (v: BienFormData) =>
    guardar.mutateAsync({ id: bien?.id, data: aDatosDeBien(v, bien !== null) })
      .then(onGuardado)
      .catch((e) => erroresAlFormulario(e, form.setError, CAMPOS_BIEN, 'No se pudo guardar el equipo'))

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
        <BienFormCaracteristicas form={form} />

        <Group justify="flex-end">
          <Button variant="default" onClick={onCancelar}>Cancelar</Button>
          <Button type="submit" loading={guardar.isPending}>
            {bien ? 'Guardar cambios' : 'Registrar equipo'}
          </Button>
        </Group>
      </Stack>
    </form>
  )
}
