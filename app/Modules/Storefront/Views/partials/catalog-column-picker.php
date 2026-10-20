<?php
$columnPickerClass=trim('catalog-column-picker '.(string)($columnPickerClass??''));
$columnPickerActive=max(1,min(4,(int)($columnPickerActive??4)));
$columnPickerHidden=(bool)($columnPickerHidden??false);
?>
<div class="<?= $view->e($columnPickerClass) ?>" role="group" aria-label="Брой колони"<?= $columnPickerHidden?' hidden':'' ?>>
  <?php for($columnCount=1;$columnCount<=4;$columnCount++): $active=$columnPickerActive===$columnCount; ?>
    <button type="button" class="catalog-column-button<?= $active?' is-active':'' ?>" data-catalog-columns="<?= $columnCount ?>" aria-label="Показвай в <?= $columnCount ?> <?= $columnCount===1?'колона':'колони' ?>" aria-pressed="<?= $active?'true':'false' ?>" title="<?= $columnCount ?> <?= $columnCount===1?'колона':'колони' ?>"><span aria-hidden="true"><?php for($i=0;$i<$columnCount;$i++): ?><i></i><?php endfor; ?></span></button>
  <?php endfor; ?>
</div>
<?php unset($columnPickerClass,$columnPickerActive,$columnPickerHidden,$columnCount,$active); ?>
