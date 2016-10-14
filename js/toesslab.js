(function ($) {
    "use strict";
    var setDefaultCSvValues = function () {
            $.each($('[id^="csv-"]'), function (i, n) {
                if (n.value === '' && $(n).attr('id') !== 'csv-filename') {
                    $(n).val(window.csvSettings[$(n).attr('id')]);
                }
            });
        },saveCSVSettings = function (data) {
            $.ajax({
                method: 'GET',
                url: window.save_csv_settings,
                data: {
                    csvData: data
                }
            });
        },
        searchUsers = function (search) {
            var user_list,
                user_list_body = $('#userList');
            $.ajax({
                method: 'GET',
                url: window.user_search_url,
                data: {
                    keyWord: search
                },
                success: function (data) {
                    var dats = $.parseJSON(data);
                    user_list = dats.res;
                    user_list_body.html('');
                    fillUserTable(user_list, dats.res_count, user_list_body);
                }
            })
        },
        setMessages = function (msg, error) {
            if ($('#dialog_csv_error').length === 0) {
                $('.clearfix').prepend('<div class="alert alert-info" id="dialog_csv_error" style="display: none">' +
                    '<button type="button" class="close" data-dismiss="alert">×</button>' +
                    '<div id="dialog_csv_error_msg"></div></div>')
            }
            var csvMessage = $('#dialog_csv_error'),
                csvMessageText = $('#dialog_csv_error_msg'),
                isError = (error) ? 'danger' : 'info';
            if (error === undefined) {
                csvMessage
                    .hide();
                return false;
            }
            csvMessage
                .removeClass('alert-danger')
                .removeClass('alert-info')
                .addClass('alert-' + isError);
            csvMessageText.html(msg);
            csvMessage
                .show();
        },
        exportUsers = function (ids, baseCols, cols, cp, ug) {
            $.ajax({
                method: 'POST',
                url: window.user_export_url,
                data: {
                    ids: ids,
                    baseColumns: baseCols,
                    columns: cols,
                    usersGroups: ug,
                    communityPoints: cp,
                    csv_filename: $('#csv-filename').val()
                },
                success: function (data) {
                    var dats = $.parseJSON(data);
                    if(dats.hasOwnProperty('error')) {
                        setMessages(dats.error, true);
                    } else {
                        setMessages(dats.success, false);
                    }
                }
            })
        },
        checkCkecked = function (check) {
            var count = parseInt($('#numExportRecs').html(), 10);

            if (check) {
                count += 1;
            } else {
                count -= 1;
            }
            $('#numExportRecs').html(count);
        },
        getUsers = function (prop, order_by) {
            var user_group = [],
                checked = [],
                user_list,
                user_list_body = $('#userList'),
                prop_object = $('[data-prop="' + prop + '"]');
            $.each($('input[name^="chooseUserGroup"]'), function (i, n) {
                if (n.checked) {
                    user_group.push(n.value);
                }
            });
            $.each($('input[name="uID[]"]'), function (i, n) {
                if (n.checked) {
                    checked.push(n.value);
                }
            });
            if (order_by === 'asc') {
                prop_object.parent().removeClass('ccm-results-list-active-sort-asc');
                prop_object.parent().addClass('ccm-results-list-active-sort-desc');
                prop_object.attr('data-sort', 'desc');
                $('#isSortedBy').val('desc');
            } else {
                prop_object.parent().removeClass('ccm-results-list-active-sort-desc');
                prop_object.parent().addClass('ccm-results-list-active-sort-asc');
                prop_object.attr('data-sort', 'asc');
                $('#isSortedBy').val('asc');
            }
            $.ajax({
                type: 'GET',
                url: window.user_group_url,
                data: {
                    group_id: user_group,
                    adminInc: $('input[name^="adminInc"]').is(':checked'),
                    was_checked: checked
                },
                success: function (data) {
                    if (data === 'null') {
                        user_list_body.html('');
                        return false;
                    }
                    var dats = $.parseJSON(data)
                    user_list = dats.res;
                    if (user_list.length === 0) {
                        user_list_body.html('');
                        return false;
                    }
                    sortResults(prop, order_by, user_list);
                    user_list_body.html('');
                    fillUserTable(user_list, dats.res_count, user_list_body);
                }
            });
        },
        fillUserTable = function (data, c, el) {
            var numRecsExport = 0;
            $.each(data, function (i, n) {
                var str = '',
                    is_checked = (n.isChecked || $('[data-search-checkbox="select-all"]').is(':checked')) ? 'checked="checked"' : '';
                str += '<tr class="' + i + '">' +
                    '<td class="col-lg-1 col-md-1 col-sm-1 col-xs-12"><input type="checkbox" id="uID_' + n.uID + '" name="uID[]" class="ccm-flat-checkbox" value="' + n.uID + '" ' + is_checked + '>';
                str += '</td>';
                str += '<td class="col-lg-1 col-md-1 col-sm-1 col-xs-12">' + n.uName + '</td>';
                str += '<td class="col-lg-3 col-md-3 col-sm-3 col-xs-12"><a href="mailto:' + n.uEmail + '">' + n.uEmail + '</a></td>';
                str += '<td class="col-lg-3 col-md-3 col-sm-3 col-xs-12">' + n.uDateAdded + '</a></td>';
                str += '<td class="col-lg-3 col-md-3 col-sm-3 col-xs-12">' + n.uNumLogins + '</a></td>';
                str += '</tr>';
                el.append(str);
                $('#numRecs').html(c);
                if (n.isChecked) {
                    numRecsExport += 1;
                }
                $('#numExportRecs').html(numRecsExport);
            });
        },
        sortResults = function (prop, asc, data) {
            data.sort(function (a, b) {
                if (asc === 'asc') {
                    return (a[prop] > b[prop]) ? 1 : ((a[prop] < b[prop]) ? -1 : 0);
                }
                return (b[prop] > a[prop]) ? 1 : ((b[prop] < a[prop]) ? -1 : 0);
            });
        },
        checkStates = function () {
            var allStates = 0;
            $.each($('input[name^="chooseUserGroup"]'), function (i, n) {
                if ($(n).is(':checked')){
                    allStates += 1;
                }
            });
            if (allStates === 0) {
                $('input[name^="adminInc"]').bootstrapSwitch('state', false);
            }
        };

    $(document).ready(function () {
        getUsers('uName', 'asc');
    });
    $(document).on('change', '[name="uID[]"]', function () {
        checkCkecked($(this).is(':checked'))
    });
    $(document).on('change', '[data-search-checkbox="select-all"]', function () {
        $.each($('[name="uID[]"]'), function (i, n) {
            checkCkecked($(n).is(':checked'))
        });
    });
    $(document).on('click', '#exportNow', function () {
        var uIDs,
            basecColumns,
            columns,
            usersGroups = $('[name="userGroup"]').is(':checked'),
            communityPoints = $('[name="communityPoints"]').is(':checked');
        if (parseInt($('#numRecs').html()) === 0 || parseInt($('#numExportRecs').html()) === 0) {
            setMessages(window.warning_no_members, true);
            return false;
        }
        setDefaultCSvValues();
        uIDs = $('[name="uID[]"]').map(function (i, n) {
            if ($(n).is(':checked')) {
                return $(n).val();
            }
        }).get();
        basecColumns = $('[name="chooseBaseColumns[]"]').map(function (i, n) {
            if ($(n).is(':checked')) {
                return $(n).attr('data-handle');
            }
        }).get();
        columns = $('[name="chooseColumns[]"]').map(function (i, n) {
            if ($(n).is(':checked')) {
                return $(n).attr('data-handle');
            }
        }).get();
        exportUsers(uIDs, basecColumns, columns, communityPoints, usersGroups);
    });
    $(document).on('keyup', '#user_search', function (e) {
        e.preventDefault();
        var search = $(this).val();
        searchUsers(search);
    });
    $(document).on('change blur', '[id^="csv-"]', function (e) {
        var val = $(this).val(),
            csvData = $('[id^="csv-"]').map(function (i, n) {
                    return {
                        handle: $(n).attr('id'),
                        val: $(n).val(),
                        name: $(n).prev('label').text()
                    }
            }).get();
        e.preventDefault();
        if ($(this).attr('id') === 'csv-filename' && val.length <= 0) {
            setMessages(window.warning_csv_file, true);
            return false;
        }
        setDefaultCSvValues();
        saveCSVSettings(csvData);
    });
    $(document).on('click', '[data-search-checkbox="select-all"]', function () {
        var checkboxes = $('[name^="uID"]');
        checkboxes.prop('checked', !checkboxes.prop('checked'));
    });
    $(document).on('click', '.sort_it', function (e) {
        e.preventDefault();
        $(this).attr('data-is-sorted', 1);
        $('#isSorted').val($(this).attr('data-prop'));
        getUsers($(this).attr('data-prop'), $(this).attr('data-sort'));
    });
    /**
     * Changes the user group
     */
    $(document).on('switchChange.bootstrapSwitch', 'input[name^="chooseUserGroup"]', function (event, state) {
        var prop = 'uName',
            by = 'asc';
        if ($(this).attr('data-first') === '1') {
            if (state) {
                $('input[name^="chooseUserGroup"][data-first!="1"]').bootstrapSwitch('state', false);
                $('input[name^="adminInc"]').bootstrapSwitch('state', false);
            }
        } else {
            if (state) {
                $('input[name^="chooseUserGroup"][data-first="1"]').bootstrapSwitch('state', false);
            }
        }
        if ($('[name="isSorted"]').val() !== '') {
            prop = $('[name="isSorted"]').val();
            by = $('[name="isSortedBy"]').val();
        }
        checkStates();
        getUsers(prop, by);
    });
    $(document).on('switchChange.bootstrapSwitch', 'input[name^="adminInc"]', function (event, state) {
        var prop = 'uName',
            by = 'asc';
        if (state) {
            $('input[name^="chooseUserGroup"][data-first="1"]').bootstrapSwitch('state', false);
        }
        if ($('[name="isSorted"]').val() !== '') {
            prop = $('[name="isSorted"]').val();
            by = $('[name="isSortedBy"]').val();
        }
        checkStates();
        getUsers(prop, by);
    });
}(jQuery));
