<?php $hasActiveCatalogFilters=($filters['min_price']??null)!==null||($filters['max_price']??null)!==null||!empty($filters['in_stock'])||!empty($filters['on_sale'])||!empty($filters['colors'])||!empty($filters['sizes'])||!empty($filters['categories'])||($filters['sort']??'latest')!=='latest'; ?>
<div class="catalog-results-toolbar">
  <div class="catalog-toolbar-controls">
    <div class="catalog-results-toolbar-meta"><button class="catalog-filter-button catalog-filter-toggle" type="button" data-catalog-filter-toggle aria-controls="catalog-filters" aria-label="Покажи или скрий филтрите" aria-expanded="true"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h10M18 7h2M4 17h2m4 0h10"/><circle cx="16" cy="7" r="2"/><circle cx="8" cy="17" r="2"/></svg><span>Филтър</span></button><label class="catalog-toolbar-sort" for="catalog-sort"><select id="catalog-sort" name="sort" form="catalog-filter-form" aria-label="Сортиране на продуктите"><option value="latest" <?= ($filters['sort']??'latest')==='latest'?'selected':'' ?>>Най-нови</option><option value="price_asc" <?= ($filters['sort']??'')==='price_asc'?'selected':'' ?>>Цена: ниска към висока</option><option value="price_desc" <?= ($filters['sort']??'')==='price_desc'?'selected':'' ?>>Цена: висока към ниска</option><option value="name" <?= ($filters['sort']??'')==='name'?'selected':'' ?>>Име</option></select></label></div>
    <?php if($hasActiveCatalogFilters): ?><a class="catalog-toolbar-clear" href="<?= isset($category)?'/category/'.$view->e($category['slug']):'/products' ?>">Изчисти филтрите</a><?php endif; ?>
    <?php $columnPickerActive=(int)($filters['columns']??4);$columnPickerClass='';$columnPickerHidden=false;require __DIR__.'/catalog-column-picker.php'; ?>
  </div>
  <p class="catalog-result-count" aria-live="polite" aria-atomic="true"><?= (int)$total ?> продукта</p>
