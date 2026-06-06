import classes from "@/styles/inputs.contained.module.css";

/**
 * Retorna classNames para el patrón "contained"
 * de Mantine (label inside input).
 * Usar en: TextInput, PasswordInput, Select,
 * Textarea, DateInput, NumberInput, etc.
 *
 * Uso:
 *   const contained = useContainedInput()
 *   <TextInput {...contained} label="Nombres" />
 */
const CONTAINED_PROPS = {
  classNames: {
    root: classes.root,
    wrapper: classes.wrapper,
    label: classes.label,
    description: classes.description,
    input: classes.input,
    innerInput: classes.innerInput,
  },
  inputWrapperOrder: [
    "description",
    "label",
    "input",
    "error",
  ] as ("description" | "label" | "input" | "error")[],
};

export function useContainedInput() {
  return CONTAINED_PROPS;
}
