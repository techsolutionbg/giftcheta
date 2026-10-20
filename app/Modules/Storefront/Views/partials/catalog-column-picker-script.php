<script>
(()=>{
  if(window.__giftchetaColumnPicker)return;
  window.__giftchetaColumnPicker=true;
  const storageKey='giftcheta:catalog-columns:v1';
  const mode=()=>window.matchMedia('(max-width: 650px)').matches?'mobile':window.matchMedia('(min-width: 1400px)').matches?'desktop':'laptop';
  const limits={mobile:2,laptop:3,desktop:4},defaults={mobile:2,laptop:3,desktop:4};
  let lastMode=mode();
  function preferences(){try{const value=JSON.parse(localStorage.getItem(storageKey)||'{}');return value&&typeof value==='object'?value:{}}catch{return{}}}
  function savePreference(type,columns){try{localStorage.setItem(storageKey,JSON.stringify({...preferences(),[type]:columns}))}catch{}}
  function favoritesPage(){return document.querySelector('[data-favorites-page]')}
  function currentGrid(){return document.querySelector('.catalog-results .product-grid')||favoritesPage()?.querySelector('.product-grid')||null}
  function applyColumns(columns){const grid=currentGrid();if(grid)grid.dataset.columns=String(columns);document.querySelectorAll('.catalog-column-picker [data-catalog-columns]').forEach(button=>{const active=Number(button.dataset.catalogColumns)===columns;button.classList.toggle('is-active',active);button.setAttribute('aria-pressed',String(active))});const hidden=document.querySelector('[data-live-catalog-filters] input[name="columns"]');if(hidden)hidden.value=String(columns)}
  function setColumns(value){const grid=currentGrid();if(!grid)return;const type=mode(),max=limits[type],columns=Math.max(1,Math.min(max,Number(value)||defaults[type]));applyColumns(columns);savePreference(type,columns);const url=new URL(location.href);url.searchParams.set('columns',String(columns));url.searchParams.delete('page');history.pushState({},'',url.pathname+url.search)}
  function syncColumns(){const grid=currentGrid();if(!grid)return;const type=mode(),modeChanged=type!==lastMode,url=new URL(location.href),isFavorites=!!favoritesPage(),hasUrlColumns=url.searchParams.has('columns'),requestedFromUrl=Number(url.searchParams.get('columns')),saved=Number(preferences()[type]);let legacyFavorites=0;if(isFavorites){try{legacyFavorites=Number(localStorage.getItem('giftcheta:favorites-columns:v1'))}catch{}}const requested=modeChanged?0:(hasUrlColumns&&requestedFromUrl>0?requestedFromUrl:(saved>0?saved:(legacyFavorites>0?legacyFavorites:defaults[type]))),columns=Math.max(1,Math.min(limits[type],requested||defaults[type]));applyColumns(columns);if(isFavorites&&saved<=0&&legacyFavorites>0)savePreference(type,columns);if(hasUrlColumns&&requestedFromUrl!==columns){url.searchParams.set('columns',String(columns));history.replaceState(history.state,'',url.pathname+url.search)}lastMode=type;syncFavoritesVisibility()}
  function syncFavoritesVisibility(){const page=favoritesPage();if(!page)return;const grid=page.querySelector('.product-grid'),controls=page.querySelector('.favorites-grid-controls');if(controls)controls.hidden=!grid||grid.childElementCount===0}
  document.addEventListener('click',event=>{const button=event.target.closest('.catalog-column-picker [data-catalog-columns]');if(button)setColumns(button.dataset.catalogColumns)});
  window.addEventListener('resize',syncColumns);
  window.addEventListener('popstate',()=>{lastMode=mode();syncColumns()});
  window.addEventListener('giftcheta:navigated',()=>{lastMode=mode();syncColumns()});
  window.addEventListener('giftcheta:favorites-rendered',syncFavoritesVisibility);
  window.addEventListener('giftcheta:favorites-changed',syncFavoritesVisibility);
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',syncColumns,{once:true});else syncColumns();
})();
</script>
