# Homepage Redesign Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Revamp the homepage, navigation, footer, and public forms page to match NC3 institutional branding with a clean, professional design supporting light and dark modes.

**Architecture:** Replace the current dark gradient-heavy UI with a clean government portal style. Solid nav backgrounds with underline active states, compact navy hero, card-based forms grid with teal accents, streamlined footer. The homepage controller will be updated to limit forms to 6 while `/forms` keeps full pagination.

**Tech Stack:** Laravel Blade templates, Tailwind CSS 3.4, Alpine.js (existing), Figtree font (existing)

---

### Task 1: Update Navigation

**Files:**
- Modify: `resources/views/layouts/navigation.blade.php` (full rewrite)
- Modify: `resources/views/components/application-logo.blade.php` (dark/light logo switching)

**Step 1: Update the application logo component for dark/light mode**

The logo component currently always renders the white logo. Update it to accept a `variant` prop and default to showing appropriate logos for light/dark mode.

Edit `resources/views/components/application-logo.blade.php` to:

```blade
@props(['variant' => 'auto'])

@if($variant === 'white')
    <img {{ $attributes->merge(['class' => '']) }} src="{{ asset('img/logo_nc3_white.png') }}" alt="NC3 Luxembourg">
@elseif($variant === 'color')
    <img {{ $attributes->merge(['class' => '']) }} src="{{ asset('img/Logo_NC3_horizontal_coul_versB.png') }}" alt="NC3 Luxembourg">
@else
    {{-- Auto: color in light mode, white in dark mode --}}
    <img {{ $attributes->merge(['class' => 'dark:hidden']) }} src="{{ asset('img/Logo_NC3_horizontal_coul_versB.png') }}" alt="NC3 Luxembourg">
    <img {{ $attributes->merge(['class' => 'hidden dark:block']) }} src="{{ asset('img/logo_nc3_white.png') }}" alt="NC3 Luxembourg">
@endif
```

**Step 2: Rewrite the navigation**

Replace the entire `resources/views/layouts/navigation.blade.php` with the new clean design:

- White background (light mode), `slate-900` (dark mode)
- No gradients, no backdrop-blur
- Solid `border-b border-slate-200 dark:border-slate-700`
- Sticky with `z-50`
- Nav links: plain text, active state uses `border-b-2 border-teal-500 text-teal-600 dark:text-teal-400` (bottom underline)
- Inactive links: `text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white`
- Remove all SVG icons from nav links (text-only links for cleaner look)
- Logo: use `<x-application-logo class="h-8 w-auto" />` (auto variant, no ring/bg wrapper)
- Auth dropdown: keep `<x-dropdown>` component, update trigger button to match new style — white bg with slate text, no gradient avatar (just initials in teal circle)
- Login/Register: Login as plain text link, Register as teal button (`bg-teal-600 hover:bg-teal-700 text-white rounded-lg px-4 py-2`)
- Mobile menu: white/dark bg (not glassmorphism), same underline active states, keep hamburger icon

**Step 3: Verify navigation renders**

Run: `php artisan serve` and check `/` in browser. Verify:
- Nav shows white bg in light mode, dark in dark mode
- Logo switches between color/white variants
- Active link has teal bottom border
- Mobile hamburger works
- Auth dropdown works (if logged in)

---

### Task 2: Redesign Hero + Homepage Content

**Files:**
- Modify: `resources/views/components/welcome.blade.php` (full rewrite)
- Modify: `resources/views/index.blade.php` (update wrapper)
- Modify: `app/Http/Controllers/FormController.php:20-28` (limit forms to 6, no pagination)

**Step 1: Update the controller to limit homepage forms**

In `app/Http/Controllers/FormController.php`, change the `index` method from `paginate(10)` to `take(6)->get()`:

```php
public function index(Request $request): View|Factory|Application
{
    $forms = Form::where('status', 'published')
        ->whereIn('visibility', ['public', 'authenticated'])
        ->latest()
        ->take(6)
        ->get();

    return view('index', compact('forms'));
}
```

