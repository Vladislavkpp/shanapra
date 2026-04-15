# Project Design System

This document captures the visual language that already exists in the project so new pages and components stay consistent with the current site instead of drifting into a second design direction.

## Source Of Truth

Use these files as the main style references before adding new UI:

- `assets/css/menu.css` for the top navigation, brand tone, and public header treatment
- `assets/css/index.css` for the homepage hero, cards, metrics, and public CTA patterns
- `assets/css/auth.css` for auth flows and polished form shells
- `assets/css/cemetery-detail.css` for content-heavy public detail pages
- `assets/css/grave.css` for data forms and record editing states
- `assets/css/profile.css` for account settings, tabs, and dashboard-like layouts
- `assets/css/common.css` for legacy shared patterns that still shape many screens
- `docs/modal-style-system.md` for the unified modal pattern used across the site

## Brand Direction

The current site feels:

- calm
- respectful
- trustworthy
- light rather than dark
- product-oriented instead of decorative

The design should support a memorial-information platform: clear structure, soft contrast, readable forms, and restrained motion.

## Visual DNA

### Palette

Core colors already repeated across the codebase:

- deep navy: `#0f2a46`, `#0a2a4a`
- secondary navy-blue: `#174d78`
- primary action blue: `#2563eb`
- hover / pressed blue: `#1d4ed8`
- pale blue highlight: `#93c5fd`
- card white: `#ffffff`
- mist backgrounds: `#f8fbff`, `#edf3f9`, `#eef2f8`, `#e3e9f1`
- line / stroke colors: `#d2deec`, `#c8d7e9`, `#d8e3ef`, `#e5e7eb`

Semantic colors already in use:

- success: `#e9f7ef`, `#1f7a43`
- warning: `#fff1d1`, `#8a5600`
- danger: `#fce7ec`, `#9a2a3b`

Rules:

- Prefer blue-first accents.
- Use gradients as atmosphere, not as loud decoration.
- Keep large backgrounds light.
- Avoid introducing unrelated brand colors without a strong reason.

### Typography

Two font modes already exist:

- `Manrope` for public-facing, polished, brand-heavy screens
- `Inter` for dense forms, account pages, settings, admin-like UI, and utility flows

Guideline:

- If a page is marketing-like, navigational, or public record viewing, default to `Manrope`.
- If a page is form-heavy, settings-heavy, or data-entry-heavy, default to `Inter`.
- Do not mix both on the same small component unless matching an existing pattern.

### Shape Language

Rounded corners are consistent across the project:

- `10px` for controls and compact buttons
- `12px` for small cards, inputs, pills, and dropdowns
- `16px` to `18px` for medium cards and major containers
- `20px` to `24px` for hero shells and key public surfaces

Rule:

- Prefer soft rounded rectangles over sharp corners.
- Do not jump to ultra-round playful shapes unless already used for pills.

### Shadows

Shadows are soft and layered, usually blue-gray rather than black-heavy.

Preferred behavior:

- low elevation for cards
- medium elevation for hero blocks, floating panels, and sticky summaries
- stronger blur only for spotlight areas such as auth shell glow or hero cards

## Layout Patterns

Repeated layout traits:

- content width near `1240px`
- page paddings around `16px` to `24px`
- cards arranged with `12px` to `18px` inner spacing
- dark fixed top menu with a spacer block below it
- public sections use airy vertical rhythm and generous white space
- dashboard and form pages use tighter but still soft spacing

Rules:

- On new pages, start from the same width rhythm used in `index.css` and `cemetery-detail.css`.
- Preserve mobile stacking instead of shrinking everything into tiny columns.
- Keep sticky or floating elements subtle and usable, not flashy.

## Component Rules

### Buttons

Existing button families:

- primary: solid blue fill, white text
- ghost: white or transparent surface with blue text and soft border
- soft: pale blue fill with dark blue text

Behavior:

- slight lift on hover
- darker blue on hover for primary buttons
- rounded corners near `10px` to `12px`
- medium weight labels

### Inputs

Common input pattern:

- white background
- thin neutral border
- blue focus border
- blue outer focus ring
- generous horizontal padding

Do:

- reuse the current focus ring style
- keep borders subtle until focus
- keep textarea, select, and input visually aligned

Do not:

- create dark inputs on light pages
- invent a different focus color

### Cards

Card patterns across public pages:

- white or nearly white background
- pale border
- soft shadow
- rounded `12px` to `24px`
- internal spacing large enough to breathe

Use cards to group:

- search actions
- metrics
- support/help blocks
- detailed record information

### Navigation

The main menu is a signature component:

- dark blue gradient shell
- white / pale text
- pill-like hover states
- light blue active state

New header or subnav work should visually harmonize with `assets/css/menu.css`.

## Motion

Motion is present but restrained:

- short fades
- subtle lift on hover
- soft reveal on load
- glows used sparingly on hero surfaces

Rule:

- animate to reinforce hierarchy or feedback
- avoid busy micro-animation loops on standard components

## Naming Conventions

This project often uses page-scoped class prefixes. Keep doing that.

Examples already in use:

- `itp-` homepage
- `ta-` auth
- `cemdet-` cemetery detail
- `acm-` cemetery add form

Rule:

- For a new page, create a short page prefix and scope new classes under it.
- Avoid generic names like `.card`, `.title`, `.button-primary` unless you are extending an existing shared component intentionally.

## Consistency Rules For Future Work

When building or editing UI in this project:

1. Reuse the existing palette before adding a new color.
2. Reuse the existing radius scale before adding a new size.
3. Match the font mode to the page type: `Manrope` for public polished surfaces, `Inter` for utility flows.
4. Prefer light backgrounds with white cards and blue accents.
5. Reuse existing button and input treatment.
6. Keep hover and focus feedback visible but calm.
7. Check mobile layouts, especially fixed headers, stacked forms, and two-column blocks.
8. If an existing page already solves a similar UI problem, copy its pattern direction instead of inventing a new one.

## What To Avoid

- bright unrelated accent colors
- purple-first styling
- harsh black shadows
- cramped layouts
- mixing many visual styles on one page
- replacing calm gradients with flat dead backgrounds
- generic bootstrap-looking UI that ignores the project's existing tone
