<?php echo $header; ?><?php echo $column_left; ?>
<?php $e = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }; ?>
<div id="content">
  <div class="page-header">
    <div class="container-fluid">
      <div class="pull-right">
        <button type="submit" form="form-neocarts" class="btn btn-primary"><i class="fa fa-save"></i> <?php echo $e($t['button_save']); ?></button>
        <a href="<?php echo $back; ?>" class="btn btn-default"><i class="fa fa-reply"></i> <?php echo $e($t['button_back']); ?></a>
      </div>
      <h1><?php echo $e($t['heading_title']); ?></h1>
      <ul class="breadcrumb">
        <?php foreach ($breadcrumbs as $breadcrumb) { ?>
        <li><a href="<?php echo $breadcrumb['href']; ?>"><?php echo $e($breadcrumb['text']); ?></a></li>
        <?php } ?>
      </ul>
    </div>
  </div>
  <div class="container-fluid">
    <?php if ($maintenance) { ?>
    <div class="alert alert-warning"><i class="fa fa-exclamation-triangle"></i> <?php echo $e($t['warning_maintenance']); ?></div>
    <?php } ?>
    <?php if ($error) { ?>
    <div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <?php echo $e($error); ?></div>
    <?php } ?>
    <?php if ($success) { ?>
    <div class="alert alert-success"><i class="fa fa-check-circle"></i> <?php echo $e($success); ?></div>
    <?php } ?>
    <div class="panel panel-default">
      <div class="panel-body">
        <form action="<?php echo $action; ?>" method="post" id="form-neocarts" class="form-horizontal">
          <ul class="nav nav-tabs">
            <?php $first = true; foreach ($tabs as $tab => $fields) { ?>
            <li<?php echo $first ? ' class="active"' : ''; ?>><a href="#neocarts-tab-<?php echo $tab; ?>" data-toggle="tab"><?php echo $e($t['tab_' . $tab]); ?></a></li>
            <?php $first = false; } ?>
          </ul>
          <div class="tab-content">
            <?php $first = true; foreach ($tabs as $tab => $fields) { ?>
            <div class="tab-pane<?php echo $first ? ' active' : ''; ?>" id="neocarts-tab-<?php echo $tab; ?>">
              <?php foreach ($fields as $f) { ?>
              <?php if ($f['type'] === 'note') { ?>
              <div class="alert alert-info"><?php if ($f['label'] !== '') { ?><?php echo $e($f['label']); ?><br /><?php } ?><?php echo empty($f['code']) ? $e($f['help']) : '<code style="white-space:normal">' . $e($f['help']) . '</code>'; ?></div>
              <?php } elseif ($f['type'] === 'legend') { ?>
              <legend><?php echo $e($f['label']); ?></legend>
              <?php if ($f['help']) { ?><p class="help-block"><?php echo $e($f['help']); ?></p><?php } ?>
              <?php } else { ?>
              <div class="form-group">
                <label class="col-sm-3 control-label" for="input-<?php echo $f['key']; ?>"><?php if ($f['help']) { ?><span data-toggle="tooltip" title="<?php echo $e($f['help']); ?>"><?php echo $e($f['label']); ?></span><?php } else { echo $e($f['label']); } ?></label>
                <div class="col-sm-9">
                  <?php if ($f['type'] === 'select') { ?>
                  <select name="<?php echo $f['name']; ?>" id="input-<?php echo $f['key']; ?>" class="form-control">
                    <?php foreach ($f['options'] as $value => $label) { ?>
                    <option value="<?php echo $e($value); ?>"<?php echo (string)$value === (string)$f['value'] ? ' selected="selected"' : ''; ?>><?php echo $e($label); ?></option>
                    <?php } ?>
                  </select>
                  <?php } elseif ($f['type'] === 'textarea') { ?>
                  <textarea name="<?php echo $f['name']; ?>" id="input-<?php echo $f['key']; ?>" rows="6" class="form-control" style="font-family:monospace"><?php echo $e($f['value']); ?></textarea>
                  <?php if ($f['help']) { ?><p class="help-block"><?php echo $e($f['help']); ?></p><?php } ?>
                  <?php } elseif ($f['type'] === 'secret') { ?>
                  <input type="password" name="<?php echo $f['name']; ?>" id="input-<?php echo $f['key']; ?>" value="" autocomplete="new-password" class="form-control"<?php if ($f['key'] === 'api_key' ? $key_set : $secret_set) { ?> placeholder="<?php echo $e($t['text_api_key_set']); ?>"<?php } ?> />
                  <?php } else { ?>
                  <input type="text" name="<?php echo $f['name']; ?>" id="input-<?php echo $f['key']; ?>" value="<?php echo $e($f['value']); ?>" class="form-control" />
                  <?php } ?>
                </div>
              </div>
              <?php } ?>
              <?php } ?>

              <?php if ($tab === 'connection') { ?>
              <div class="form-group">
                <label class="col-sm-3 control-label" for="input-webhook-url"><span data-toggle="tooltip" title="<?php echo $e($t['help_webhook_url']); ?>"><?php echo $e($t['entry_webhook_url']); ?></span></label>
                <div class="col-sm-9"><input type="text" id="input-webhook-url" value="<?php echo $e($webhook_url); ?>" readonly="readonly" class="form-control" onclick="this.select()" /><p class="help-block"><?php echo $e($webhook_url); ?></p></div>
              </div>
              <div class="form-group">
                <div class="col-sm-9 col-sm-offset-3">
                  <button type="button" id="neocarts-check" class="btn btn-default"><i class="fa fa-plug"></i> <?php echo $e($t['button_check']); ?></button>
                  <a href="<?php echo $e($admin_url); ?>" target="_blank" rel="noopener" class="btn btn-link"><i class="fa fa-external-link"></i> <?php echo $e($t['button_neocarts_admin']); ?></a>
                  <div id="neocarts-check-result" style="margin-top:10px"></div>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-3 control-label"><?php echo $e($t['text_version']); ?></label>
                <div class="col-sm-9"><p class="form-control-static"><?php echo $e($version); ?></p></div>
              </div>
              <?php } ?>
            </div>
            <?php $first = false; } ?>
          </div>
        </form>
        <?php echo $support_footer; ?>
      </div>
    </div>
  </div>
</div>
<script type="text/javascript"><!--
(function () {
  function say(target, ok, text) {
    $(target).html($('<div class="alert"></div>').addClass(ok ? 'alert-success' : 'alert-danger').text(text));
  }
  $('#neocarts-check').on('click', function () {
    var button = $(this).prop('disabled', true);
    $.getJSON(<?php echo json_encode(html_entity_decode($check, ENT_QUOTES, 'UTF-8')); ?>)
      .done(function (json) { say('#neocarts-check-result', json.ok, json.message); })
      .fail(function (xhr) { say('#neocarts-check-result', false, (xhr.responseJSON && xhr.responseJSON.message) || xhr.statusText); })
      .always(function () { button.prop('disabled', false); });
  });
})();
//--></script>
<?php echo $footer; ?>
