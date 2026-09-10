<?php

defined('_JEXEC') or die;

$heading = (string) $this->params->get('page_heading', '');
?>
<div class="com-tagrouting tag-pages">
    <?php if ((bool) $this->params->get('show_page_heading', 0) && $heading !== '') : ?>
        <h1><?= $this->escape($heading) ?></h1>
    <?php endif; ?>
</div>
