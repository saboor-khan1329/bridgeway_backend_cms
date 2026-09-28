{{--
    The Service Finder guide, shown as a read-only tab inside the configurator.

    It lives here, next to the switches it describes, because an operator who
    needs it is already on this screen — a document in a repository or a shared
    drive is one they will never find at the moment they need it.

    Written for the person running the site, not for a developer: no file paths,
    no class names, no code. The developer-facing companion is
    backend/SERVICE_FINDER.md, which covers the architecture and the endpoints.

    KEEPING THIS CURRENT: when you add a setting to ServiceFinderSettings::FIELDS,
    add a line about it in the matching section below. A field's own label and
    help text explain what it does; this page explains WHEN you would touch it
    and what it affects elsewhere.
--}}

<div class="card border-top-0 rounded-top-0">
    <div class="card-body sf-guide">

        <div class="alert alert-info d-flex gap-3 align-items-start">
            <i class="fa fa-circle-info mt-1"></i>
            <div>
                <strong>This tab holds no settings.</strong>
                It explains what the Service Finder is, what each screen does and
                the order to do things in. Every other tab on this page changes
                the live website as soon as you save.
            </div>
        </div>

        {{-- ------------------------------------------------------------ A --}}
        <h4 class="mt-4">A. What the Service Finder is</h4>
        <p>
            A search panel on the home page. A visitor types a town or postcode,
            optionally picks a service, and gets back the services covered in
            that area plus a map of where you operate. Every result and every map
            pin can open a quote form, and those enquiries land in
            <strong>Service Finder &rarr; Leads</strong>.
        </p>
        <p class="mb-0">
            It can also be placed on service, sector and location pages — see
            <em>Where It Appears</em>.
        </p>

        {{-- ------------------------------------------------------------ B --}}
        <h4 class="mt-4">B. The three things it is built from</h4>
        <dl class="row mb-0">
            <dt class="col-sm-3">Sites</dt>
            <dd class="col-sm-9">
                Real addresses you cover, imported from a spreadsheet. These are
                what the map actually plots. Client names are never shown to the
                public — only the dots.
            </dd>

            <dt class="col-sm-3">Areas</dt>
            <dd class="col-sm-9">
                Towns and cities that group those sites, e.g. every
                <code>B</code> postcode becomes Birmingham. Areas are what a
                visitor searches for and what the results list names.
            </dd>

            <dt class="col-sm-3">Coverage</dt>
            <dd class="col-sm-9">
                Which services you advertise in each area. This is a decision you
                make, not something the import can know — see D.
            </dd>
        </dl>

        {{-- ------------------------------------------------------------ C --}}
        <h4 class="mt-4">C. First-time setup, in order</h4>
        <ol class="mb-0">
            <li><strong>Import your sites.</strong> <em>Service Finder &rarr; Import</em>. Always run a dry run first — it shows exactly what would change without changing anything.</li>
            <li><strong>Check the areas it created.</strong> <em>Service Finder &rarr; Areas</em>. Rename, merge or switch off anything that reads oddly to a customer.</li>
            <li><strong>Set the coverage.</strong> <em>Service Finder &rarr; Coverage</em>. Attach the services you want advertised in each area and pick a featured one.</li>
            <li><strong>Write the copy.</strong> The <em>Section Copy</em> and <em>Popup &amp; Modal Copy</em> tabs here.</li>
            <li><strong>Point the leads somewhere.</strong> The <em>Notifications</em> tab, so enquiries reach a real inbox.</li>
            <li><strong>Turn it on.</strong> The <em>Where It Appears</em> tab.</li>
        </ol>

        {{-- ------------------------------------------------------------ D --}}
        <h4 class="mt-4">D. Why the import does not decide what you advertise</h4>
        <p class="mb-0">
            The spreadsheet says where you already have staff. It does not say
            where you are willing to take work. Those are different questions, so
            an import never removes a service you attached by hand — you can
            re-import as often as you like without losing your decisions.
        </p>

        {{-- ------------------------------------------------------------ E --}}
        <h4 class="mt-4">E. The map pins and their colours</h4>
        <p>
            Each service on the map gets its own colour, taken in order from the
            palette on <em>Map &amp; Pins</em>. The key in the corner of the map
            lists only services that actually have a pin, so it never advertises
            coverage a visitor cannot see.
        </p>
        <p class="mb-0">
            <strong>Adding a new service later?</strong> Nothing is needed here —
            it takes the next colour from the palette automatically. Only add
            colours to the palette if you have more services on the map than
            colours in the list, because after that the colours start repeating
            and two services become indistinguishable.
        </p>

        {{-- ------------------------------------------------------------ F --}}
        <h4 class="mt-4">F. The search pin (the one a visitor can drag)</h4>
        <p>
            When someone searches, a single pin drops where their search landed.
            They can drag it to correct the position, and the results update to
            the nearest area as they drop it. Dragging never costs anything — the
            match is worked out from data already on the page, not by asking
            Google.
        </p>
        <p>
            The artwork comes from <em>Map &amp; Pins &rarr; Search pin artwork</em>.
            <strong>Leave it empty and the site uses its own built-in pin</strong>,
            so the map is never without a pointer. Only paste an SVG here if you
            specifically want a different shape, and if you do:
        </p>
        <ul class="mb-0">
            <li>the artwork must point <strong>downwards</strong>, with its tip at the bottom-centre — that tip is what marks the spot;</li>
            <li>set the width and height fields to match your artwork exactly, or the pin will sit offset from the place it marks;</li>
            <li>keep it simple and give it a light outline — it is drawn small, on a dark map, and fine detail disappears;</li>
            <li>scripts and external images are stripped out automatically when you save, so only the drawing survives.</li>
        </ul>

        {{-- ------------------------------------------------------------ G --}}
        <h4 class="mt-4">G. Keeping the running cost at zero</h4>
        <p>
            Google charges for map loads and for address suggestions. Three
            settings control almost all of it, all on <em>Behaviour &amp; Cost</em>:
        </p>
        <ul>
            <li><strong>Map load trigger</strong> — "only after the visitor clicks" is the cheapest, because no map loads unless someone asks for one.</li>
            <li><strong>Enable the interactive map</strong> — off means the results list only, and stops map billing immediately.</li>
            <li><strong>Enable Google address autocomplete</strong> — off means postcode and town-name matching still work perfectly, with no suggestion billing at all.</li>
        </ul>
        <p class="mb-0">
            <em>Service Finder &rarr; API Usage</em> shows what has actually been
            used. Check there before changing anything, rather than guessing.
        </p>

        {{-- ------------------------------------------------------------ H --}}
        <h4 class="mt-4">H. Handling enquiries</h4>
        <p class="mb-0">
            <em>Service Finder &rarr; Leads</em>. Each one records where it came
            from — a map pin, a results card, or a package tier on a service page
            — so you can see which part of the site is producing work. Obvious
            spam is filed separately and never emailed; the <em>Spam &amp; Limits</em>
            tab controls how strict that is.
        </p>

        {{-- ------------------------------------------------------------ I --}}
        <h4 class="mt-4">I. If something looks wrong</h4>
        <dl class="row mb-0">
            <dt class="col-sm-4">The map says "unavailable"</dt>
            <dd class="col-sm-8">The Google key is missing or has expired. A developer needs to look at this.</dd>

            <dt class="col-sm-4">No address suggestions</dt>
            <dd class="col-sm-8">Autocomplete is switched off on <em>Behaviour &amp; Cost</em>. Searching by postcode or town name still works without it.</dd>

            <dt class="col-sm-4">An area shows no services</dt>
            <dd class="col-sm-8">Nothing has been attached to it yet — go to <em>Coverage</em>.</dd>

            <dt class="col-sm-4">A pin is in the wrong place</dt>
            <dd class="col-sm-8">Its postcode failed to resolve. Find it under <em>Sites</em> and set the position by hand.</dd>

            <dt class="col-sm-4">A change has not appeared</dt>
            <dd class="col-sm-8">The website caches this data for up to an hour. Saving any tab here clears it immediately, so save again and reload.</dd>
        </dl>

    </div>
</div>
