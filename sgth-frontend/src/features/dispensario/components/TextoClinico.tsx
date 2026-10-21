import { Box, Text, type MantineFontSize } from '@mantine/core'
import classes from './TextoClinico.module.css'

interface Props {
  valor:        string
  /**
   * Si el campo se escribe con el editor enriquecido: el diagnóstico detallado
   * y el plan, los mismos de `ConsultaMedica::CAMPOS_HTML`, que el servidor
   * sanea al guardar.
   */
  enriquecido?: boolean
  size?:        MantineFontSize
}

/**
 * Un campo de la nota clínica, como HTML o como texto según lo que es.
 *
 * Se decidía mirando si el valor empezaba por `<`, así que cualquier campo de
 * texto —el motivo, el examen físico, las notas— se pintaba como HTML si
 * alguien escribía una etiqueta, y esos campos el servidor no los sanea porque
 * son texto. Ahora lo decide el campo: solo los dos del editor van como HTML.
 */
export function TextoClinico({ valor, enriquecido = false, size = 'sm' }: Props) {
  if (enriquecido) {
    return (
      <Box
        fz={size}
        className={classes.enriquecido}
        dangerouslySetInnerHTML={{ __html: valor }}
      />
    )
  }

  // Sale de un área de texto: los saltos de línea son del médico.
  return (
    <Text size={size} className={classes.texto}>
      {valor}
    </Text>
  )
}
