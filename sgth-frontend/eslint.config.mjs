import { defineConfig, globalIgnores } from "eslint/config";
import nextVitals from "eslint-config-next/core-web-vitals";
import nextTs from "eslint-config-next/typescript";

const eslintConfig = defineConfig([
  ...nextVitals,
  ...nextTs,
  // Override default ignores of eslint-config-next.
  globalIgnores([
    // Default ignores of eslint-config-next:
    ".next/**",
    "out/**",
    "build/**",
    "next-env.d.ts",
  ]),
  {
    rules: {
      // Un identificador que empieza por `_` declara que no se usa a
      // propósito: parámetros que hay que mantener por la firma, elementos
      // descartados al desestructurar, errores capturados que no se inspeccionan.
      "@typescript-eslint/no-unused-vars": [
        "warn",
        {
          argsIgnorePattern: "^_",
          varsIgnorePattern: "^_",
          caughtErrorsIgnorePattern: "^_",
          destructuredArrayIgnorePattern: "^_",
        },
      ],
    },
  },
  {
    files: ["**/*.tsx"],
    rules: {
      // La validación de formularios la hace Zod vía zodResolver. El prop
      // `required` de Mantine llega al DOM como atributo `required` real, y
      // sin `noValidate` el navegador cancela el evento `submit` antes de que
      // React Hook Form llegue a ejecutarse: no se dispara la petición y no se
      // renderiza ningún mensaje de error.
      "no-restricted-syntax": [
        "error",
        {
          selector:
            'JSXOpeningElement[name.name="form"]:not(:has(JSXAttribute[name.name="noValidate"], JSXSpreadAttribute))',
          message:
            "Todo <form> debe llevar noValidate: la validación la hace Zod (zodResolver), no el navegador. Sin él, el `required` de Mantine bloquea el submit y los mensajes de error nunca se pintan.",
        },
      ],
    },
  },
]);

export default eslintConfig;
