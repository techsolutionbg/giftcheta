<?php
$closeButtonTag = ($closeButtonHref ?? null) !== null ? 'a' : 'button';
$closeButtonLabel = (string)($closeButtonLabel ?? 'Затвори');
$closeButtonClass = trim('site-close-button '.(string)($closeButtonClass ?? ''));
$closeButtonAttributes = is_array($closeButtonAttributes ?? null) ? $closeButtonAttributes : [];
$closeButtonHref = $closeButtonHref ?? null;
?>
<?php if ($closeButtonTag === 'a'): ?>
<a class="<?= htmlspecialchars($closeButtonClass, ENT_QUOTES, 'UTF-8') ?>" href="<?= htmlspecialchars((string)$closeButtonHref, ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars($closeButtonLabel, ENT_QUOTES, 'UTF-8') ?>">
<?php else: ?>
<button type="button" class="<?= htmlspecialchars($closeButtonClass, ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars($closeButtonLabel, ENT_QUOTES, 'UTF-8') ?>" <?php foreach ($closeButtonAttributes as $attribute => $value): ?><?php if (preg_match('/^[a-z][a-z0-9-]*$/i', (string)$attribute) && $value !== false && $value !== null): ?><?= htmlspecialchars((string)$attribute, ENT_QUOTES, 'UTF-8') ?><?= $value === true ? '' : '="'.htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8').'"' ?> <?php endif; ?><?php endforeach; ?>>
<?php endif; ?>
  <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m6 6 12 12M18 6 6 18"/></svg>
<?php if ($closeButtonTag === 'a'): ?></a><?php else: ?></button><?php endif; ?>
<?php
unset($closeButtonTag, $closeButtonLabel, $closeButtonClass, $closeButtonAttributes, $closeButtonHref);
