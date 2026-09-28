# Testimonials — how it works and how to run it

The "Trusted by Businesses Like Yours" section: a client quote (headline,
testimonial text, name, role, photo) shown in a card, or — with two or more
attached — a carousel of them with arrows and dot pagination.

- Admin: **Testimonials** in the sidebar (under Content, just below FAQs)

---

## 1. What a visitor sees

A section with a centred heading + sub-heading, and below it a panel:

- **One testimonial attached** → a plain card. No carousel, no arrows, no
  Swiper JS loaded at all.
- **Two or more attached** → the same card becomes a one-slide-at-a-time
  carousel: circular prev/next arrows either side of a dot-pagination row.
  With more than 5 testimonials the dots don't just keep growing sideways —
  they use Swiper's "dynamic bullets" window (5 full-size dots, shrinking/
  fading ones at the edges), so the row stays the same width regardless of
  how many are attached. Autoplay advances every 7 seconds; a visitor's own
  clicks still work normally on top of that.

Each card: an optional headline, the quote, the client's name and role, and
an optional photo on the right (falls back to a single, wider text column
when no photo is attached — nothing looks broken either way).

**Recommended photo size:** roughly **560×454px** (about 1.23:1, landscape) —
or 1120×908 for a sharp retina result. That's not a hard requirement: the
photo box uses `object-fit: cover`, so any reasonably landscape-ish photo
will fill it correctly, just cropped to fit if the proportions are very
different.

---

## 2. The model

Testimonials reuse an existing table that already had this exact shape built
in but unused: `reviews` (model `App\Models\Review`), scoped everywhere in
this feature to `is_testimonial = true` — the `rating` column and the
`is_testimonial` flag exist for a possible future star-rating "Reviews"
feature on the same table; nothing here touches rows where that flag is off.

A testimonial has: `title` (headline), `content` (the quote), `author_name`,
`author_role`, `company_name`, `rating` (1–5, not currently shown on the
frontend), `status` (active/inactive — a global kill-switch independent of
where it's attached), and one photo (via the existing polymorphic `images`
table, `image_type = 'photo'`).

**Where it shows up** is a many-to-many attachment (`reviewables` pivot
table) to any of: `Page` (static pages — this is how Home works), `Service`
(covers both regular service pages and sector pages — they're the same
model), `Location`, and `Category` (category listing pages). Attaching to a
Page/Service/Location/Category is what makes it show there; detaching (or
turning its `status` off) is what makes it stop. There's no separate
per-attachment on/off switch — attach/detach and the one global `status`
flag are the only two levers, matching how FAQs already work in this
codebase.

**Heading/sub-heading**: each Page/Service/Location/Category has its own
optional `testimonials_heading` / `testimonials_sub_heading` columns. Blank
on every page by default — the frontend supplies "Trusted by Businesses Like
Yours" / its default sub-copy when they're empty, so the section looks
finished everywhere without any admin action. Set them on a specific page's
own edit form to override the copy just there.

---

## 3. Admin — two ways to attach one

**From the Testimonial itself** (`/admin/testimonials` → edit) — good for
attaching one testimonial to several pages at once:
- Show on Pages (e.g. Home)
- Show on Services / Sectors
- Show on Locations
- Show on Categories

**From the content record's own edit form** (Category/Service/Sector page/
Location/Page → edit) — good for building up a specific page and picking
which existing testimonials belong there, alongside that page's own
`Testimonials Heading` / `Testimonials Sub Heading` override fields:
- Categories: only on **service-type** categories (category pages) — not on
  location/blog category types, which don't render this section.
- Service Pages / Sector Pages: same underlying `Service` model, both forms
  carry the fields.
- Locations: on every location's edit form.
- Pages: on every static page's edit form (this is where Home's fields
  live — Page id with `page_type = 'home'`).

Both write to the exact same pivot row, so it doesn't matter which side you
use — attach from whichever screen you happen to be on.

---

## 4. Where it's wired in on the frontend

All five templates render the section (opt-in — nothing shows until a
testimonial is attached, per template, as above):

| Template | File |
|---|---|
| Home | `frontend/src/app/page.js` — between Solutions and the CTA |
| Category pages | `frontend/src/app/[categorySlug]/page.js` — between the services grid and the CTA |
| Service / Sector pages | `frontend/src/components/ContentPage.jsx` — right before FAQs |
| Locations index (`/locations`) | `frontend/src/app/locations/page.js` — after the location cards |
| Location detail pages | `frontend/src/app/locations/[locationSlug]/page.js` — right before FAQs |

**To show it on a page it doesn't currently render on:** add
`<ClientTestimonials data={toTestimonials(data.testimonials)} />` to that
page's JSX, and add `testimonials: raw.testimonials || null` to whichever
mapper builds that page's `data` (see `frontend/src/lib/mappers/`) — the
backend controller for that page type also needs
`'testimonials' => $this->formatTestimonialSection($model, $model->testimonials)`
added to its response (see `BaseFrontendController::formatTestimonialSection()`
and any of the five controllers above for the exact pattern to copy). Every
piece needed to do this (the model relation, the admin fields, the frontend
component) already exists — this is only ever a few lines in a fifth or
sixth place, not new plumbing.

---

## 5. Frontend architecture

- `frontend/src/lib/mappers/testimonials.mapper.js` — `toTestimonials()`
  normalises the API's `{heading, sub_heading, items[]}` shape into the
  component's props, and returns `null` when there are no active items (the
  reason every template safely renders nothing until something's attached).
- `frontend/src/components/ClientTestimonials.jsx` — server component. Picks
  the plain-card path vs. the carousel path based on item count.
- `frontend/src/components/TestimonialCard.jsx` — the actual card markup
  (headline/quote/author + photo). Shared, byte-identical, between the plain
  path and every Swiper slide — the same reasoning as `ServiceCard.jsx`.
- `frontend/src/components/ClientTestimonialsCarousel.jsx` /
  `ClientTestimonialsSwiper.jsx` — the two-stage dynamic-import pair (same
  pattern as `Hero`/`HeroCarousel`/`HeroSlider`), so Swiper's JS is never
  loaded for a page with only one testimonial.
- `frontend/src/components/ClientTestimonials.scss` — typography/spacing
  matches the site's existing scale (heading `clamp(26px,3vw,44px)` as used
  by SerTwoCol/SectFaq/Faqs; the panel uses the same elevated-shadow
  treatment as Hero/LocCards/ServiceFinder rather than a flat border).

---

## 6. Notes for future maintainers

- The pagination container (`.ct-pagination`) needs `position: static
  !important; transform: none !important` in the SCSS — Swiper's bundled
  pagination CSS assumes it floats absolutely over a full-bleed slider
  (`position:absolute; left:50%` + a JS-computed centring `translateX`);
  without cancelling both of those, the dots render on top of the prev
  arrow instead of between the two arrows. If a future Swiper upgrade
  changes this internal behaviour, re-check the dot row's position after
  upgrading.
- `autoHeight` is enabled on the Swiper instance — testimonials vary a lot
  in quote length and not all of them have a photo, so each slide keeps its
  own height instead of every slide inheriting the tallest one in the set.
- Only one `ClientTestimonials` section exists per page (unlike
  `ServicesCards`, which repeats several times on Home), so the carousel
  uses plain CSS-selector navigation/pagination (`.ct-prev`/`.ct-next`/
  `.ct-pagination`) rather than the `onSwiper`-ref pattern `ServicesCards`
  needs to avoid binding every section's arrows to the first instance.
