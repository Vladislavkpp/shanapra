---
name: site-design-system
description: Use when working on frontend or UI in this repository to keep new pages, components, and redesigns aligned with the existing memorial-platform visual system instead of introducing a second design style.
---

# Site Design System

Use this skill for any frontend, HTML, CSS, PHP template, or UI refactor work in this repository.

## Goal

Keep the project in one visual system by reusing its existing palette, typography split, radius scale, card treatment, and public-vs-utility page patterns.

## First Read

Read these files before making significant UI choices:

- `docs/design-system.md`
- `docs/design-components.md`
- `codex/skills/site-design-system/references/project-style.md`

Then inspect the most relevant source CSS for the screen you are changing:

- `assets/css/menu.css`
- `assets/css/index.css`
- `assets/css/auth.css`
- `assets/css/cemetery-detail.css`
- `assets/css/grave.css`
- `assets/css/profile.css`
- `assets/css/common.css`

## Core Rules

- Reuse existing colors before adding new ones.
- Reuse existing radii before inventing new geometry.
- Default to light surfaces, white cards, and blue accents.
- Use `Manrope` for polished public-facing screens.
- Use `Inter` for dense forms, settings, and utility flows.
- Match existing button styles: primary, ghost, or soft.
- Reuse the blue focus ring style for fields and interactive controls.
- Keep motion subtle: gentle hover lift, fade-in, restrained glow.
- Preserve mobile usability and the fixed-header spacing pattern.

## Public Vs Utility Split

Public pages usually look like:

- atmospheric light background
- strong hero or lead card
- `Manrope`
- larger radii and softer composition

Utility pages usually look like:

- cleaner shell
- more direct forms and data presentation
- `Inter`
- tighter spacing but still rounded and soft

Choose the mode that already fits the page instead of blending both arbitrarily.

## Component Guidance

- Prefer page-scoped CSS prefixes such as `itp-`, `ta-`, `cemdet-`, `acm-`.
- Avoid generic class names unless extending an existing shared component.
- Prefer extending existing patterns over introducing a new component family.
- If a page already solves a similar layout problem, mirror that approach first.

## Design Guardrails

Do not introduce:

- purple-first palettes
- harsh dark-mode styling on otherwise light pages
- flat generic bootstrap-looking sections
- heavy black shadows
- cramped form controls
- flashy animation on standard content

## When Unsure

If the repository offers multiple valid precedents, choose the one closest to the page type:

- homepage or public discovery: `assets/css/index.css`
- auth or onboarding: `assets/css/auth.css`
- detailed record pages: `assets/css/cemetery-detail.css` or `assets/css/grave.css`
- user settings or dashboards: `assets/css/profile.css`
- top-level navigation: `assets/css/menu.css`

## Deliverable Expectation

When making UI changes:

1. keep the current brand feel
2. reuse existing visual tokens and component behavior
3. ensure desktop and mobile both feel intentional
4. avoid creating a parallel design language
