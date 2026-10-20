<?php ob_start(); ?>
<?php $pageHeading='Всички продукти';$pageBreadcrumbs=[['label'=>'Начало','url'=>'/'],['label'=>'Всички продукти']];$pageMeta='';$pageShowFilter=true;$pageShowFilterButton=false;require __DIR__.'/partials/page-heading.php'; ?>
<div class="catalog-layout"><?php require __DIR__.'/partials/catalog-sidebar.php'; ?><section class="catalog-results" data-catalog-ssr aria-label="Продукти"><?php require __DIR__.'/partials/catalog-grid-controls.php'; ?><?php require __DIR__.'/partials/product-grid.php';require __DIR__.'/partials/pagination.php'; ?></section></div>
<?php $content=ob_get_clean();require __DIR__.'/layout.php'; ?>
