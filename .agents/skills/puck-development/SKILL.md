---
name: puck-development
description: Desarrolla y configura el editor visual Puck (@puckeditor/core) en React.
  Actívalo cuando trabajes con puckConfig, Config, Components, slot fields,
  root.render, puck.css, <Puck>, <Render>, usePuck, PuckApi, DropZone,
  resolveData, resolveFields, permissions, categorías, viewports, campos
  personalizados, data migration, o cualquier integración de Puck editor.
  Úsalo para crear/configurar la config, bloques, root render, slots, columnas,
  grid, y el renderizado frontend del contenido.
---

# Puck Development (@puckeditor/core)

Editor visual drag-and-drop open-source para React.js. MIT license.

## Instalación

```bash
npm i @puckeditor/core
import "@puckeditor/core/puck.css";
```

## Componentes principales

### `<Puck>` — Editor UI
```tsx
<Puck config={config} data={initialData} onPublish={save} />
```

### `<Render>` — Renderizado público
```tsx
<Render config={config} data={data} />
```

## Config (`Config<Components, RootProps>`)

### `components` (requerido)
Objeto donde cada key es un nombre de componente con `ComponentConfig`:

```tsx
type Components = {
  HeadingBlock: { children: string; level: 'h1' | 'h2' | 'h3' };
};

const config: Config<Components> = {
  components: {
    HeadingBlock: {
      fields: { children: { type: "text" }, level: { type: "select", options: [...] } },
      defaultProps: { children: "Título", level: "h2" },
      render: ({ children, level: Tag }) => <Tag>{children}</Tag>,
    },
  },
};
```

### `root` (opcional)
Renderiza un wrapper alrededor de todos los componentes:

```tsx
root: {
  fields: { title: { type: "text" } },
  defaultProps: { title: "Sin título" },
  render: ({ children, title }) => (
    <div>
      <header>{title}</header>
      <main>{children}</main>
      <footer>© 2026</footer>
    </div>
  ),
}
```

**Importante**: El root.render se renderiza TANTO en el editor como en el frontend (`<Render>`). Si además tienes un layout en Inertia/Next.js, verás headers/footers duplicados. El root.render es parte del **contenido**, no de la UI del editor.

### `categories` (opcional)
```tsx
categories: {
  layout: { components: ['ColumnsBlock', 'GridBlock'], title: 'Layout' },
  typography: { components: ['HeadingBlock', 'TextBlock'], title: 'Tipografía' },
}
```

## ComponentConfig

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `render(props)` | `(props) => ReactNode` | **Requerido**. Renderiza el componente |
| `fields` | `Record<string, Field>` | Campos editables en el panel |
| `defaultProps` | `Partial<Props>` | Props por defecto al insertar |
| `inline` | `boolean` | Sin wrapper div (requiere `puck.dragRef`) |
| `label` | `string` | Etiqueta visible (default: nombre del componente) |
| `metadata` | `object` | Metadatos extra |
| `permissions` | `Permissions` | Permisos globales del componente |
| `resolveData(data, params)` | `async` | Modifica props dinámicamente |
| `resolveFields(data, params)` | `async` | Campos dinámicos según estado |
| `resolvePermissions(data, params)` | `async` | Permisos dinámicos por instancia |

### Render props
- `id: string` — ID único del componente
- `puck.dragRef` — ref para `inline`
- `puck.isEditing: boolean` — true si está en el editor
- `puck.metadata` — metadatos mergeados
- `puck.renderDropZone(zone)` — renderiza DropZone (alternativa server component)
- `...props` — props de los fields

## Fields (sistema de tipos)

