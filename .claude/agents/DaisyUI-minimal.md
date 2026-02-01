# Project Rules: DaisyUI Required

This project uses **DaisyUI 5** with **Tailwind CSS 4**. All frontend development MUST use DaisyUI components and patterns.

## Non-Negotiable Rules

1. **Always use DaisyUI components** - Check if a component exists before building custom
2. **Use semantic colors only** - `primary`, `secondary`, `base-*`, `success`, `error`, etc.
3. **Never use Tailwind color names** - No `blue-500`, `gray-800`, `red-600`
4. **Zero custom CSS** - Compose with DaisyUI + Tailwind utilities only

## Quick Reference

### Colors (Use These)
```
bg-primary, bg-secondary, bg-accent, bg-neutral
bg-base-100, bg-base-200, bg-base-300
bg-info, bg-success, bg-warning, bg-error
text-base-content, text-primary-content, etc.
```

### Colors (Never Use)
```
❌ bg-blue-500, text-gray-800, bg-red-600, text-slate-900
```

### Core Components
- **Buttons**: `btn btn-primary`, `btn btn-outline`, `btn btn-ghost`
- **Cards**: `card`, `card-body`, `card-title`, `card-actions`
- **Forms**: `input`, `select`, `textarea`, `checkbox`, `toggle`, `radio`
- **Feedback**: `alert`, `toast`, `loading`, `progress`
- **Navigation**: `navbar`, `menu`, `tabs`, `breadcrumbs`, `dock`
- **Layout**: `drawer`, `modal`, `collapse`, `divider`

### Sizes
`*-xs`, `*-sm`, `*-md`, `*-lg`, `*-xl`

### Styles  
`*-outline`, `*-dash`, `*-soft`, `*-ghost`

## Before Committing

- [ ] Used DaisyUI component (not custom)
- [ ] Semantic colors only
- [ ] Works in dark mode (automatic with DaisyUI)
- [ ] No custom CSS

## Docs
- https://daisyui.com/components/
- https://daisyui.com/docs/colors/
