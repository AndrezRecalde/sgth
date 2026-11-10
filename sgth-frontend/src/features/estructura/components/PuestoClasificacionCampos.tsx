'use client'

import { Alert, Grid, Select } from '@mantine/core'
import { IconInfoCircle } from '@tabler/icons-react'
import { Controller, useWatch, type Control, type FieldErrors, type UseFormSetValue } from 'react-hook-form'
import { useContainedInput } from '@/hooks/useContainedInput'
import { dinero } from '@/lib/dinero'
import { COMPLEJIDADES, rolSugeridoDelGrupo, rolesDelRegimen, type RegimenPuesto } from '../constants/clasificacionPuesto'
import type { GrupoOcupacional } from '@/types/api'
import type { PuestoFormData } from '../schemas/puesto.schema'

interface Props {
  control:  Control<PuestoFormData>
  errors:   FieldErrors<PuestoFormData>
  /** Para poner el rol que sugiere el grupo elegido. */
  setValue: UseFormSetValue<PuestoFormData>
  regimen:  RegimenPuesto
  grupos:   GrupoOcupacional[]
  /** Ocupado, el grado cambia con «Revisar la clasificación». */
  ocupado:  boolean
}

const SOLO_CON_REVISION = 'Ocupado: cambia con «Revisar la clasificación».'

/**
 * El grado, la complejidad y el rol del puesto. Lo que se ofrece depende del
 * régimen: la escala de grupos ocupacionales es la de la LOSEP, y el rol
 * «Código del Trabajo» es el de los obreros.
 */
export function PuestoClasificacionCampos({ control, errors, setValue, regimen, grupos, ocupado }: Props) {
  const contained = useContainedInput()
  const rol = useWatch({ control, name: 'rol_puesto' })

  /**
   * Cada grupo sugiere el rol que más usa el Manual de Puestos en su grado
   * (D3). Solo llena un rol vacío: el que ya eligió Talento Humano se respeta.
   */
  const sugerirRol = (grupoId: number | null) => {
    if (rol) return
    const sugerido = rolSugeridoDelGrupo(grupos.find((g) => g.id === grupoId)?.rol_puesto, regimen)
    if (sugerido) setValue('rol_puesto', sugerido, { shouldDirty: true })
  }

  // Solo los grados del régimen del puesto: el backend rechaza los de la otra
  // escala, y antes se ofrecían todos.
  const opcionesGrupo = grupos
    .filter((g) => g.regimen === regimen)
    .map((g) => ({
      value: String(g.id),
      label: `${g.grado_codigo} — ${g.denominacion_generica ?? g.grupo} (${dinero(g.rmu)})`,
    }))

  return (
    <>
      {/* La escala de grupos ocupacionales es del régimen LOSEP: fija el
          grado y la RMU del puesto. Bajo Código del Trabajo la
          remuneración se negocia en cada contrato, así que pedir aquí un
          grupo ocupacional invita a cargar un dato que no aplica. */}
      {regimen === 'losep' ? (
        <Grid.Col span={12}>
          <Controller
            name="grupo_ocupacional_id"
            control={control}
            render={({ field }) => (
              <Select
                label="Grupo ocupacional (LOSEP)"
                description={ocupado ? SOLO_CON_REVISION : 'Define el grado y la RMU del puesto, y sugiere su rol.'}
                placeholder="Seleccione el grupo"
                data={opcionesGrupo}
                searchable
                clearable
                disabled={ocupado}
                {...contained}
                value={field.value ? String(field.value) : null}
                onChange={(v) => {
                  field.onChange(v ? Number(v) : null)
                  sugerirRol(v ? Number(v) : null)
                }}
                error={errors.grupo_ocupacional_id?.message}
              />
            )}
          />
        </Grid.Col>
      ) : (
        <Grid.Col span={12}>
          <Alert variant="light" color="ocean" icon={<IconInfoCircle size={16} />}>
            Bajo Código del Trabajo la remuneración se pacta en cada contrato,
            no se deriva de una escala. Se ingresa al aprobar la acción de
            personal de ingreso.
          </Alert>
        </Grid.Col>
      )}
      <Grid.Col span={{ base: 12, sm: 4 }}>
        <Controller
          name="nivel_complejidad"
          control={control}
          render={({ field }) => (
            <Select
              label="Complejidad"
              placeholder="Seleccione"
              data={COMPLEJIDADES}
              clearable
              {...contained}
              value={field.value ?? null}
              onChange={(v) => field.onChange(COMPLEJIDADES.find((c) => c.value === v)?.value ?? null)}
              error={errors.nivel_complejidad?.message}
            />
          )}
        />
      </Grid.Col>
      <Grid.Col span={{ base: 12, sm: 8 }}>
        <Controller
          name="rol_puesto"
          control={control}
          render={({ field }) => (
            <Select
              label="Rol del puesto"
              placeholder="Seleccione el rol"
              data={rolesDelRegimen(regimen)}
              clearable
              {...contained}
              value={field.value ?? null}
              onChange={(v) => field.onChange(rolesDelRegimen(regimen).find((r) => r.value === v)?.value ?? null)}
              error={errors.rol_puesto?.message}
            />
          )}
        />
      </Grid.Col>
    </>
  )
}
