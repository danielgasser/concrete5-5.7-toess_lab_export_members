<?php defined('C5_EXECUTE') or die("Access Denied.");
$fh = \Core::make('helper/concrete/asset_library');
$form = \Core::make('helper/form');
?>
<script>
(function ($) {
    "use strict";
    $(document).ready(function () {
        window.ConcreteEvent.bind('FileManagerBeforeSelectFile', function (e, fdata) {
            if (fdata.hasOwnProperty('fID')) {
                $('#import_csv_go').attr('disabled', false);
            } else {
                $('#import_csv_go').attr('disabled', true);
            }
        });
    });
}(jQuery));
</script>
<script>

</script>
<div class="alert alert-info" id="dialog_csv_error" style="display: none">
    <button type="button" class="close" data-dismiss="alert">×</button>
    <div id="dialog_csv_error_msg"></div>
</div>

<div class="clearfix">
    <form id="import_csv" role="form" method="post" action="<?php    print $controller->action('get_csv_file')?>" class="form-horizontal" novalidate>
        <div class="row">
            <div class="col-lg-6">
                <?php echo $form->label('fID', t('Choose CSV-File to import'))?>
                <?php echo $fh->file('ccm-b-file', 'fID', t('Choose File'));?>
            </div>
            <div class="col-lg-6">
                <?php echo $form->label('import_csv_go', t('Import now'))?><br>
                <button style="width: 100%;" id="import_csv_go" disabled name="import_csv_go" class="btn btn-primary" type="submit" ><?php    echo t('Import')?></button>
            </div>
        </div>
    </form>
</div>
