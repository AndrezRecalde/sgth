import classes from '@/styles/inputs.contained.module.css'

/**
 * Patrón "contained" — la etiqueta se dibuja dentro del control.
 *
 * Es el estándar de TODOS los campos del SGTH. Devuelve `classNames` y el
 * orden de partes que Mantine necesita; se esparce sobre cualquier input:
 *
 *   const contained = useContainedInput()
 *   <TextInput label="Nombres" {...contained} {...register('nombres')} />
 *
 * En barras de filtros, donde el campo convive con botones y no necesita el
 * aire de un formulario de captura, se usa la variante compacta:
 *
 *   const contained = useContainedInput('sm')
 *
 * Los objetos son constantes de módulo: se crean una vez y no rompen la
 * memoización de los inputs al re-renderizar el formulario.
 */

const INPUT_WRAPPER_ORDER = [
  'description',
  'label',
  'input',
  'error',
] as ('description' | 'label' | 'input' | 'error')[]

const CONTAINED_MD = {
  classNames: {
    root: classes.root,
    wrapper: classes.wrapper,
    label: classes.label,
    description: classes.description,
    input: classes.input,
    innerInput: classes.innerInput,
  },
  inputWrapperOrder: INPUT_WRAPPER_ORDER,
} as const

const CONTAINED_SM = {
  classNames: {
    root: classes.root,
    wrapper: classes.wrapper,
    label: `${classes.label} ${classes.labelCompact}`,
    description: classes.description,
    input: `${classes.input} ${classes.inputCompact}`,
    innerInput: `${classes.innerInput} ${classes.innerInputCompact}`,
  },
  inputWrapperOrder: INPUT_WRAPPER_ORDER,
} as const

export type ContainedSize = 'sm' | 'md'

export function useContainedInput(size: ContainedSize = 'md') {
  return size === 'sm' ? CONTAINED_SM : CONTAINED_MD
}
