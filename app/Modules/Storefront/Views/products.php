<?php ob_start(); ?>
<?php $pageHeading='Всички продукти';$pageBreadcrumbs=[['label'=>'Начало','url'=>'/'],['label'=>'Всички продукти']];$pageMeta=(int)$total.' продукта';require __DIR__.'/partials/page-heading.php'; ?>
<?php require __DIR__.'/partials/product-grid.php'; ?>
<?php $content=ob_get_clean();require __DIR__.'/layout.php'; ?>