**Step 2: Update the homepage wrapper**

Replace `resources/views/index.blade.php` — remove the `<x-slot name="header">` (no page header for the homepage, the hero replaces it) and the wrapping card:

```blade
<x-app-layout>
    <x-welcome :forms="$forms ?? collect()" />
</x-app-layout>
```

**Step 3: Rewrite the welcome component with compact hero**

Replace `resources/views/components/welcome.blade.php` with:

**Hero section:**
- Background: `bg-gradient-to-br from-[#0A1745] to-[#113885]` (NC3 navy). Dark mode variant: `dark:from-[#060E2B] dark:to-[#0A1745]`
- Right side: `nc3-logo-no-text-no-bg.png` as watermark at `opacity-10`, absolutely positioned
- Left-aligned content in `max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 lg:py-24`
- Small label: `<span class="text-teal-400 text-sm font-semibold tracking-wider uppercase">NC3 Submission Platform</span>`
- Headline: `<h1 class="mt-4 text-3xl lg:text-4xl xl:text-5xl font-bold text-white leading-tight">Secure Submissions for<br>Luxembourg's Cybersecurity Ecosystem</h1>`
- Subtext: `<p class="mt-6 text-lg text-slate-300 max-w-2xl">Submit applications, track progress, and collaborate with NC3 through our streamlined and secure platform.</p>`
- CTA: `<a href="#forms" class="mt-8 inline-flex items-center px-6 py-3 text-base font-semibold text-white bg-teal-600 hover:bg-teal-500 rounded-lg transition-colors shadow-lg shadow-teal-600/20">Browse Available Forms <svg arrow icon></svg></a>`

**Forms section:**
- `id="forms"` anchor
- `bg-white dark:bg-slate-900` background
- `max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16`
- Section header: "Available Forms" `text-2xl font-bold text-slate-900 dark:text-white`
- Subtitle: "Select an application to get started"
- Grid: `grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mt-8`
- Form cards:
  - `bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 border-l-4 border-l-teal-500`
  - `p-6` padding
  - Title: `text-lg font-semibold text-slate-900 dark:text-white`
  - Description: `text-sm text-slate-600 dark:text-slate-400 line-clamp-3 mt-2`
  - Bottom row: visibility badge + created date + "Start Application" link
  - Visibility badge: Public = `bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400`, Login Required = `bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400`
  - CTA link: `text-teal-600 dark:text-teal-400 hover:text-teal-700 dark:hover:text-teal-300 font-medium text-sm` with arrow
- "View All Forms" link below grid: `<a href="/forms" class="inline-flex items-center text-teal-600 dark:text-teal-400 hover:underline font-medium">View all available forms <arrow svg></a>`
- Empty state: centered icon + "No forms available" message

**Step 4: Verify homepage renders**

Run: `php artisan serve` and check `/`. Verify:
- Compact navy hero with left-aligned text
- NC3 watermark visible on right
- Forms grid shows up to 6 cards with left teal border
- "View All Forms" link present
- Dark mode toggle works

---

### Task 3: Redesign Public Forms Page

**Files:**
- Modify: `resources/views/forms/public-index.blade.php` (full rewrite)

**Step 1: Rewrite the public forms page**

Replace `resources/views/forms/public-index.blade.php` with styled version using the same card design as the homepage:

```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 dark:text-slate-200 leading-tight">
            {{ __('Available Forms') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            @if($forms->isEmpty())
                <!-- Empty state -->
                <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 p-12 text-center">
                    <svg class="mx-auto w-12 h-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <h3 class="mt-4 text-lg font-semibold text-slate-900 dark:text-white">No Forms Available</h3>
                    <p class="mt-2 text-slate-500 dark:text-slate-400">Check back later or contact support.</p>
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($forms as $form)
                        <!-- Same card markup as welcome.blade.php forms section -->
                        <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 border-l-4 border-l-teal-500 p-6">
                            <!-- Title -->
                            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ $form->title }}</h3>
                            <!-- Description -->
                            <p class="text-sm text-slate-600 dark:text-slate-400 line-clamp-3 mt-2">{{ $form->description }}</p>
                            <!-- Bottom row -->
                            <div class="mt-4 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    @if($form->visibility === 'public')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400">Public</span>
                                    @elseif($form->visibility === 'authenticated')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">Login Required</span>
                                    @endif
                                    <span class="text-xs text-slate-400">{{ $form->created_at->diffForHumans() }}</span>
                                </div>
                                @if($form->visibility === 'public' || auth()->check())
                                    <a href="{{ route('submissions.create', $form) }}" class="text-teal-600 dark:text-teal-400 hover:text-teal-700 dark:hover:text-teal-300 font-medium text-sm inline-flex items-center">
                                        Apply
                                        <svg class="ml-1 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                    </a>
                                @else
                                    <a href="{{ route('login') }}" class="text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 font-medium text-sm">Login to apply</a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-8">
                    {{ $forms->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
```

**Step 2: Verify forms page**

Run: `php artisan serve` and check `/forms`. Verify:
- Cards match homepage style (left teal border)
- Pagination works
- Visibility badges show correctly
- Login Required forms show "Login to apply" for guests

---

### Task 4: Redesign Footer

**Files:**
- Modify: `resources/views/layouts/app.blade.php:79-202` (footer section only)

**Step 1: Replace the footer in app.blade.php**

Replace the footer section (lines 79-202) with a streamlined 3-column version:

- Light gray bg: `bg-slate-50 dark:bg-slate-900`
- Teal top border: `border-t-2 border-teal-500` (simpler than gradient)
- `max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12`
- 3-column grid (`grid-cols-1 md:grid-cols-3 gap-8`):

**Column 1 — Brand:**
- NC3 logo (small, `h-8`)
- "National Cybersecurity Competence Center" text
- EU funding badge (keep existing `<img>` tag, smaller)

**Column 2 — Quick Links:**
- "Quick Links" heading (`text-sm font-semibold uppercase tracking-wider text-slate-900 dark:text-white`)
- Simple `<ul>` with: Home, Forms, Dashboard (@auth), NC3 Website (external)
- Links: `text-sm text-slate-600 dark:text-slate-400 hover:text-teal-600 dark:hover:text-teal-400`
- No SVG icons on links

**Column 3 — Legal:**
- "Legal" heading
- Terms of Service, Privacy Policy, Cookie Settings (button)
- Same link styles

**Bottom bar:**
- `border-t border-slate-200 dark:border-slate-800 mt-8 pt-6`
- Copyright: `text-sm text-slate-500`

**Step 2: Verify footer**

Check `/` and `/forms`. Verify:
- 3-column layout on desktop, stacks on mobile
- EU badge visible
- Links work
- Cookie settings button works
- Dark mode correct

---

### Task 5: Final Polish

**Files:**
- Modify: `resources/views/layouts/app.blade.php` (page background)
- Modify: `resources/views/components/dropdown.blade.php` (dark mode style for nav dropdown)

**Step 1: Update page background in app.blade.php**

Change the main wrapper background from `bg-gray-100 dark:bg-gray-900` to `bg-white dark:bg-slate-900` for the clean look (line 24).

**Step 2: Update dropdown content classes**

In `resources/views/components/dropdown.blade.php`, update the default `contentClasses` prop from `py-1 bg-white dark:bg-gray-700` to `py-1 bg-white dark:bg-slate-800` to match the new slate palette.

**Step 3: Visual review**

Run `php artisan serve` and review all pages:
- [ ] Homepage (light + dark): hero, forms, overall layout
- [ ] `/forms` (light + dark): cards, pagination
- [ ] Navigation: desktop + mobile, logged in + logged out
- [ ] Footer: all links, EU badge, layout
- [ ] Other pages (dashboard, profile): ensure they still look correct with new nav/footer