</div><style>
 .catalog-results{transition:opacity .14s ease}.catalog-results.is-loading{opacity:1;pointer-events:none}.catalog-results-toolbar{display:flex;align-items:center;justify-content:space-between;gap:12px;margin:0 0 12px}.catalog-results-toolbar-meta{display:flex;align-items:center;gap:14px;min-width:0}.catalog-result-count{display:none!important;margin:0;color:var(--muted);white-space:nowrap}.catalog-toolbar-sort{display:flex;align-items:center;gap:8px;color:var(--muted);font-size:.9rem;white-space:nowrap}.catalog-toolbar-sort select{min-width:155px;min-height:40px;padding:7px 34px 7px 11px;border:1px solid var(--line);border-radius:9px;background:#fff;color:var(--ink);font:inherit;font-size:.92rem}.catalog-toolbar-sort select:focus-visible{outline:3px solid #e94a7040;outline-offset:2px}.catalog-toolbar-clear{display:inline-flex;align-items:center;justify-content:center;min-height:40px;padding:0 12px;border:1px solid var(--line);border-radius:9px;background:#fff;color:var(--muted);font-size:.9rem;white-space:nowrap;transition:border-color .16s ease,background .16s ease,color .16s ease}.catalog-toolbar-clear:hover{border-color:#e3b7c1;background:#fff7f8;color:var(--accent-dark)}.catalog-filter-button{min-height:42px;display:inline-flex;align-items:center;justify-content:center;gap:9px;padding:0 15px;border:1px solid #eadfdd;border-radius:9px;background:#fff;color:#4d4242;font:inherit;font-size:.92rem;cursor:pointer;transition:border-color .16s ease,background .16s ease,transform .16s ease}.catalog-filter-button:hover{border-color:#dcaab5;background:#fff8f9;transform:translateY(-1px)}.catalog-filter-button:focus-visible{outline:3px solid #e94a7040;outline-offset:2px}.catalog-filter-button svg{width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}.catalog-column-picker{display:flex;align-items:center;gap:3px;padding:2px;border:1px solid var(--line);border-radius:10px;background:#fff}.catalog-column-button{width:32px;height:30px;display:grid;place-items:center;padding:0;border:1px solid var(--line);border-radius:8px;background:#fff;color:#716664;cursor:pointer;transition:border-color .15s,background .15s,color .15s,box-shadow .15s}.catalog-column-button:hover{background:#fff4f6;color:var(--accent-dark)}.catalog-column-button.is-active{border-color:#f0c9d3;background:#fbe8ed;color:var(--accent-dark);box-shadow:inset 0 0 0 1px #f0c9d3}.catalog-column-button:focus-visible{outline:2px solid var(--accent);outline-offset:2px}.catalog-column-button>span{display:flex;align-items:stretch;justify-content:center;gap:2px;width:22px;height:16px}.catalog-column-button i{flex:1;border:1px solid currentColor;border-radius:2px;background:currentColor;opacity:.78}
.catalog-results .product-grid{gap:14px}.product-grid[data-columns="1"]{grid-template-columns:minmax(0,1fr)}.product-grid[data-columns="2"]{grid-template-columns:repeat(2,minmax(0,1fr))}.product-grid[data-columns="3"]{grid-template-columns:repeat(3,minmax(0,1fr))}.product-grid[data-columns="4"]{grid-template-columns:repeat(4,minmax(0,1fr))}
.catalog-results.is-ssr-loading>.product-grid:not(.catalog-skeleton-grid){display:none}
@media(min-width:651px) and (max-width:1399px){.catalog-column-button[data-catalog-columns="4"]{display:none}}@media(min-width:1200px) and (max-width:1399px){.product-grid[data-columns="4"]{grid-template-columns:repeat(3,minmax(0,1fr))}}
.product-grid[data-columns="1"] .product-card{display:grid;grid-template-columns:minmax(220px,34%) minmax(0,1fr);align-items:stretch}.product-grid[data-columns="1"] .product-card-media{position:relative;height:auto;min-height:260px;align-self:stretch}.product-grid[data-columns="1"] .product-card-media>a{position:absolute;inset:0;height:auto}.product-grid[data-columns="1"] .product-card-media>a>img,.product-grid[data-columns="1"] .product-card-media>a>.image-placeholder{height:100%;aspect-ratio:auto}.product-grid[data-columns="1"] .product-card-media>a>img{object-fit:cover}.product-grid[data-columns="1"] .product-card-body{align-self:stretch;padding:18px 16px;gap:8px}.product-grid[data-columns="1"] .product-card-detail-meta{display:block}.product-grid[data-columns="1"] .product-card-short-description{margin:0;color:var(--muted);font-size:.96rem;line-height:1.55}.product-grid[data-columns="1"] .product-card-sku{margin:0;color:var(--muted);font-size:.84rem}.product-grid[data-columns="1"] .product-card-stock{margin:0;font-size:.9rem}.product-grid[data-columns="1"] .product-card-stock.is-available{color:#26704e}.product-grid[data-columns="1"] .product-card-stock.is-unavailable{color:#a34747}.product-grid[data-columns="1"] .product-card-price-row{margin-top:0}.product-grid[data-columns="1"] .product-card-actions{display:flex;flex-wrap:wrap;align-items:center;gap:8px;margin-top:0;padding-top:2px}.product-grid[data-columns="1"] .product-card-add-form{display:contents}.product-grid[data-columns="1"] .product-card-add-form>.product-card-variants{flex:1 0 100%;margin:2px 0}.product-grid[data-columns="1"] .product-card-add-button,.product-grid[data-columns="1"] .product-card-options-link{width:auto;min-width:155px;flex:0 1 auto}.product-grid[data-columns="1"] .product-card-quick-preview{width:auto;min-width:0;min-height:38px;flex:0 1 auto;padding:7px 10px;font-size:.86rem}
@media(max-width:900px){.product-grid[data-columns="4"]{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media(max-width:760px){.catalog-results-toolbar{flex-wrap:wrap}.catalog-results-toolbar-meta{flex:1 1 auto;flex-wrap:wrap;gap:9px}.catalog-toolbar-sort{flex:1 1 160px}.catalog-toolbar-sort select{width:100%;min-width:0}.catalog-toolbar-clear{order:3;margin-left:auto}}
@media(max-width:650px){.product-grid[data-columns="3"],.product-grid[data-columns="4"]{grid-template-columns:repeat(2,minmax(0,1fr))}.catalog-results .product-grid{gap:10px}.catalog-column-button{width:30px;height:29px}.catalog-results-toolbar-meta{gap:9px}.catalog-filter-button{min-height:38px;padding:0 11px;font-size:.86rem}.catalog-toolbar-clear{min-height:36px;padding:0 10px;font-size:.84rem}.catalog-toolbar-sort select{min-height:38px;font-size:.88rem}.product-grid[data-columns="1"] .product-card{grid-template-columns:minmax(112px,36%) minmax(0,1fr)}.product-grid[data-columns="1"] .product-card-media{min-height:220px}.product-grid[data-columns="1"] .product-card-body{padding:13px;gap:6px}.product-grid[data-columns="1"] .product-card-short-description{font-size:.88rem}}
.catalog-toolbar-controls{display:flex;align-items:center;gap:10px;min-width:0;width:100%}.catalog-results-toolbar-meta{flex:0 1 auto}.catalog-results-toolbar>.catalog-result-count{margin:0 0 0 auto;text-align:right}.catalog-toolbar-clear{margin:0;order:0}
@media(min-width:761px){.catalog-column-picker{margin-left:auto}}
@media(max-width:760px){.catalog-results-toolbar{align-items:center;flex-wrap:wrap}.catalog-toolbar-controls{flex:1 1 auto;flex-wrap:wrap}.catalog-results-toolbar-meta{flex:0 1 auto}.catalog-results-toolbar>.catalog-result-count{margin-left:auto}.catalog-toolbar-clear{order:0;margin-left:0}}
.catalog-toolbar-controls,.catalog-results-toolbar-meta{gap:10px}.catalog-filter-button,.catalog-toolbar-sort select,.catalog-toolbar-clear{box-sizing:border-box;height:42px;min-height:42px}.catalog-filter-button,.catalog-toolbar-clear{padding:0 14px}.catalog-toolbar-sort select{padding-top:0;padding-bottom:0}.catalog-column-picker{box-sizing:border-box;height:42px;min-height:42px;gap:3px;padding:0;border:0;background:transparent}.catalog-column-button{box-sizing:border-box;width:42px;height:42px;min-width:42px;min-height:42px}
@media(max-width:650px){.catalog-column-button[data-catalog-columns="3"],.catalog-column-button[data-catalog-columns="4"]{display:none}.catalog-filter-button,.catalog-toolbar-sort select,.catalog-toolbar-clear{height:42px;min-height:42px}.catalog-column-picker{height:42px;min-height:42px}.catalog-column-button{width:42px;height:42px;min-width:42px;min-height:42px}.catalog-skeleton-grid[data-columns="1"]>.catalog-skeleton-card:nth-child(n+3){display:none}}
</style>
<?php require __DIR__.'/catalog-column-picker-styles.php'; ?>
<style>
.catalog-skeleton-card{display:flex;min-width:0;flex-direction:column;overflow:hidden;border:1px solid #f0e4e1;border-radius:var(--radius);background:#fff}
.catalog-skeleton-media{position:relative;aspect-ratio:1/1;overflow:hidden;background:#f7efec}
.catalog-skeleton-body{display:grid;flex:1;align-content:start;gap:11px;padding:14px 15px 17px}
.catalog-skeleton-line,.catalog-skeleton-action{position:relative;overflow:hidden;border-radius:7px;background:#f3eae8}
.catalog-skeleton-line{width:100%;height:13px}.catalog-skeleton-line.is-title{width:78%;height:17px}.catalog-skeleton-line.is-short{width:53%}.catalog-skeleton-line.is-price{width:38%;height:18px;margin-top:5px}
.catalog-skeleton-action{height:42px;margin-top:auto;border-radius:10px}
.catalog-skeleton-media:after,.catalog-skeleton-line:after,.catalog-skeleton-action:after{position:absolute;inset:0;background:linear-gradient(100deg,transparent 20%,#fff9 48%,transparent 76%);background-size:220% 100%;content:"";animation:catalog-skeleton-shimmer 1.25s ease-in-out infinite}
.catalog-results.is-appending{position:relative}.catalog-results.is-appending .catalog-appended-skeleton{pointer-events:none}
@keyframes catalog-skeleton-shimmer{to{background-position-x:-220%}}
.product-grid[data-columns="1"]>.catalog-skeleton-card{display:grid;grid-template-columns:minmax(220px,34%) minmax(0,1fr)}
.product-grid[data-columns="1"] .catalog-skeleton-media{min-height:260px;aspect-ratio:auto}
.product-grid[data-columns="1"] .catalog-skeleton-body{gap:13px;padding:18px 16px}
.product-grid[data-columns="1"] .catalog-skeleton-line.is-title{width:62%;height:20px}
@media(max-width:650px){.catalog-skeleton-body{gap:9px;padding:11px}.catalog-skeleton-line{height:11px}.catalog-skeleton-line.is-title{height:14px}.catalog-skeleton-action{height:38px}.product-grid[data-columns="1"]>.catalog-skeleton-card{grid-template-columns:minmax(112px,36%) minmax(0,1fr)}.product-grid[data-columns="1"] .catalog-skeleton-media{min-height:220px}.product-grid[data-columns="1"] .catalog-skeleton-body{gap:8px;padding:13px}}
@media(prefers-reduced-motion:reduce){.catalog-skeleton-media:after,.catalog-skeleton-line:after,.catalog-skeleton-action:after{animation:none;background:linear-gradient(100deg,transparent,#fff5,transparent)}}
</style>
<?php require __DIR__.'/catalog-column-picker-script.php'; ?>
<script>
(()=>{
  if(window.__giftchetaCatalogSkeleton)return;
  window.__giftchetaCatalogSkeleton=true;
  function showSkeleton(){
    const results=document.querySelector('.catalog-results');
    if(!results||results.querySelector('.catalog-skeleton-grid'))return;
    const currentGrid=results.querySelector('.product-grid');
    const columns=currentGrid?.dataset.columns||document.querySelector('[data-catalog-columns].is-active')?.dataset.catalogColumns||'4';
    const skeletonCount=Math.max(2,Number(columns)*2);
    const grid=document.createElement('div');
    grid.className='product-grid catalog-skeleton-grid';
    grid.dataset.columns=columns;
    grid.setAttribute('aria-hidden','true');
    for(let index=0;index<skeletonCount;index++){
      const card=document.createElement('article');card.className='catalog-skeleton-card';
      const media=document.createElement('div');media.className='catalog-skeleton-media';
      const body=document.createElement('div');body.className='catalog-skeleton-body';
      for(const className of ['catalog-skeleton-line is-title','catalog-skeleton-line','catalog-skeleton-line is-short','catalog-skeleton-line is-price','catalog-skeleton-action']){const block=document.createElement('span');block.className=className;body.append(block)}
      card.append(media,body);grid.append(card);
    }
    const empty=results.querySelector('.empty');
    if(currentGrid)currentGrid.replaceWith(grid);else if(empty)empty.replaceWith(grid);else results.append(grid);
    results.classList.add('is-loading');results.setAttribute('aria-busy','true');
  }
  document.addEventListener('click',event=>{if(event.target.closest('.catalog-results .pagination a,.catalog-results .catalog-toolbar-clear'))showSkeleton()},true);
  document.addEventListener('change',event=>{if(event.target.matches('[data-live-catalog-filters] input[type="checkbox"],[data-live-catalog-filters] input[type="number"],[data-live-catalog-filters] select'))showSkeleton()},true);
  document.addEventListener('input',event=>{if(event.target.matches('[data-live-catalog-filters] input[type="number"]'))showSkeleton()},true);
  document.addEventListener('submit',event=>{if(event.target.matches('[data-live-catalog-filters]'))showSkeleton()},true);
  window.addEventListener('popstate',showSkeleton);
})();
</script>
