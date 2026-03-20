# Project Component Guide

Use this document together with `docs/design-system.md` when adding or redesigning screens.

## Public Page Formula

For public-facing pages, prefer this structure:

1. dark fixed top navigation
2. light atmospheric page background
3. one main hero or top card with `18px` to `24px` radius
4. follow-up cards or sections in a grid
5. help / support / contact block near key actions

Reference files:

- `assets/css/menu.css`
- `assets/css/index.css`
- `assets/css/cemetery-detail.css`
- `assets/css/public-profile.css`

## Form Page Formula

For data-entry and account flows, prefer this structure:

1. stable page shell with light gradient background
2. a clear form container card
3. compact labels above fields
4. blue focus ring on active fields
5. primary action pinned to the end of the form block

Reference files:

- `assets/css/auth.css`
- `assets/css/grave.css`
- `assets/css/profile.css`

## Buttons

### Primary

Use for the main action on a card or form.

Visual traits:

- `#2563eb` background
- `#1d4ed8` hover state
- white label
- `10px` to `12px` radius
- bold label

### Ghost

Use for secondary actions near a stronger primary.

Visual traits:

- white or transparent background
- pale border
- dark blue label

### Soft

Use for low-risk utility actions on public surfaces.

Visual traits:

- pale blue background
- medium blue text
- border close to surrounding stroke colors

## Form Controls

Shared control treatment:

- field height should feel comfortable, not cramped
- labels remain compact and clear
- borders stay neutral until focus
- focus ring uses the existing blue ring

Prefer:

- `10px` radius on fields
- internal padding around `12px 14px`
- stacked labels on smaller screens

## Cards And Blocks

### Hero Card

Use for the page entry point or top summary.

Traits:

- `20px` to `24px` radius
- layered light gradient
- medium shadow
- enough spacing for headline plus one supporting area

### Standard Card

Use for metrics, sections, or grouped content.

Traits:

- `12px` to `18px` radius
- white background
- thin pale border
- shadow lighter than hero card

### Sticky Utility Card

Use only when long pages need navigation help or quick actions.

Traits:

- blur or nearly white background
- compact layout
- medium shadow
- should feel helpful, not intrusive

## Copy And Tone

UI copy should match the product tone:

- direct
- calm
- respectful
- practical

Avoid:

- playful slang
- aggressive sales language
- overly dramatic alerts for normal states

## Responsive Rules

Before shipping UI:

1. collapse multi-column grids cleanly
2. keep tap targets comfortable
3. ensure sticky elements do not block the page
4. make long labels wrap gracefully
5. avoid horizontal scroll

## Reuse Checklist

Before creating a new pattern, check whether one of these files already has it:

- `assets/css/index.css`
- `assets/css/auth.css`
- `assets/css/profile.css`
- `assets/css/cemetery-detail.css`
- `assets/css/grave.css`
- `assets/css/menu.css`

If a similar pattern exists, extend it or echo its visual behavior.
