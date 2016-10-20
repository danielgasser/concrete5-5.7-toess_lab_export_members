<?php defined('C5_EXECUTE') or die("Access Denied.");
?>
<script>
    var user_group_url = '<?php    echo $this->action('change_user_group')?>',
        user_export_url = '<?php    echo $this->action('export_to_csv')?>',
        save_csv_settings = '<?php    echo $this->action('save_csv_settings')?>',
        warning_csv_file = '<?php echo t('Please enter a CSV-Filename.') ?>',
        warning_title = '<?php    echo t('Warning') ?>',
        csvSettings = <?php echo $csvSettingsJSON ?>,
        warning_no_members = '<?php    echo t('Please select some Users to export.') ?>',
        user_search_url = '<?php    echo $this->action('search_users')?>';
</script>
<script>
    (function ($) {
        "use strict";
        jQuery(document).ready(function () {
            $('input[name^="chooseUserGroup"], input[name^="chooseColumns"], input[name^="chooseBaseColumns"], input[name="communityPoints"], input[name="userGroup"], input[name="adminInc"]').bootstrapSwitch({
                labelWidth: '200'

            });
        });
    }(jQuery));

</script>
<div class="alert alert-info" id="dialog_csv_error" style="display: none">
    <button type="button" class="close" data-dismiss="alert">×</button>
    <div id="dialog_csv_error_msg"></div>
</div>

