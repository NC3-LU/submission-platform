# Design Consistency Revamp

**Date:** 2026-03-04
**Scope:** All user-facing pages except `forms/{id}/edit` (already revamped)
**Approach:** Bottom-up component fixes, then page-specific changes

## Reference Design Language

Established by the revamped `forms/edit.blade.php`, homepage, and navigation:

- **Primary accent:** `sky-600` (hover: `sky-700`)
- **Neutrals:** `gray-*` for content, `slate-*` for layout chrome
- **Cards:** `bg-white dark:bg-gray-800 shadow rounded-xl p-6`
- **Section headers:** Icon in colored rounded-lg box + title + description
- **Buttons:** `bg-sky-600 hover:bg-sky-700 text-white font-medium rounded-lg transition-colors`
- **Focus rings:** `focus:ring-2 focus:ring-sky-500`
- **Inputs:** `rounded-lg border-gray-300 dark:border-gray-600 focus:ring-sky-500`
- **Status badges:** Semantic colors with `dark:*-900/30` opacity pattern
- **Transitions:** `transition-colors` on all interactive elements
- **Tables:** Keep tabular layout, modernize container (rounded-xl, better headers)

## Commit Plan

### Commit 1: Shared Blade Components
- `x-button`: Ensure sky focus ring
- `x-input`: Standardize sky focus
- `x-checkbox`: Verify sky colors
- `x-action-section`, `x-form-section`: Consistent card styling
- `x-authentication-card`: Match layout
- `x-statistics-card`: Add sky accent, modernize
- `x-dashboard.admin-statistics`: Modernize grid
- `x-dashboard.user-forms`: Modernize cards

### Commit 2: Dashboard
- Modernize stat cards with section header pattern
- Consistent spacing and card styles

### Commit 3: Forms Pages
- `forms/create.blade.php`: blue→sky, rounded-xl card, section header
- `forms/user-index.blade.php`: Modernize table, sky actions
- `forms/preview.blade.php`: blue→sky buttons
- `forms/public-index.blade.php`: Minor tweaks only

### Commit 4: Submissions Pages
- `submissions/create.blade.php`: Fix dark mode wrapper
- `submissions/show.blade.php`: Modernize layout, sky links, card structure
- `submissions/edit.blade.php`: yellow→amber warning
- `submissions/user-index.blade.php`: Modernize table, standardize actions
- `submissions/thankyou.blade.php`: blue→sky, modernize card

### Commit 5: Livewire Components
- `SubmissionForm`: Progress bar blue→sky, step indicators
- `SubmissionIndex`: Table header modernization

### Commit 6: Workflows
- Fix timeline colors, reduce shadow-xl→shadow

### Commit 7: Auth, Policy, Terms, Profile
- Auth: sky focus rings, consistent text colors
- Policy/Terms: background consistency
- Profile: Handled via component cascade
