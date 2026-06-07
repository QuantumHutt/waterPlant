# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project location

The git repository root and working directory is `/var/www/waterPlant`, but the actual
project lives one level down in `smart-home-1.0.0/`. **Run all npm/next commands from
`smart-home-1.0.0/`**, not the repo root.

```bash
cd smart-home-1.0.0
```

## Commands

```bash
npm i            # install dependencies (package-lock.json is the source of truth)
npm run dev      # start dev server (http://localhost:3000)
npm run build    # static export -> smart-home-1.0.0/out/
npm run start    # serve a production build
npm run lint     # next lint
```

There is no test suite or test runner configured.

## What this is

A Smart Home admin **dashboard template** (from ThemeWagon / CodesCandy, scaffolded via
v0). It is a UI-only front end: all device data, charts, users, and stats are hardcoded /
mocked inside the components. There is no backend, API, database, or real authentication —
the signin/signup pages are static forms.

## Architecture

- **Next.js 15 App Router + React 19 + TypeScript**, styled with **Tailwind CSS v4** and
  **shadcn/ui** (new-york style, components live in `components/ui/`).
- **Static site**: `next.config.mjs` sets `output: 'export'`, so the app builds to static
  HTML in `out/`. It is deployed to GitHub Pages — in production only, `basePath` and
  `assetPrefix` are set to `/smart-home-dashboard`. Keep this in mind for any absolute asset
  paths or links.
- **Build ignores errors**: `next.config.mjs` sets `eslint.ignoreDuringBuilds: true` and
  `typescript.ignoreBuildErrors: true`. A green build does NOT mean type-clean or lint-clean
  code — run `npm run lint` and check types manually if correctness matters.
- **Routing** uses route groups:
  - `app/(auth)/` — `signin`, `signup` (static, no shared layout).
  - `app/(dashboard)/` — `dashboard`, `devices`, `statistics`, `security`, `messages`,
    `profile`. These share `app/(dashboard)/layout.tsx`, a `"use client"` layout that wraps
    pages with the `Sidebar` + `Topbar` and manages mobile sidebar open/close state.
  - `app/page.tsx` redirects `/` to `/dashboard`.
- **Dashboard composition**: `components/dashboard/main-dashboard.tsx` assembles the dashboard
  page from feature widgets in `components/dashboard/` (e.g. `room-card`, `device-card`,
  `light-controls`, `energy-widget`, `consumption-chart`, `scenes`, `users`). Charts use
  `recharts`.

## Theming (two independent layers)

1. **Light/dark mode** via `next-themes` (`components/theme-provider.tsx`, toggled by
   `theme-toggle.tsx`). Default theme is `light`.
2. **Brand accent color** via a `data-brand` attribute on `<html>` (default `"purple"`, set
   in `app/layout.tsx`). `components/color-theme.tsx` switches between brand keys
   (purple/blue/teal/orange/pink), persists the choice in `localStorage`, and updates the
   attribute. Brand colors are defined as CSS variables in `app/globals.css` (oklch, with
   `--brand` / `--brand-2`); add new brands there and in the `BRANDS` array.

All theme/design tokens (colors, radius, sidebar palette) are CSS custom properties in
`app/globals.css` for both `:root` and `.dark`.

## Conventions

- Path alias `@/*` maps to the project root (`smart-home-1.0.0/`), e.g. `@/components/...`,
  `@/lib/utils`, `@/hooks/...`.
- Use the `cn()` helper from `lib/utils.ts` (clsx + tailwind-merge) for conditional classes.
- Icons come from `lucide-react`; toasts from `sonner` and the `use-toast` hook.
- Add new shadcn/ui primitives into `components/ui/` (config in `components.json`).
- `package.json` lists some stray deps (svelte, vue, @remix-run/react) left over from v0
  scaffolding — they are unused; this is a Next.js/React app only.

## Deployment

`.github/workflows/action.yml` only creates a GitHub Release when a `v*` tag is pushed; it
does not build or deploy. The static `out/` export is what gets published to GitHub Pages.
