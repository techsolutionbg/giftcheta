<?php ob_start(); ?>
<section class="favorites-page" data-favorites-page aria-labelledby="favorites-title">
  <header class="favorites-heading">
    <h1 id="favorites-title">Любими продукти</h1>
    <p>Запази идеите, които харесваш, и се върни към тях по всяко време.</p>
  </header>
  <?php $items=[];$filters=['columns'=>4];$gridAlways=true;$gridExtraClass='favorites-grid';$gridDataFavorites=true;$renderGridEmptyMessage=false;require __DIR__.'/partials/product-grid.php';unset($items,$filters,$gridAlways,$gridExtraClass,$gridDataFavorites,$renderGridEmptyMessage); ?>
  <div class="favorites-empty empty" data-favorites-empty hidden>
    <span class="favorites-empty-heart" aria-hidden="true">♡</span>
    <h2>Тук ще съберем любимите ти подаръци</h2>
    <p>Избери сърцето на продукт, за да го запазиш в тази секция.</p>
  </div>
</section>
<style>
.favorites-page{font-weight:400}.favorites-page h1,.favorites-page h2,.favorites-page strong,.favorites-page b{font-weight:400!important}.favorites-heading{margin:0 0 26px;text-align:center}.favorites-heading h1{margin:0 0 9px;color:#2e2528;font-size:clamp(1.9rem,4vw,2.65rem);line-height:1.15;letter-spacing:-.035em}.favorites-heading p,.favorites-empty p{font-size:1.08rem;line-height:1.6}.favorites-heading p{margin:0;color:var(--muted)}.favorites-grid{margin-bottom:30px}.favorites-grid[hidden]{display:none}.favorites-empty{display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:230px;padding:42px 20px;background:linear-gradient(135deg,#fff9fa,#fff1f4 55%,#fff8f9);border:1px solid #f4dce2;border-radius:18px;text-align:center}.favorites-empty[hidden]{display:none}.favorites-empty-heart{display:block;color:#e34c70;font-size:3rem;line-height:1}.favorites-empty h2{margin:10px 0 7px;color:#3b3033;font-size:1.2rem}.favorites-empty p{max-width:560px;margin:0;color:var(--muted)}@media(max-width:520px){.favorites-heading p,.favorites-empty p{font-size:1rem}.favorites-empty{min-height:200px;padding:34px 18px}}
</style>
<?php $content=ob_get_clean();require __DIR__.'/layout.php'; ?>
