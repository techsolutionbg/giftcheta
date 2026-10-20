<?php ob_start(); ?>
<?php $pageHeading=(string)$category['name'];$pageBreadcrumbs=[['label'=>'Начало','url'=>'/'],['label'=>'Всички продукти','url'=>'/products']];foreach($breadcrumbs as $crumb)$pageBreadcrumbs[]=['label'=>$crumb['name'],'url'=>(int)$crumb['id']===(int)$category['id']?null:'/category/'.$crumb['slug']];$pageMeta='';$pageShowFilter=true;$pageShowFilterButton=false;require __DIR__.'/partials/page-heading.php'; ?>
<?php $categoryCards=$children;$categoryGridLabel='Подкатегории';require __DIR__.'/partials/category-grid-visual.php'; ?>
<div class="catalog-layout"><?php require __DIR__.'/partials/catalog-sidebar.php'; ?><section class="catalog-results" data-catalog-ssr aria-label="Продукти в категорията"><?php require __DIR__.'/partials/catalog-grid-controls.php'; ?><?php require __DIR__.'/partials/product-grid.php';require __DIR__.'/partials/pagination.php'; ?></section></div>
<?php $content=ob_get_clean();require __DIR__.'/layout.php'; ?>
