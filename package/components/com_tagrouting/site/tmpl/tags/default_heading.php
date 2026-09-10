<?php

defined('_JEXEC') or die;
?>
<div class="com-tagrouting__heading page-header">
    <h1>
        <?php if ($this->landingIcon !== '') : ?>
            <span class="<?= $this->escape($this->landingIcon) ?>" aria-hidden="true"></span>
        <?php endif; ?>
        <?= $this->escape($this->landingTitle) ?>
    </h1>
</div>
