import { SegmentedControl } from '@mantine/core'
import { IconCheck, IconHelpCircle, IconMinus } from '@tabler/icons-react'
import classes from './SiNoSinRespuesta.module.css'

interface Props {
  pregunta: string
  /** `true` sí, `false` no, `null` o `undefined` sin responder. */
  valor?: boolean | null
  onChange: (valor: boolean | null) => void
}

/** Los tres estados viajan como texto porque SegmentedControl no admite nulo. */
const SIN_RESPUESTA = 'sin_respuesta'

function aTexto(valor?: boolean | null): string {
  if (valor === true) return 'si'
  if (valor === false) return 'no'
  return SIN_RESPUESTA
}

function aBooleano(texto: string): boolean | null {
  if (texto === 'si') return true
  if (texto === 'no') return false
  return null
}

/**
 * Pregunta de historia clínica con tres respuestas posibles.
 *
 * «No respondió» no es lo mismo que «respondió que no», y en una ficha médica
 * esa diferencia importa: un campo que arranca en «no» afirma algo que nadie
 * preguntó. Por eso el estado inicial es explícito.
 *
 * Se presenta como tarjeta y no como un campo más porque son datos que deciden
 * una atención de urgencia: pasarlos por alto tiene consecuencias. Mientras no
 * se responda, la tarjeta se señala en ámbar.
 */
export function SiNoSinRespuesta({ pregunta, valor, onChange }: Props) {
  const estado = aTexto(valor)
  const pendiente = estado === SIN_RESPUESTA

  const Icono = pendiente ? IconHelpCircle : valor ? IconCheck : IconMinus

  return (
    <div
      className={classes.tarjeta}
      data-estado={pendiente ? 'pendiente' : estado}
    >
      <div className={classes.cabecera}>
        <span className={classes.icono} aria-hidden="true">
          <Icono size={17} stroke={1.8} />
        </span>
        <span className={classes.pregunta}>{pregunta}</span>
      </div>

      <SegmentedControl
        fullWidth
        size="sm"
        value={estado}
        onChange={(v) => onChange(aBooleano(v))}
        aria-label={pregunta}
        data={[
          { value: 'si', label: 'Sí' },
          { value: 'no', label: 'No' },
          { value: SIN_RESPUESTA, label: 'Sin respuesta' },
        ]}
      />

      {pendiente && (
        <span className={classes.aviso}>Pendiente de preguntar al paciente</span>
      )}
    </div>
  )
}
