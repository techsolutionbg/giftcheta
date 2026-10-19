<section class="catalog-results" aria-label="<?= $view->e($catalogLabel??'Продукти') ?>" aria-live="polite">
  <?php require __DIR__.'/partials/catalog-grid-controls.php'; ?>
  <?php require __DIR__.'/partials/product-grid.php'; ?>
  <?php require __DIR__.'/partials/pagination.php'; ?>
</section>