| Tipo | Descripción |
|------|-------------|
| `{ type: "text" }` | Input de texto simple |
| `{ type: "textarea" }` | Textarea |
| `{ type: "number", min, max, step }` | Input numérico |
| `{ type: "select", options: [{label, value}] }` | Select desplegable |
| `{ type: "radio", options: [{label, value}] }` | Radio buttons |
| `{ type: "richtext" }` | Editor rich text |
| `{ type: "slot" }` | Zona drag-and-drop con componentes anidados |
| `{ type: "object", objectFields: {...} }` | Sub-campos agrupados |
| `{ type: "array", arrayFields: {...} }` | Lista de items repetibles |
| `{ type: "custom", render: () => JSX }` | UI completamente custom |
| `{ type: "external", fetchUrl: string }` | Data de API externa |

Tipo base compartido: `{ type, label?, description?, defaultTab?: string }`

### Slot field (clave para layouts)

```tsx
import { Slot } from "@puckeditor/core";

type Props = {
  ColumnsBlock: { column1: Slot; column2: Slot; columns: number; gap: string };
};

// En render
render: ({ column1: Col1, column2: Col2, columns, gap }) => (
  <div style={{ display: "grid", gridTemplateColumns: `repeat(${columns}, 1fr)`, gap }}>
    <Col1 />
    <Col2 />
  </div>
)
```

Parámetros del slot:
- `allow: string[]` — solo estos componentes
- `disallow: string[]` — todos excepto estos
- `minEmptyHeight: string | number` — altura mínima vacío (default: 128px)
- `style: CSSProperties` — estilo CSS (flex/grid)
- `className: string`
- `collisionAxis: "x" | "y" | "dynamic"`
- `as: ElementType` — render como otro elemento/html tag

### Arrays como defaultProps de slots
```tsx
defaultProps: {
  content: [{ type: "Card", props: { text: "Pre-populado" } }],
}
```

## Data Model

```ts
type Data = {
  content: ComponentData[];       // Componentes del content principal
  root: { props: RootProps };     // Props del root
  zones?: Record<string, ComponentData[]>; // DropZones (deprecated con slots)
};

type ComponentData = {
  type: string;
  props: { id: string; [key: string]: any };
};
```

## inline mode (para CSS avanzado)

Sin wrapper div. Requiere `puck.dragRef`:

```tsx
{
  inline: true,
  render: ({ puck }) => <div ref={puck.dragRef}>...</div>,
}
```

## Dynamic Props (resolveData)

```tsx
resolveData: async ({ props }, { changed }) => {
  return { props: { resolved: props.title }, readOnly: { resolved: true } };
};
```

Triggers: `"insert"`, `"replace"`, `"move"`, `"load"`, `"force"`.

## usePuck hook

```tsx
const usePuck = createUsePuck();
const selectedType = usePuck((s) => s.selectedItem?.type);
```

Accede a: `appState`, `dispatch`, `history`, `selectedItem`, `getItemBySelector`, etc.

## Buenas prácticas con este proyecto

- El **root.render** es parte del contenido visible. Si usas `<Render>` dentro de una página que ya tiene layout (header/footer), verás duplicados. Solución: renderiza un `<Render>` sin layout extra, o no pongas header/footer en root.render.
- Para render público, usa `<Render config={puckConfig} data={pagina.contenido} />` sin wrappers.
- Los slots fijos (column1, column2, etc.) se definen como campos `type: "slot"` en fields. Se reciben como funciones render en render().
- El `onPublish` recibe el Data completo; guárdalo como JSON en la BD.
- Los componentes renderizados dentro de Puck no tienen acceso a Inertia (`usePage`, `router`). Si necesitas navegación real, hazlo en root.render o fuera de Puck.
- Siempre tipa con `Config<Components, RootProps>`.
- Usa `labels` descriptivos en cada componente.

## Archivos relevantes del proyecto

- `resources/js/paginas/puck-config.tsx` — Config principal
- `resources/js/paginas/blocks/` — Bloques individuales
- `resources/js/paginas/blocks/shared-block-fields.tsx` — Campos compartidos
- `resources/js/pages/paginas/editar.tsx` — Página editor
- `resources/js/pages/paginas/mostrar.tsx` — Página render público