<?php $e = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }; ?>
<div class="panel panel-default" id="neocarts-product">
  <div class="panel-heading"><h3 class="panel-title"><i class="fa fa-refresh"></i> <?php echo $e($t['heading_product_panel']); ?></h3></div>
  <div class="panel-body">
    <input type="hidden" name="neocarts_subscription_present" value="1" />
    <?php foreach ($switches as $i => $switch) { ?>
    <div class="checkbox"<?php echo $i ? ' style="margin-left:20px"' : ''; ?>>
      <label><input type="checkbox" name="neocarts_subscription[<?php echo $e($switch['id']); ?>]" value="1"<?php echo $switch['on'] ? ' checked="checked"' : ''; ?> /> <?php echo $e($switch['label']); ?></label>
    </div>
    <?php } ?>
    <p class="help-block"><?php echo $e($t['help_product_subscription']); ?></p>
    <p><a href="<?php echo $e($admin_url); ?>" target="_blank" rel="noopener"><i class="fa fa-external-link"></i> <?php echo $e($t['text_neocarts_panel_link']); ?></a></p>
    <?php echo $support_footer; ?>
  </div>
</div>
