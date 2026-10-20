<?php if(!empty($categoryCards)): ?>
<nav class="category-grid category-grid-visual" aria-label="<?= $view->e($categoryGridLabel??'Категории') ?>">
<?php foreach($categoryCards as $index=>$categoryCard): $categoryImage=$view->image($categoryCard['image_path']??null); ?>
  <a class="category-card category-card-visual" href="/category/<?= $view->e($categoryCard['slug']) ?>" style="--category-card-index:<?= (int)$index ?>">
    <?php if($categoryImage): ?><img src="<?= $view->e($categoryImage) ?>" alt="" loading="lazy" decoding="async"><?php else: ?><span class="category-card-art" aria-hidden="true"><span></span><span></span><span></span></span><?php endif; ?>
    <span class="category-card-copy"><strong><?= $view->e($categoryCard['name']) ?></strong><span>Разгледай категорията <span aria-hidden="true">→</span></span></span>
  </a>
<?php endforeach; ?>
</nav>
<style>
.category-grid-visual{grid-template-columns:repeat(auto-fit,minmax(min(100%,230px),1fr));gap:18px;margin:0 0 26px}
.category-card-visual{position:relative;isolation:isolate;display:block;width:100%;min-width:0;min-height:0;padding:0;aspect-ratio:1.48/1;border:1px solid #eadfd9;border-radius:18px;box-shadow:0 5px 18px #3f26300a;opacity:0;animation:category-card-enter .5s cubic-bezier(.2,.7,.2,1) forwards;animation-delay:calc(var(--category-card-index,0)*65ms);transition:transform .28s ease,box-shadow .28s ease,border-color .28s ease}
.category-card-visual:hover{transform:translateY(-5px);border-color:#e2b7c1;box-shadow:0 14px 30px #7034441c;color:#fff}
.category-card-visual>img{position:absolute;inset:0;width:100%;height:100%;max-height:none;object-fit:cover;border-radius:inherit;background:#f7e8ec;transition:transform .55s cubic-bezier(.2,.7,.2,1),filter .35s ease}
.category-card-visual:hover>img{transform:scale(1.055);filter:saturate(1.08)}
.category-card-visual:after{position:absolute;z-index:0;inset:25% 0 0;border-radius:inherit;background:linear-gradient(180deg,transparent,#21141abf);content:"";pointer-events:none}
.category-card-copy{position:absolute;z-index:1;right:0;bottom:0;left:0;display:grid;gap:3px;padding:20px 19px;color:#fff;text-shadow:0 1px 8px #24161d8c;transform:translateY(3px);transition:transform .28s ease}
.category-card-visual:hover .category-card-copy{transform:translateY(0)}
.category-card-copy strong{font-size:1.14rem;font-weight:500;line-height:1.3}
.category-card-copy>span{display:flex;align-items:center;gap:7px;font-size:.9rem;opacity:.88}
.category-card-copy>span span{transition:transform .2s ease}.category-card-visual:hover .category-card-copy>span span{transform:translateX(4px)}
.category-card-art{position:absolute;inset:0;overflow:hidden;border-radius:inherit;background:radial-gradient(ellipse at 25% 25%,#fff8 0 8%,transparent 9%),radial-gradient(ellipse at 70% 65%,#fff6 0 12%,transparent 13%),linear-gradient(135deg,#edc2cc,#b9798e 48%,#70485e)}
.category-card-art span{position:absolute;width:42%;aspect-ratio:1;border:1px solid #ffffff66;border-radius:50%;background:#fff2;filter:blur(.2px);transform:rotate(-25deg)}.category-card-art span:nth-child(1){top:-12%;left:8%}.category-card-art span:nth-child(2){right:-10%;bottom:5%;width:58%}.category-card-art span:nth-child(3){top:24%;left:37%;width:28%;background:#f9d5dc66}
.category-card-visual:focus-visible{outline:3px solid #d96582;outline-offset:3px}
@keyframes category-card-enter{to{opacity:1}}
@media(max-width:650px){.category-grid-visual{gap:12px}.category-card-visual{min-height:0;aspect-ratio:1.2/1;border-radius:15px}.category-card-copy{padding:14px}.category-card-copy strong{font-size:1rem}}
@media(prefers-reduced-motion:reduce){.category-card-visual,.category-card-visual>img,.category-card-copy,.category-card-copy>span span{animation:none;transition:none;transform:none;opacity:1}}
</style>
<?php endif; ?>
