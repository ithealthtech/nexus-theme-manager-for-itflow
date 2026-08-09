# Nexus Theme Manager for IT Flow Design Specification

- Date: 2026-08-08
- Implementation baseline: ITFlow 26.08, commit `89b080b430aaafba5d520c4e52c57b28a9559085`

## Design intent

Nexus provides a dark, direct, technically capable, and reassuring support experience without hard-coding an operator's company identity. Customer surfaces are dark-first and spacious. Technician surfaces retain a light, high-density work area with a cohesive dark shell; ITFlow's existing user-selectable dark mode remains supported.

No company-specific copy, fictional marketing metrics, remote fonts, trackers, or hotlinked website assets are included. Company name, logo, favicon, user, client, module, permission, and white-label values remain dynamic.

## Central tokens

| Role | Token/value | Usage |
|---|---|---|
| Ink | `#0B0A17` | Dark page background and dark text on cyan |
| Dark surface | `#121124` | Authentication cards, portal cards, navigation |
| Elevated dark | `#25284A` | Secondary controls and selected utility surfaces |
| Dark border | `#343858` | Dividers and component outlines |
| Cyan | `#69BFF5` | Links, focus, informational states, standard actions |
| Violet | `#7888FF` | Hover and limited interface accent |
| Gradient | `linear-gradient(110deg, #7285F4, #69C9EE)` | Major calls to action and active navigation only |
| White | `#FFFFFF` | Dark-surface headings |
| Soft copy | `#D9D9E5` | Dark-surface body copy |
| Muted dark | `#AEB3CA` | Accessible secondary copy on dark surfaces |
| Light page | `#F3F4FA` | Technician work area |
| Light surface | `#FFFFFF` | Technician cards, tables, modals |
| Light border | `#D7D9E7` | Technician dividers |
| Light text | `#121124` | Technician headings/body |
| Light muted | `#6F7288` | Secondary technician copy |

Typography uses local/system fallbacks only:

- Interface: Inter, system UI, Segoe UI, sans-serif
- Display: Aptos Display, Space Grotesk, Inter, system UI, sans-serif
- Data: IBM Plex Mono, SFMono-Regular, Consolas, monospace

## Information architecture

### Unified authentication

1. Dynamic company identity/fallback
2. Context eyebrow and state-specific heading
3. Persistent labels and existing controls
4. Existing feedback region
5. Existing password reset, role choice, MFA, remember-me, and Entra paths
6. Security reassurance
7. Optional ITFlow attribution when white-label mode is disabled

### Customer portal

1. Dynamic company identity
2. Home and tickets
3. Permission-controlled finance and technical sections
4. Unmistakable create-request action
5. Custom links
6. Account/profile/sign-out menu
7. Welcome and request context
8. Page-specific dashboard, list, conversation, form, or profile content
9. Company footer without a hard-coded external destination

### Technician and administrator UI

1. Existing fixed top navigation and search
2. Existing global, client-context, or administrative sidebar
3. Existing page title/action row
4. Dense operational cards, filters, forms, tables, tabs, queues, and modals
5. Existing footer and all JavaScript interactions

## Component rules

| Component | Rule |
|---|---|
| Primary action | Cyan for normal actions; gradient for authentication and the customer create-request CTA |
| Secondary action | Elevated neutral surface with visible border |
| Destructive action | Conventional red; never replaced by theme accent colors |
| Focus | Four-pixel translucent cyan ring plus transparent outline fallback |
| Form controls | Persistent labels, 44px height, clear focus border/ring, system autofill-compatible |
| Cards | Thin border, 4–8px radius, restrained shadow, no decorative glass effects in technician work areas |
| Tables | Uppercase compact headers, generous row separators, horizontal scrolling contained at narrow widths |
| Status/priority | Badge text always names the state; icon/text supplements color where practical |
| Alerts | Semantic color plus text/icon; polite live regions added to changed auth/customer feedback |
| Navigation | Cyan/gradient active indicator; no reproduction of the marketing site's full navigation |
| Motion | 150ms interaction transitions; effectively disabled by `prefers-reduced-motion` |
| Print | Shadows, fixed shell navigation, and dark backgrounds removed or normalized |

## Responsive behavior

- Maximum customer content width: approximately 76rem.
- Authentication cards remain within 27rem and become edge-safe at 320px.
- Customer navigation collapses at Bootstrap's `lg` breakpoint and keeps conditional menus intact.
- Tables become intentional horizontal scroll regions rather than forcing page overflow.
- Agent/AdminLTE mobile sidebar behavior is unchanged; shared theme rules follow the existing collapsed/off-canvas state.
- Non-small buttons are at least 44px high on narrow screens.
- At 200% zoom, normal content may reflow; only data tables are intended to scroll horizontally.

## Annotated preview notes

### Visual review checklist

- Authentication labels remain visible independently of placeholders.
- Security reassurance is present without organization-specific marketing copy or links.
- The customer portal keeps the create-request action prominent at desktop and mobile widths.
- Ticket number, subject, status text, and update time form the primary scan path.
- Technician operational content remains dense and light by default while the shell uses Nexus tokens.
- Priority and status badges retain conventional semantics and text labels.
