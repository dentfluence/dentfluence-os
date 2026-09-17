# client-sites — client website output templates

These Blade templates are rendered into **static HTML files** that get deployed to a client's own Hostinger account over SFTP. They are the product we sell.

**They are not application UI.** Nothing here is ever served by a route.

## Hard separation

Never share a layout, component, partial or CSS file with `resources/views/web/` (Command Centre) or `resources/views/portal/` (Client Portal). Those two are the Dentfluence app, on Dentfluence brand, behind a login. These are the dentist's public website, on the dentist's brand, indexed by Google.

A change to the Dentfluence app's look must never be able to alter a live client site. Keeping the trees disjoint is what guarantees that.

## Layout

| Folder | Holds |
|---|---|
| `layouts/` | full page shells — head, header, footer, schema block |
| `blocks/` | the content blocks that `web_pages.content` json maps onto (hero, treatment list, testimonial, FAQ, CTA, map, gallery) |
| `pages/` | one template per `web_pages.page_type` |

`web_pages.template` names which template in `pages/` renders that row.

## Rules

- Every block reads only from the `content` json it is handed. No queries inside a template.
- No Dentfluence branding in the output. The client's logo, the client's colours.
- Output must be self-contained static HTML — no Laravel helpers that resolve at request time, no `route()`, no `auth()`, no session.
- GA4 measurement id comes from `web_sites.ga4_measurement_id`. Never hardcode one, and never ship `G-XXXXXXXXXX`.
