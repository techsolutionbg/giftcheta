<?php
$pageHeading = (string) ($pageHeading ?? '');
$pageBreadcrumbs = is_array($pageBreadcrumbs ?? null) ? $pageBreadcrumbs : [];
$pageMeta = (string) ($pageMeta ?? '');
?>
<header class="store-page-heading">
  <h1 id="store-page-title"><?= $view->e($pageHeading) ?></h1>
  <?php if ($pageBreadcrumbs): ?>
    <nav class="store-page-breadcrumbs" aria-label="Навигация">
      <?php foreach ($pageBreadcrumbs as $index => $crumb): ?>
        <?php if ($index > 0): ?><span aria-hidden="true">/</span><?php endif; ?>
        <?php if (!empty($crumb['url'])): ?><a href="<?= $view->e($crumb['url']) ?>"><?= $view->e($crumb['label']) ?></a><?php else: ?><span aria-current="page"><?= $view->e($crumb['label']) ?></span><?php endif; ?>
      <?php endforeach; ?>
    </nav>
  <?php endif; ?>
  <?php if ($pageMeta !== ''): ?><p class="store-page-heading-meta"><?= $view->e($pageMeta) ?></p><?php endif; ?>
</header>
