'use client'

import { Grid, TextInput } from '@mantine/core'
import type { UseFormReturn } from 'react-hook-form'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useCompanerosDeUnidad } from '../hooks/useCompanerosDeUnidad'
import { PermisoJefeSelect } from './PermisoJefeSelect'
import type { PermisoFormData } from './permiso.schema'
import type { CompaneroDeUnidad } from '@/types/api'

interface Props {
  form:         UseFormReturn<PermisoFormData>
  nombrePropio: string
}

const opcion = (c: CompaneroDeUnidad) => ({
  value: String(c.id),
  label: c.cargo ? `${c.nombre} — ${c.cargo}` : c.nombre,
})

/**
 * Quien registra solo sus permisos: el solicitante es él mismo, y el jefe
 * inmediato lo elige entre los servidores de su unidad.
 *
 * Esa lista sale del autoservicio y no del listado de expedientes, que está
 * cerrado a Talento Humano: con él, el selector respondía 403 y salía vacío.
 * Se ofrecen todos los servidores de la unidad; los jefes, en su propio grupo
 * y primero, que es a quien se busca.
 */
export function PermisoSolicitantePropio({ form, nombrePropio }: Props) {
  const contained = useContainedInput()
  const { data: companeros = [] } = useCompanerosDeUnidad()

  const jefes = companeros.filter(c => c.es_jefe).map(opcion)
  const resto = companeros.filter(c => !c.es_jefe).map(opcion)
  const opciones = [
    ...(jefes.length ? [{ group: 'Jefes de la unidad', items: jefes }] : []),
    ...(resto.length ? [{ group: 'Otros servidores de la unidad', items: resto }] : []),
  ]

  return (
    <Grid>
      {/* El solicitante es quien tiene la sesión: no se elige. */}
      <Grid.Col span={{ base: 12, sm: 6 }}>
        {/*
          Sin descripción: el patrón contained la pone encima del campo, y
          empujaba este 19 px más abajo que el jefe inmediato de al lado.
        */}
        <TextInput
          label="Servidor"
          value={nombrePropio}
          readOnly
          {...contained}
        />
      </Grid.Col>

      <Grid.Col span={{ base: 12, sm: 6 }}>
        <PermisoJefeSelect
          form={form}
          opciones={opciones}
          cantidad={companeros.length}
          textoVacio="Sin más servidores en su unidad"
        />
      </Grid.Col>
    </Grid>
  )
}
