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
export function useContainedInput() {
  return {
    classNames: {
      root: classes.root,
      wrapper: classes.wrapper,
      label: classes.label,
      input: classes.input,
      innerInput: classes.innerInput,
    },
    //variant: "filled" as const,
  };
}
