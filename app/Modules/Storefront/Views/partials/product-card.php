<?php
$image=$view->image($item['image_path']??null);
$price=(float)($item['public_price']??$item['sale_price']??$item['price']);
$regularPublicPrice=(float)($item['regular_public_price']??$item['price']??0);
$hasVariants=(bool)($item['has_active_variants']??false);
$needsCustomization=(bool)($item['has_required_customizations']??false);
$directVariantAdd=$hasVariants&&!$needsCustomization&&!empty($item['can_direct_variant_add']);
$available=$hasVariants?(bool)($item['has_available_variant']??false):((bool)($item['is_available']??false)&&(($item['inventory_mode']??'boolean')!=='tracked'||(int)($item['stock_quantity']??0)>0));
$onSale=$regularPublicPrice>0&&$price>0&&$price<$regularPublicPrice;
$regularPriceCents=(int)round($regularPublicPrice*100);$currentPriceCents=(int)round($price*100);$savedCents=max(0,$regularPriceCents-$currentPriceCents);$discountPercent=$regularPriceCents>0?(int)round($savedCents/$regularPriceCents*100):0;
$favoriteProduct=['id'=>(int)$item['id'],'name'=>(string)$item['name'],'slug'=>(string)$item['slug'],'image'=>$image,'price'=>$price];
$favoriteJson=json_encode($favoriteProduct,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
?>
<article class="product-card">
  <div class="product-card-media">
    <a href="/product/<?= $view->e($item['slug']) ?>" aria-label="<?= $view->e($item['name']) ?>">
      <?php if($image): ?><img src="<?= $view->e($image) ?>" alt="<?= $view->e($item['image_alt']?:$item['name']) ?>" loading="lazy"><?php else: ?><div class="image-placeholder" aria-hidden="true"></div><?php endif; ?>
    </a>
    <span class="product-card-promo" data-card-promo <?= $onSale?'':'hidden' ?>><?php if($onSale): ?>Спестявате <?= $view->e($view->money($savedCents/100)) ?> · <?= $discountPercent ?>%<?php endif; ?></span>
    <button class="product-card-favorite" type="button" data-favorite-toggle aria-pressed="false" aria-label="Добави продукта в любими" data-favorite-product="<?= $view->e($favoriteJson?:'{}') ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1.1L12 21l7.8-7.5 1.1-1.1a5.5 5.5 0 0 0-.1-7.8Z"/></svg></button>
  </div>
  <div class="product-card-body">
    <h3><a href="/product/<?= $view->e($item['slug']) ?>"><?= $view->e($item['name']) ?></a></h3>
    <?php if(!empty($item['sku'])): ?><p class="product-card-detail-meta product-card-sku">Код: <?= $view->e($item['sku']) ?></p><?php endif; ?><?php if(!empty($item['short_description'])): ?><p class="product-card-detail-meta product-card-short-description"><?= $view->e($item['short_description']) ?></p><?php endif; ?><p class="product-card-detail-meta product-card-stock <?= $available?'is-available':'is-unavailable' ?>"><?= $available?'В наличност':'Временно неналичен' ?></p>
    <?php if(!$directVariantAdd&&(!empty($item['variant_colors'])||!empty($item['variant_sizes']))): ?><div class="product-card-variants" aria-label="Варианти на продукта"><?php if(!empty($item['variant_colors'])): ?><div class="product-card-variant-group"><span class="product-card-color-list"><?php foreach(array_slice($item['variant_colors'],0,5) as $color): ?><?php if($color['hex']): ?><span class="product-card-color" style="--variant-color:<?= $view->e($color['hex']) ?>" title="<?= $view->e($color['label']) ?>" aria-label="<?= $view->e($color['label']) ?>"></span><?php else: ?><span class="product-card-color-name"><?= $view->e($color['label']) ?></span><?php endif; ?><?php endforeach; ?></span></div><?php endif; ?><?php if(!empty($item['variant_sizes'])): ?><div class="product-card-variant-group"><span class="product-card-size-list"><?php foreach(array_slice($item['variant_sizes'],0,5) as $size): ?><span class="product-card-size"><?= $view->e($size['label']) ?></span><?php endforeach; ?></span></div><?php endif; ?></div><?php endif; ?>
    <div class="product-card-price-row"><span class="price" data-card-price-current><?= $view->money($price) ?></span><span class="product-card-price-was" data-card-price-was <?= $onSale?'':'hidden' ?>><?= $view->money($regularPublicPrice) ?></span></div>
    <div class="product-card-actions">
      <?php if(!$available): ?>
        <button class="product-card-add-button" type="button" disabled aria-label="Няма наличност" title="Няма наличност"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h2l2.2 11.2a2 2 0 0 0 2 1.6h8.6a2 2 0 0 0 1.9-1.4L22 8H6"/><circle cx="10" cy="20" r="1"/><circle cx="18" cy="20" r="1"/><path d="M5 5 19 19"/></svg><span>Няма наличност</span></button>
      <?php elseif($directVariantAdd): ?>
        <?php $variantControls=json_encode($item['variant_combinations'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);$defaultOther=json_encode($item['default_variant_other'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>
        <form class="product-card-add-form" method="post" action="/cart/items" data-grid-cart-add data-card-variant-form data-default-other="<?= $view->e($defaultOther?:'{}') ?>" data-variant-combinations="<?= $view->e($variantControls?:'[]') ?>">
          <input type="hidden" name="_csrf" value="<?= $view->e($csrfToken??'') ?>"><input type="hidden" name="product_id" value="<?= (int)$item['id'] ?>"><input type="hidden" name="quantity" value="1"><input type="hidden" name="variant_id" value="<?= (int)$item['default_variant_id'] ?>" data-card-variant-id>
          <div class="product-card-variants" aria-label="Избор на вариант">
            <?php if(!empty($item['variant_colors'])): ?><fieldset class="product-card-variant-group"><legend class="product-card-sr-only">Цвят</legend><span class="product-card-color-list"><?php foreach($item['variant_colors'] as $color): ?><label class="product-card-color-choice"><input type="radio" name="card_color_<?= (int)$item['id'] ?>" value="<?= $view->e($color['label']) ?>" data-card-variant-choice="color" <?= $color['label']===$item['default_variant_color']?'checked':'' ?> aria-label="Цвят: <?= $view->e($color['label']) ?>"><?php if($color['hex']): ?><span class="product-card-color" style="--variant-color:<?= $view->e($color['hex']) ?>" title="<?= $view->e($color['label']) ?>"></span><?php else: ?><span class="product-card-color-name"><?= $view->e($color['label']) ?></span><?php endif; ?></label><?php endforeach; ?></span></fieldset><?php endif; ?>
            <?php if(!empty($item['variant_sizes'])): ?><fieldset class="product-card-variant-group"><legend class="product-card-sr-only">Размер</legend><span class="product-card-size-list"><?php foreach($item['variant_sizes'] as $size): ?><label class="product-card-size-choice"><input type="radio" name="card_size_<?= (int)$item['id'] ?>" value="<?= $view->e($size['label']) ?>" data-card-variant-choice="size" <?= $size['label']===$item['default_variant_size']?'checked':'' ?> aria-label="Размер: <?= $view->e($size['label']) ?>"><span class="product-card-size"><?= $view->e($size['label']) ?></span></label><?php endforeach; ?></span></fieldset><?php endif; ?>
          </div>
          <button class="product-card-add-button" type="submit" aria-label="Добави в количката" title="Добави в количката"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h2l2.2 11.2a2 2 0 0 0 2 1.6h8.6a2 2 0 0 0 1.9-1.4L22 8H6"/><circle cx="10" cy="20" r="1"/><circle cx="18" cy="20" r="1"/><path d="M12 5v6m-3-3h6"/></svg><span>Добави в количката</span></button><span class="product-card-add-feedback" role="status" aria-live="polite" hidden></span>
        </form>
      <?php elseif($hasVariants||$needsCustomization): ?>
        <a class="product-card-options-link" href="/product/<?= $view->e($item['slug']) ?>" aria-label="Персонализирай продукта" title="Персонализирай продукта"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16M4 12h16M4 19h16M8 3v4m8 3v4m-5 3v4"/></svg><span>Персонализирай</span></a>
      <?php else: ?>
        <form class="product-card-add-form" method="post" action="/cart/items" data-grid-cart-add>
          <input type="hidden" name="_csrf" value="<?= $view->e($csrfToken??'') ?>"><input type="hidden" name="product_id" value="<?= (int)$item['id'] ?>"><input type="hidden" name="quantity" value="1">
          <button class="product-card-add-button" type="submit" aria-label="Добави в количката" title="Добави в количката"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h2l2.2 11.2a2 2 0 0 0 2 1.6h8.6a2 2 0 0 0 1.9-1.4L22 8H6"/><circle cx="10" cy="20" r="1"/><circle cx="18" cy="20" r="1"/><path d="M12 5v6m-3-3h6"/></svg><span>Добави в количката</span></button><span class="product-card-add-feedback" role="status" aria-live="polite" hidden></span>
        </form>
      <?php endif; ?>
      <button class="product-card-quick-preview" type="button" data-quick-view-trigger aria-label="Бърз преглед" title="Бърз преглед"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.5"/><path d="m16 16 4.5 4.5M10.8 7.8v6m-3-3h6"/></svg><span>Бърз преглед</span></button>
    </div>
  </div>
</article>
