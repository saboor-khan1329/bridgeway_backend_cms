# Page Sections — how they work and how to run them

Four admin-authored sections that a **service**, **sector** or **location**
page can carry, on top of the fixed sections those templates already have:

| Component | What it looks like |
|---|---|
| **Feature Cards** | A row of image cards (image, title, paragraph) that scrolls sideways when there are more than fit |
| **Packages / Tiers** | Side-by-side package cards with a tick list and a quote button |
| **Cost Factors Table** | An intro column with a call-to-action, beside a two-column table |
| **Numbered Process Steps** | A numbered grid explaining how the work is carried out |

**Where to find them in admin:** sidebar → **Page Sections** (under CONTENT).
That screen lists every service, sector and location page with a column per
component showing how many rows each already has, so you can see at a glance
what is filled in and what is not. Each record's own edit form also carries a
**Page Sections** button that jumps straight to its sections.

---

## 1. The rule that matters

**Nothing renders until a content editor fills it in.** A page with no rows
looks exactly as it did before this feature existed. That is enforced in the
API, not the frontend: a block with no usable rows is dropped from the payload
entirely, so there is no empty heading, no stray spacing, and nothing for a
crawler to index.

"Usable" means every field marked required on that component has a value. Blank
rows are normal — the editor always offers spare ones so adding the next item
is one click, and empty ones are simply ignored on save.

---

## 2. The model

One table, `content_blocks` (model `App\Models\ContentBlock`), attached
polymorphically to the page it belongs to:

| Column | Purpose |
|---|---|
| `blockable_type` / `blockable_id` | The Service or Location it belongs to |
| `type` | Which component renders it — see `ContentBlock::TYPES` |
| `heading`, `intro` | Section heading and supporting paragraph |
| `items` (JSON) | The repeatable rows; shape differs per type |
| `options` (JSON) | Non-repeatable extras (button label, table headings) |
| `sort_order` | Order the sections appear on the page |
| `is_active` | Hide a finished section without deleting the content |

**Sector pages are Services** — the same model with a different page type — so
both templates read the same relation and need no separate handling.

There is a unique index on `(blockable_type, blockable_id, type)`: one block of
each component per page. Each of the four is a distinct named section that
appears at most once, which is what lets the admin screen edit them as four
fixed panels rather than an unbounded list. If a second instance of a type is
ever wanted, dropping that index is the only schema change required.

### `ContentBlock::TYPES` is the single source of truth

The registry in the model describes each component's fields — key, label, type
(`text` / `textarea` / `image` / `list`), whether it is required, and length
limits. **The admin form, the validation rules and the API formatter are all
generated from it.** Adding a fifth component means adding an entry there plus
a frontend component; the admin screen picks it up with no template change.

---

## 3. Admin

### Finding them

Two ways in, because there are two different questions an editor asks.

**Sidebar → Page Sections** answers "which pages have sections, and which still
need them". One tab per page type — Service Pages, Sector Pages, Locations —
each showing how many pages of that type already carry content, and a table
with a column per component:

| Cell | Meaning |
|---|---|
| `—` | no rows written |
| green `3` | three rows, section switched on |
| amber `3 hidden` | three rows written but the **Visible** switch is off |

Search filters by page title or slug. **Edit sections** opens the editor.

**The Page Sections button on a record's edit form** answers "I am already
editing this page, take me to its sections".

> Service and sector pages are the same records underneath — a sector is a
> Service whose category is of type `sector`. They are separate tabs because
> they are separate screens everywhere else in the admin, and the editor sends
> you back to whichever listing you came from. Opening a plain service under
> the `sector` URL (or the reverse) returns a 404 rather than the wrong page.

### The editor

Everything for one page is on a single screen, because "what does this page
show" is the question an editor actually has.

Per component: a **Visible** switch, an **Order** number, the heading and
intro, any options, and the rows. Images are chosen with the same File Manager
picker used elsewhere in admin, and each carries its own alt text.

Saving is deliberately non-destructive: a component whose rows are all blank is
kept with empty items rather than deleted, so nothing an editor typed is thrown
away by a stray save. Clearing a row empties its inputs rather than removing
the row, which keeps every later row's index stable.

---

## 4. Frontend