<div class="clearfix">
    <div class="row">
        <div class="col-lg-6 col-md-6 col-sm-6 col-xs-6">
            <h3><?php echo t('Total records') ?>: <span id="numRecs">0</span></h3>
        </div>
        <div class="col-lg-6 col-md-6 col-sm-6 col-xs-6">
            <h3><?php echo t('Number of records to export') ?>: <span id="numExportRecs">0</span></h3>
            <button class="btn btn-primary" id="exportNow">Export to CSV</button>
        </div>
    </div>
    <hr>
    <div class="row">
        <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
            <h3><?php echo t('CSV-Settings') ?></h3>
        </div>
        <div class="col-lg-3 col-md-3 col-sm-3 col-xs-3">
            <?php
            echo $form->label('csv-filename', t('CSV-Filename (without extension)')) ?>
            <div class="input-group">
                <?php
                echo $form->text('csv-filename', $csvSettings['csv-filename'])
                ?>
                <span class="input-group-addon">.csv</span>
            </div>
        </div>
        <div class="col-lg-3 col-md-3 col-sm-3 col-xs-3">
            <?php
            echo $form->label('csv-delimiter', t('CSV-Delimiter')) ?>
            <?php
            echo $form->text('csv-delimiter', $csvSettings['csv-delimiter'], array('pattern' => '.{1}'))
            ?>
        </div>
        <div class="col-lg-3 col-md-3 col-sm-3 col-xs-3">
            <?php
            echo $form->label('csv-enclosure', t('CSV-Enclosure')) ?>
            <?php
            echo $form->text('csv-enclosure', $csvSettings['csv-enclosure'], array('pattern' => '.{1}'))
            ?>
        </div>
        <div class="col-lg-3 col-md-3 col-sm-3 col-xs-3">
            <?php
            echo $form->label('csv-escape', t('CSV-Escape')) ?>
            <?php
            echo $form->text('csv-escape', $csvSettings['csv-escape'], array('pattern' => '.{1}'))
            ?>
        </div>
    </div>
    <hr>
    <div class="row toesslab-attributes">
        <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
            <h3><?php echo t('Basic User Attributes to export') ?></h3>
            <div class="row">
                <?php
                $i = 0;
                foreach ($columns['baseAttributes'] as $key => $ps) {
                    if ($i % 3 == 0) {
                        echo '<div class="col-lg-3 col-md-3 col-sm-3 col-xs-3">';
                    }
                    echo $form->checkbox('chooseBaseColumns[]', $ps['akHandle'], true, array('data-label-text' => $ps['akName'], 'data-size' => 'normal', 'data-first' => ($i == 0), 'data-handle' => $ps['akHandle']));
                    echo '<br>';
                    $i++;
                    if ($i % 3 == 0 && $i > 0) {
                        echo '</div>';
                    }
                }
                ?>
            </div>
        </div>
    </div>
    <hr>
    <div class="row toesslab-attributes">
        <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
            <h3><?php echo t('User Attributes to export') ?></h3>
            <div class="row">
                <?php
                $i = 0;
                foreach ($columns['attributes'] as $key => $ps) {
                    if ($i % 3 == 0) {
                        echo '<div class="col-lg-3 col-md-3 col-sm-3 col-xs-3">';
                    }
                    echo $form->checkbox('chooseColumns[]', $ps['akHandle'], true, array('data-label-text' => $ps['akName'], 'data-size' => 'normal', 'data-first' => ($i == 0), 'data-handle' => $ps['akHandle']));
                    echo '<br>';
                    $i++;
                    if ($i % 3 == 0 && $i > 0) {
                        echo '</div>';
                    }
                }
                ?>
            </div>
            </div>
        </div>
    </div>
    <hr>
    <div class="row">
        <div class="col-lg-6 col-md-6 col-sm-6 col-xs-6">
            <h3><?php echo t('Export Community Points') ?></h3>
            <?php
            echo $form->checkbox('communityPoints', t('Community Points'), false, array('data-label-text' => t('Community Points'), 'data-size' => 'normal', 'data-first' => ($i == 0), 'data-handle' => 'uCommunityPoints'));
            ?>
        </div>
        <div class="col-lg-6 col-md-6 col-sm-6 col-xs-6">
            <h3><?php echo t('Export Users Groups') ?></h3>
            <?php
            echo $form->checkbox('userGroup', t('Users Groups'), false, array('data-label-text' => t('Users Groups'), 'data-size' => 'normal', 'data-first' => ($i == 0), 'data-handle' => 'uGroups'));
            ?>
        </div>
    </div>
    <hr>
    <div class="row">
        <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
            <h3><?php echo t('Filter by User Groups') ?></h3>
            <label class="control-label" for="chooseUserGroup" name="chooseUserGroup"><?php
                echo t('User group');
                ?>
            </label>
            <?php
            echo '<br>';
            ?>
            <div class="row">
                <?php
                $i = 0;
                foreach ($possibleGroups as $key => $ps) {
                    if ($i % 4 == 0) {
                        echo '<div class="col-lg-4 col-md-4 col-sm-4 col-xs-4">';
                    }
                    echo $form->checkbox('chooseUserGroup[]', $key, ($i == 0), array('data-label-text' => $ps, 'data-size' => 'normal', 'data-first' => ($i == 0)));
                    echo '<br>';
                    $i++;
                    if ($i % 4 == 0 && $i > 0) {
                        echo '</div>';
                    }
                }
                echo $form->checkbox('adminInc', 'adminInc', false, array('data-label-text' => t('Include Super-User (admin)'), 'data-size' => 'normal', 'data-first' => 'adminInc'));
                ?>

            </div>
        </div>
        <hr>
        <div class="row">
            <div class="col-lg-6 col-md-6 col-sm-6 col-xs-6">
                <h3><?php echo t('Filter by Username or Email') ?></h3>
                <i class="fa fa-search"></i>
                <input type="search" id="user_search" name="user_search" value="" placeholder="Username or Email"
                       class="form-control ccm-input-search">
            </div>
        </div>
        <hr>
        <div class="row">
            <div class="table-responsive" data-search-element="results">
                <table id="userRecipientList" class="table ccm-search-results-table" data-search="users">
                    <thead>
                    <tr>
                        <?php
                        echo $isSortedBy;
                        ?>
                        <th class="col-lg-1 col-md-1 col-sm-1 col-xs-12"><?php echo t('Select all') ?><input
                                type="checkbox" data-search-checkbox="select-all" class="ccm-flat-checkbox"></th>
                        <th class="col-lg-1 col-md-1 col-sm-1 col-xs-12 ccm-results-list-active-sort-asc"><a
                                href="#"
                                data-is-sorted=""
                                data-sort="asc"
                                class="sort_it"
                                data-prop="uName"><?php echo t('User name') ?></a>
                        </th>
                        <th class="col-lg-3 col-md-3 col-sm-3 col-xs-12 ccm-results-list-active-sort-asc"><a
                                href="#"
                                data-is-sorted=""
                                data-sort="asc"
                                class="sort_it"
                                data-prop="uEmail"><?php echo t('Email') ?></a>
                        </th>
                        <th class="col-lg-3 col-md-3 col-sm-3 col-xs-12 ccm-results-list-active-sort-asc"><a
                                href="#"
                                data-is-sorted=""
                                data-sort="asc"
                                class="sort_it"
                                data-prop="uDateAdded"><?php echo t('Signup Date') ?></a>
                        </th>
                        <th class="col-lg-3 col-md-3 col-sm-3 col-xs-12 ccm-results-list-active-sort-asc"><a
                                href="#"
                                data-is-sorted=""
                                data-sort="asc"
                                class="sort_it"
                                data-prop="uNumLogins"><?php echo t('Logins') ?></a>
                        </th>
                    </tr>
                    </thead>
                    <tbody id="userList">
                    <?php
                    if (sizeof($totalRecords) > 0) {
                        foreach ($users as $u) { ?>
                            <tr>
                                <td>
                                    <?php
                                    echo $form->checkbox('uID[]', $u->uID, true, array('data-label-text' => $u->uID, 'data-size' => 'normal', 'class' => 'ccm-flat-checkbox'));
                                    ?>
                                </td>
                                <td>
                                    <?php echo  '<a href="mailto:' . $u->uEmail . '">' . $u->uEmail . '</a>'; ?>
                                </td>
                            </tr>
                            <?php
                        }
                    }
                    ?>
                    </tbody>
                </table>
                <?php
                echo $form->hidden('isSorted', $isSorted);
                echo $form->hidden('isSortedBy', $isSortedBy);
                ?>
            </div>

        </div>
    </div>

</div>
