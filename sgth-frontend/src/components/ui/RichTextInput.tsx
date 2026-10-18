'use client'

import { RichTextEditor } from '@mantine/tiptap'
import { useEditor } from '@tiptap/react'
import StarterKit from '@tiptap/starter-kit'
import Highlight from '@tiptap/extension-highlight'
import { Text, Stack } from '@mantine/core'
import { useEffect } from 'react'

interface Props {
  label:        string
  value:        string
  onChange:     (value: string) => void
  required?:    boolean
  error?:       string
  description?: string
  minHeight?:   number
  maxHeight?:   number
  /**
   * Agrega títulos, citas y enlaces a la barra. Es lo que permite el cuerpo de
   * una noticia o un comunicado (`HtmlEnriquecido` en el backend); la nota
   * clínica se queda con la barra corta.
   */
  completo?:    boolean
}

/**
 * Editor de texto enriquecido (TipTap). Lo guardado es HTML, y el backend lo
 * limpia al recibirlo con su propia lista blanca: la barra solo ofrece lo que
 * esa lista deja pasar.
 */
export function RichTextInput({
  label, value, onChange,
  required, error, description,
  minHeight = 120,
  maxHeight = 400,
  completo = false,
}: Props) {
  const editor = useEditor({
    // El editor no se crea en el primer render, sino después. Es lo que TipTap
    // ya hacía por su cuenta al detectar Next.js —evita que el HTML del
    // servidor y el del navegador difieran—, pero al no estar la opción escrita
    // lo avisaba por consola en cada montaje. Aquí se deja dicho.
    //
    // Va en `false` y no en `true`: aunque el componente sea de cliente, Next
    // lo prerenderiza igual en el servidor, y con `true` TipTap cambiaría este
    // aviso por el de «SSR detected».
    immediatelyRender: false,
    extensions: [
      // El enlace se configura DENTRO del StarterKit: en TipTap 3 ya lo trae, y
      // registrarlo además por separado dejaba dos extensiones compitiendo por
      // el nombre `link`. Ganaba una u otra sin criterio, así que
      // `openOnClick: false` podía quedarse sin efecto y un clic en un enlace
      // dentro de una nota clínica navegaba en vez de situar el cursor.
      StarterKit.configure({
        link: { openOnClick: false },
      }),
      Highlight.configure({ multicolor: false }),
    ],
    content: value || '',
    onUpdate: ({ editor }) => {
      const html = editor.getHTML()
      onChange(html === '<p></p>' ? '' : html)
    },
  })

  useEffect(() => {
    if (editor && value === '' && editor.getHTML() !== '<p></p>') {
      editor.commands.setContent('')
    }
  }, [value, editor])

  return (
    <Stack gap={4}>
      <Text size="sm" fw={500}>
        {label}
        {required && (
          <Text span c="red" ml={4}>*</Text>
        )}
      </Text>
      {description && (
        <Text size="xs" c="dimmed">{description}</Text>
      )}
      <RichTextEditor
        editor={editor}
        style={{
          border: error
            ? '1px solid var(--mantine-color-red-6)'
            : undefined,
        }}
      >
        <RichTextEditor.Toolbar sticky stickyOffset={0}>
          {completo && (
            <RichTextEditor.ControlsGroup>
              <RichTextEditor.H2 />
              <RichTextEditor.H3 />
              <RichTextEditor.Blockquote />
            </RichTextEditor.ControlsGroup>
          )}
          <RichTextEditor.ControlsGroup>
            <RichTextEditor.Bold />
            <RichTextEditor.Italic />
            <RichTextEditor.Underline />
            <RichTextEditor.Highlight />
          </RichTextEditor.ControlsGroup>
          <RichTextEditor.ControlsGroup>
            <RichTextEditor.BulletList />
            <RichTextEditor.OrderedList />
          </RichTextEditor.ControlsGroup>
          {completo && (
            <RichTextEditor.ControlsGroup>
              <RichTextEditor.Link />
              <RichTextEditor.Unlink />
            </RichTextEditor.ControlsGroup>
          )}
          <RichTextEditor.ControlsGroup>
            <RichTextEditor.ClearFormatting />
          </RichTextEditor.ControlsGroup>
        </RichTextEditor.Toolbar>
        <RichTextEditor.Content
          style={{
            minHeight,
            maxHeight,
            overflowY: 'auto',
          }}
        />
      </RichTextEditor>
      {error && (
        <Text size="xs" c="red">{error}</Text>
      )}
    </Stack>
  )
}