| File | Role |
|---|---|
| `frontend/src/components/content-blocks/ContentBlocks.jsx` | Server component. Renders whichever blocks the page has, in the operator's order |
| `.../BlockFeatureCards.jsx` + `CardScroller.jsx` | Cards; the scroller is the only client-side part |
| `.../BlockPackages.jsx` | Package cards |
| `.../BlockCostFactors.jsx` | Intro column + table |
| `.../BlockProcessSteps.jsx` | Numbered grid — fully static, no client JS |
| `.../QuoteCtaButton.jsx` | The quote call-to-action (see below) |
| `.../ContentBlocks.scss` | All four components' styles |
| `frontend/src/lib/mappers/content-blocks.mapper.js` | Shape guard; returns `[]` for anything unexpected |

Dropped into a template once:

```jsx
<ContentBlocks
  data={data.contentBlocks}
  service={data?.id ? { id: data.id, title: data.title } : null}
/>
```

Currently wired into `ContentPage.jsx` (service + sector pages) and
`app/locations/[locationSlug]/page.js`, in both cases immediately before the
testimonials section.

### Rendering decisions worth knowing

- **Server-rendered.** Every heading, package name, feature, table row and step
  is in the initial HTML. Verified against the raw response, not just the
  hydrated page.
- **The quote buttons are real links.** `QuoteCtaButton` always renders an
  `<a href="/contact-us">`, and JavaScript upgrades the click into the quote
  dialog. With scripting unavailable the enquiry route still works, and a
  crawler follows a genuine link rather than meeting an inert `<button>`.
- **No layout shift.** Card images have a fixed aspect ratio (410:430) and
  explicit dimensions, so the row occupies its final height from first paint.
- **The cost table is a real `<table>`** with proper column and row headers. On
  narrow screens the rows restack into label-above-value pairs and the headers
  are visually hidden, so it never forces a horizontal scroll.
- **Headings nest correctly** — section headings are `<h2>`, card/package/step
  titles are `<h3>`. Verified: one `<h1>` per page, no level skips.
- **No browser storage** is used anywhere in these components.

### Type scale

Sizes come from the site's existing sections rather than the Figma frame's raw
pixels — the frame is a fixed 1300px artboard, and lifting 36/44px headings
verbatim would make these louder than every other heading on the same page:

| | |
|---|---|
| Split-header heading | `clamp(26px, 2.1vw, 34px)` — as ServicesCards |
| Centred heading | `clamp(26px, 3vw, 44px)` — as SerTwoCol / Faqs |
| Intro and body | `clamp(16px, 1.4vw, 18px)` |
| Card radius | 24px — the site's panel radius |

---

## 5. The quote dialog

The **Packages** and **Cost Factors** buttons open the Service Finder's
existing quote dialog, so those leads land in the one **Leads** screen an
operator already works from. They are told apart by `origin`:

- `package_card` — a package card's button
- `content_block` — the cost-factor section's button

Both were added to `FinderLead::ORIGINS`. The dialog needs the finder's
settings, which `ContentBlocks` fetches from the same cached coverage payload
the finder itself uses — so a page carrying both makes one request, not two.
If the finder is switched off or its API is unreachable, the buttons stay as
plain links to the contact page rather than opening a dialog that cannot
submit.

---

## 6. Adding a fifth component

1. Add an entry to `ContentBlock::TYPES` with its fields and options.
2. Build the frontend component and add a `case` to `ContentBlocks.jsx`.
3. Style it in `ContentBlocks.scss`.

No migration, no admin template change, no validation change — those all read
the registry.

---

## 7. Notes for future maintainers

- Images in `items` store a **File Manager path**, not an `images` table row.
  That keeps rows reorderable without image records drifting out of sync with
  their position. `BaseFrontendController::formatContentBlockImage()` turns the
  path into a `{path, url, alt}` triple using the same `ImageFormatter::url()`
  every other image goes through.
- `list` fields (a package's feature list) are stored as a JSON array but the
  API formatter also accepts a newline string, so a row written by hand or by
  an older import still renders.
- `options` are merged over the type's declared defaults on read, so a block
  saved before an option existed still returns a usable button label.
- Saving bumps `FrontendCache`, so an edit is visible on the site immediately
  rather than after the cache TTL.
