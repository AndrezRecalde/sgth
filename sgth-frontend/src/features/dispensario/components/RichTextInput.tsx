'use client'

import { RichTextEditor, Link } from '@mantine/tiptap'
import { useEditor } from '@tiptap/react'
import StarterKit from '@tiptap/starter-kit'
import '@mantine/tiptap/styles.css'
import { Text, Stack } from '@mantine/core'
import { useEffect } from 'react'

interface Props {
  label:        string
  placeholder?: string
  value:        string
  onChange:     (value: string) => void
  required?:    boolean
  error?:       string
  description?: string
}

export function RichTextInput({
  label, placeholder, value, onChange,
  required, error, description,
}: Props) {
  const editor = useEditor({
    extensions: [
      StarterKit,
      Link.configure({ openOnClick: false }),
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
          <RichTextEditor.ControlsGroup>
            <RichTextEditor.Bold />
            <RichTextEditor.Italic />
            <RichTextEditor.Underline />
          </RichTextEditor.ControlsGroup>
          <RichTextEditor.ControlsGroup>
            <RichTextEditor.BulletList />
            <RichTextEditor.OrderedList />
          </RichTextEditor.ControlsGroup>
          <RichTextEditor.ControlsGroup>
            <RichTextEditor.ClearFormatting />
          </RichTextEditor.ControlsGroup>
        </RichTextEditor.Toolbar>
        <RichTextEditor.Content
          mih={120}
        />
      </RichTextEditor>
      {error && (
        <Text size="xs" c="red">{error}</Text>
      )}
    </Stack>
  )
}
