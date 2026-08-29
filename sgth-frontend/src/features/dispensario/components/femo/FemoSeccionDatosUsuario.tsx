'use client'

import { Checkbox, Grid, Group, Select, Stack, Text, TextInput } from '@mantine/core'
import { useContainedInput } from '@/hooks/useContainedInput'
import type { FichaBaseForm } from '../../schemas/femo.schema'
import type { SexoPaciente } from '../../services/solicitudCertificacionService'
import { FemoSeccion } from './FemoSeccion'
import classes from './FemoSeccionDatosUsuario.module.css'

interface Props {
  fichaData: Partial<FichaBaseForm>
  onFichaChange: (data: Partial<FichaBaseForm>) => void
  sexo?: SexoPaciente
  /** Del expediente del paciente. El MSP lo pide en esta sección. */
  tipoSangre?: string | null
  cedula?: string
}

const SEXO_ETIQUETA: Record<string, string> = {
  masculino: 'Masculino',
  femenino: 'Femenino',
  otro: 'Otro',
}

/**
 * Sección A del formulario 028 — datos del establecimiento y del usuario.
 *
 * La institución, el RUC y el CIIU son constantes del dispensario y se imprimen
 * en el PDF; no se piden aquí. La identidad, el sexo y el grupo sanguíneo vienen
 * del expediente y se muestran para cotejar, no para editar: si algo está mal,
 * se corrige en el expediente y no en cada ficha.
 */
export function FemoSeccionDatosUsuario({
  fichaData, onFichaChange, sexo, tipoSangre, cedula,
}: Props) {
  const contained = useContainedInput()

  const set = (cambios: Partial<FichaBaseForm>) =>
    onFichaChange({ ...fichaData, ...cambios })

  return (
    <FemoSeccion letra="A" titulo="Datos del usuario">
      <dl className={classes.identidad}>
        <div className={classes.dato}>
          <dt>N.º de historia clínica</dt>
          <dd>{cedula || '—'}</dd>
        </div>
        <div className={classes.dato}>
          <dt>Sexo</dt>
          <dd>{sexo ? SEXO_ETIQUETA[sexo] ?? sexo : 'Sin registrar'}</dd>
        </div>
        <div className={classes.dato}>
          <dt>Grupo sanguíneo</dt>
          <dd>{tipoSangre || 'Sin registrar'}</dd>
        </div>
      </dl>

      <Grid>
        <Grid.Col span={{ base: 12, md: 4 }}>
          <Select
            label="Lateralidad"
            placeholder="Mano dominante"
            data={[
              { value: 'derecha', label: 'Derecha' },
              { value: 'izquierda', label: 'Izquierda' },
            ]}
            clearable
            {...contained}
            value={fichaData.lateralidad ?? null}
            onChange={(v) => set({ lateralidad: v as FichaBaseForm['lateralidad'] })}
          />
        </Grid.Col>
      </Grid>

      <Stack gap="xs">
        <Text size="xs" fw={600} c="dimmed">
          Grupo de atención prioritaria
        </Text>

        {/* Los cuatro grupos del formulario 028. */}
        <Group gap="lg">
          {/* No se ofrece a pacientes hombres. Se muestra cuando el sexo no
              está registrado, para no perder el dato por un expediente
              incompleto. */}
          {sexo !== 'masculino' && (
            <Checkbox
              label="Embarazada"
              checked={fichaData.grupo_embarazada ?? false}
              onChange={(e) => set({ grupo_embarazada: e.currentTarget.checked })}
            />
          )}
          <Checkbox
            label="Persona con discapacidad"
            checked={fichaData.grupo_discapacidad ?? false}
            onChange={(e) => set({
              grupo_discapacidad: e.currentTarget.checked,
              // Al desmarcar se limpia el porcentaje: dejarlo guardado
              // enviaría un dato que ya no corresponde a nadie.
              ...(e.currentTarget.checked ? {} : { porcentaje_discapacidad: '' }),
            })}
          />
          <Checkbox
            label="Enfermedad catastrófica"
            checked={fichaData.grupo_enfermedad_catastrofica ?? false}
            onChange={(e) => set({ grupo_enfermedad_catastrofica: e.currentTarget.checked })}
          />
          <Checkbox
            label="Adulto mayor"
            checked={fichaData.grupo_adulto_mayor ?? false}
            onChange={(e) => set({ grupo_adulto_mayor: e.currentTarget.checked })}
          />
        </Group>

        {fichaData.grupo_discapacidad && (
          <Grid>
            <Grid.Col span={{ base: 12, md: 4 }}>
              <TextInput
                label="Porcentaje de discapacidad"
                placeholder="Ej: 40"
                {...contained}
                value={fichaData.porcentaje_discapacidad ?? ''}
                onChange={(e) => set({ porcentaje_discapacidad: e.currentTarget.value })}
              />
            </Grid.Col>
          </Grid>
        )}
      </Stack>
    </FemoSeccion>
  )
}
