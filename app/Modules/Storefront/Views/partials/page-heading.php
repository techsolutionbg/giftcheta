<?php
$pageHeading = (string) ($pageHeading ?? '');
$pageBreadcrumbs = is_array($pageBreadcrumbs ?? null) ? $pageBreadcrumbs : [];
$pageMeta = (string) ($pageMeta ?? '');
$pageShowFilter = (bool) ($pageShowFilter ?? false);
$pageShowFilterButton = (bool) ($pageShowFilterButton ?? $pageShowFilter);
?>
<header class="store-page-heading<?= $pageShowFilter ? ' store-page-heading-catalog' : '' ?>">
  <div class="store-page-heading-main">
    <h1 id="store-page-title"><?= $view->e($pageHeading) ?><?php if ($pageShowFilter): ?><span class="catalog-heading-count" data-catalog-heading-count> (<?= (int)($total ?? 0) ?>)</span><?php endif; ?></h1>
    <?php if ($pageBreadcrumbs): ?>
      <nav class="store-page-breadcrumbs" aria-label="Навигация">
        <?php foreach ($pageBreadcrumbs as $index => $crumb): ?>
          <?php if ($index > 0): ?><span aria-hidden="true">/</span><?php endif; ?>
          <?php if (!empty($crumb['url'])): ?><a href="<?= $view->e($crumb['url']) ?>"><?= $view->e($crumb['label']) ?></a><?php else: ?><span aria-current="page"><?= $view->e($crumb['label']) ?></span><?php endif; ?>
        <?php endforeach; ?>
      </nav>
    <?php endif; ?>
  </div>
  <?php if ($pageShowFilterButton || $pageMeta !== ''): ?><div class="store-page-heading-side">
    <?php if ($pageShowFilterButton): ?><button class="catalog-filter-button catalog-filter-toggle" type="button" data-catalog-filter-toggle aria-label="Покажи или скрий филтрите" aria-expanded="true"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h10M18 7h2M4 17h2m4 0h10"/><circle cx="16" cy="7" r="2"/><circle cx="8" cy="17" r="2"/></svg><span>Филтър</span></button><?php endif; ?>
    <?php if ($pageMeta !== ''): ?><p class="store-page-heading-meta"><?= $view->e($pageMeta) ?></p><?php endif; ?>
  </div><?php endif; ?>
</header>
<style>.store-page-heading:not(.store-page-heading-catalog) .store-page-heading-main,.store-page-heading:not(.store-page-heading-catalog) .store-page-heading-side{display:contents}</style>
<?php if ($pageShowFilter): ?>
<style>
.store-page-heading-catalog{display:flex;align-items:center;justify-content:center;gap:28px;margin:0 auto 20px;text-align:center}
.store-page-heading-catalog .store-page-heading-main{display:grid;justify-items:center;gap:9px;min-width:0}
.catalog-heading-count{display:inline}
.store-page-heading-catalog .store-page-breadcrumbs{justify-content:center}
.store-page-heading-catalog .store-page-heading-side{display:flex;align-items:center;justify-content:flex-end;gap:22px;flex:none}
.store-page-heading-catalog .store-page-heading-meta{margin:0;white-space:nowrap}
.catalog-filter-button{min-height:42px;display:inline-flex;align-items:center;justify-content:center;gap:9px;padding:0 15px;border:1px solid #eadfdd;border-radius:9px;background:#fff;color:#4d4242;font:inherit;font-size:.92rem;cursor:pointer;transition:border-color .16s ease,background .16s ease,transform .16s ease}
.catalog-filter-button:hover{border-color:#dcaab5;background:#fff8f9;transform:translateY(-1px)}
.catalog-filter-button:focus-visible{outline:3px solid #e94a7040;outline-offset:2px}
.catalog-filter-button svg{width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}

@media(max-width:600px){.store-page-heading-catalog{align-items:flex-start;gap:14px}.store-page-heading-catalog .store-page-heading-side{gap:12px}.catalog-filter-button{min-height:38px;padding:0 11px;font-size:.86rem}}
@media(max-width:430px){.store-page-heading-catalog{flex-wrap:wrap}.store-page-heading-catalog .store-page-heading-side{width:100%;justify-content:flex-start}.store-page-heading-catalog .store-page-heading-main{width:100%}}
</style>
<?php endif; ?>
