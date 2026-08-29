'use client'

import { Stack } from '@mantine/core'
import type {
  AntecedenteForm, AntecedenteReproductivoForm,
  ConsumoSustanciaForm, FichaBaseForm,
} from '../../schemas/femo.schema'
import type { SexoPaciente } from '../../services/solicitudCertificacionService'
import { FemoSeccionDatosUsuario } from './FemoSeccionDatosUsuario'
import { FemoSeccionMotivoConsulta } from './FemoSeccionMotivoConsulta'
import { FemoSeccionAntecedentes } from './FemoSeccionAntecedentes'
import { FemoSeccionEnfermedadActual } from './FemoSeccionEnfermedadActual'
import { FemoSeccionSignosVitales } from './FemoSeccionSignosVitales'

interface Props {
  fichaData: Partial<FichaBaseForm>
  constantesData: Record<string, number | null>
  antecedentes: AntecedenteForm[]
  antecedenteReproductivo: Partial<AntecedenteReproductivoForm>
  consumoSustancias: ConsumoSustanciaForm[]
  onFichaChange: (data: Partial<FichaBaseForm>) => void
  onAntecedentesChange: (data: AntecedenteForm[]) => void
  onAntecedenteReproductivoChange: (data: Partial<AntecedenteReproductivoForm>) => void
  onConsumoSustanciasChange: (data: ConsumoSustanciaForm[]) => void
  sexo?: SexoPaciente
  tipoSangre?: string | null
  cedula?: string
}

/**
 * Primer paso de la ficha FEMO: secciones A a E del formulario 028, en el
 * mismo orden y con las mismas letras que el impreso del MSP.
 *
 * El orden importa: el médico llena esto con el formulario oficial al lado y
 * navega por letra. Antes las secciones no coincidían — los signos vitales
 * salían como «B» cuando en el impreso son la «E», y la enfermedad actual, que
 * es la «D», estaba escondida como un campo dentro de los antecedentes.
 */
export function FemoPaso1({
  fichaData, constantesData, antecedentes,
  antecedenteReproductivo, consumoSustancias,
  onFichaChange, onAntecedentesChange,
  onAntecedenteReproductivoChange, onConsumoSustanciasChange,
  sexo, tipoSangre, cedula,
}: Props) {
  return (
    <Stack gap="xl">
      <FemoSeccionDatosUsuario
        fichaData={fichaData}
        onFichaChange={onFichaChange}
        sexo={sexo}
        tipoSangre={tipoSangre}
        cedula={cedula}
      />

      <FemoSeccionMotivoConsulta
        fichaData={fichaData}
        onFichaChange={onFichaChange}
      />

      <FemoSeccionAntecedentes
        fichaData={fichaData}
        antecedentes={antecedentes}
        antecedenteReproductivo={antecedenteReproductivo}
        consumoSustancias={consumoSustancias}
        onFichaChange={onFichaChange}
        onAntecedentesChange={onAntecedentesChange}
        onAntecedenteReproductivoChange={onAntecedenteReproductivoChange}
        onConsumoSustanciasChange={onConsumoSustanciasChange}
        sexo={sexo}
      />

      <FemoSeccionEnfermedadActual
        fichaData={fichaData}
        onFichaChange={onFichaChange}
      />

      <FemoSeccionSignosVitales constantesData={constantesData} />
    </Stack>
  )
}
