# Specification: Public homepage banner carousel

## 1. Objective

Render the administrator-managed banners as a homepage hero carousel matching the supplied reference: full-width image, readable text overlay, CTA, previous/next arrows, and slide indicators.

## 2. Stack

- Server-rendered PHP HTML in the existing storefront module.
- Small progressive-enhancement JavaScript and CSS; no client framework or server process.
- Respect browser reduced-motion preferences.

## 3. Commands

Use documented project commands from `README.md`: `docker compose exec -T app php -l <changed-php-file>` for syntax and `docker compose --profile build run --rm admin-build` only if admin assets changed. Public behavior can be reviewed in the running local site.

## 4. Project structure

Fetch active banners through the storefront module's repository/controller and render them from the existing homepage view/layout. Keep carousel markup in the public storefront templates, styling in the storefront stylesheet, and behavior in its existing public JavaScript assets.

## 5. Behavior

Render only active banners, ordered by sort order and then stable ID. Each slide contains its image, title, description, and CTA link. Provide previous/next controls and one indicator per slide. Automatically advance every six seconds. Pause automatic advancement while the carousel is hovered or keyboard-focused; resume when interaction ends. Disable autoplay when `prefers-reduced-motion: reduce` is active while keeping manual controls usable. With no active banners, show the existing static hero as a fallback. With one banner, omit nonfunctional slide controls and autoplay.

## 6. Style and accessibility

Match the supplied reference while following existing storefront tokens: image-led wide hero, text aligned to the left with sufficient contrast, prominent CTA, circular side controls, and clear active indicator. Use descriptive alternative text configured in administration; mark purely decorative images accordingly. Use semantic buttons with accessible labels, expose the active slide state, and retain visible keyboard focus. Keep controls usable on narrow viewports without obscuring the message.

## 7. Verification strategy

- PHP syntax check for changed PHP files.
- Manual browser review at desktop and mobile widths, including controls, six-second rotation, focus/hover pause, reduced-motion setting, empty state, and single-banner state.
- Do not add or run automated tests unless requested.

## 8. Boundaries and success criteria

The homepage displays the current active banner set in deterministic order, with working CTA and accessible manual controls; autoplay advances at the approved interval; the existing hero remains available as a fallback. Public HTML remains server-rendered and production needs no Node service.

## 9. Open questions

None. Automatic rotation every six seconds was confirmed by the user.
