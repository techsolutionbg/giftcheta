<style>
.catalog-column-picker{display:flex;align-items:center;gap:3px;padding:2px;border:1px solid var(--line);border-radius:10px;background:#fff}
.catalog-column-picker[hidden]{display:none!important}
.catalog-column-button{width:32px;height:30px;display:grid;place-items:center;padding:0;border:1px solid var(--line);border-radius:8px;background:#fff;color:#716664;cursor:pointer;transition:border-color .15s,background .15s,color .15s,box-shadow .15s}
.catalog-column-button:hover{background:#fff4f6;color:var(--accent-dark)}
.catalog-column-button.is-active{border-color:#f0c9d3;background:#fbe8ed;color:var(--accent-dark);box-shadow:inset 0 0 0 1px #f0c9d3}
.catalog-column-button:focus-visible{outline:2px solid var(--accent);outline-offset:2px}
.catalog-column-button>span{display:flex;align-items:stretch;justify-content:center;gap:2px;width:22px;height:16px}
.catalog-column-button i{flex:1;border:1px solid currentColor;border-radius:2px;background:currentColor;opacity:.78}
.product-grid[data-columns="1"]{grid-template-columns:minmax(0,1fr)}
.product-grid[data-columns="2"]{grid-template-columns:repeat(2,minmax(0,1fr))}
.product-grid[data-columns="3"]{grid-template-columns:repeat(3,minmax(0,1fr))}
.product-grid[data-columns="4"]{grid-template-columns:repeat(4,minmax(0,1fr))}
@media(max-width:900px){.product-grid[data-columns="4"]{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media(max-width:650px){.product-grid[data-columns="3"],.product-grid[data-columns="4"]{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(min-width:651px) and (max-width:1399px){.catalog-column-button[data-catalog-columns="4"]{display:none}}
@media(min-width:761px){.catalog-column-picker{margin-left:auto}}
.catalog-column-picker{box-sizing:border-box;height:42px;min-height:42px;gap:3px;padding:0;border:0;background:transparent}
.catalog-column-button{box-sizing:border-box;width:42px;height:42px;min-width:42px;min-height:42px}
@media(max-width:650px){.catalog-column-button[data-catalog-columns="3"],.catalog-column-button[data-catalog-columns="4"]{display:none}}
</style>
