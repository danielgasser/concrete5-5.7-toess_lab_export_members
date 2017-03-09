(function ($) {
    "use strict";
    var setDefaultCSvValues = function () {
            $.each($('[id^="csv-"]'), function (i, n) {
                $(n).val(window.csvSettings[$(n).attr('id')]);
            });
        },
        saveExportSettings = function (data) {
            $.ajax({
                method: 'GET',
                url: window.save_export_settings,
                data: {
                    exportSettings: data
                },
                success: function (data) {
                    $('#test').html(data)
                    var dats = $.parseJSON(data);
                    if(dats.hasOwnProperty('error')) {
                        setMessages(dats.error, true);
                    } else {
                        setMessages(dats.success, false);
                    }
                }
            });
        },
        saveCSVSettings = function (data) {
            $.ajax({
                method: 'GET',
                url: window.save_csv_settings,
                data: {
                    csvData: data
                },
                success: function (data) {
                    var dats = $.parseJSON(data);
                    $.each(dats, function (i, n) {
                        console.log(i, n);
                        $('#' + i).val(n);
                    })
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
        progressed = function () {
            var xhr = new window.XMLHttpRequest();
            xhr.upload.addEventListener("progress", function(evt){
                if (evt.lengthComputable) {
                    var percentComplete = evt.loaded / evt.total;
                    //Do something with upload progress
                    console.log(percentComplete);
                }
            }, false);
            xhr.addEventListener("progress", function(evt){
                if (evt.lengthComputable) {
                    var percentComplete = evt.loaded / evt.total;
                    //Do something with download progress
                    console.log(percentComplete);
                }
            }, false);
            return xhr;
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
             //   xhr: progressed(),
                method: 'POST',
                url: window.user_export_url,
                data: {
                    ids: ids,
                    baseColumns: baseCols,
                    columns: cols,
                    usersGroups: ug,
                    communityPoints: cp,
                    csv_filename: $('#csv_filename').val()
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
        checkChecked = function () {
            var count = 0;
            $.each($('[name="uID[]"]'), function (i, n) {
                if ($(n).is(':checked')) {
                    count += 1;
                }
            });
            $('#numExportRecs').html(count);
            if (count === 0) {
                $('#exportNow').prop('disabled', true);
            } else {
                $('#exportNow').prop('disabled', false);
            }
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
                str += '<td class="col-lg-1 col-md-1 col-sm-1 col-xs-12"><a href="' + window.base_url + '/dashboard/users/search/view/' + n.uID + '">' + n.uName + '</a></td>';
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
                $('input[name^="chooseUserGroup"][data-first="1"]').bootstrapSwitch('state', true);
            }
        },
        checkAllStates = function (toCheck, toSwitch) {
            var counter = 0;
            $.each($(toCheck), function (i, n) {
                if ($(n).is(':checked')) {
                    counter += 1;
                }
            });
            if (counter === $(toCheck).length) {
                $(toSwitch).bootstrapSwitch('state', true);
            } else {
                $(toSwitch).bootstrapSwitch('state', false);
            }
        };

    $(document).ready(function () {
        getUsers('uName', 'asc');
        $('.bootstrap-switch-id-adminInc').hide();
        $('#exportNow').prop('disabled', true);
        checkAllStates('[id^="chooseBaseColumns_"]', '#chooseAll_BaseColumns')
        checkAllStates('[id^="chooseColumns_"]', '#chooseAll_Columns')
    });
    $(document).on('change', '[name="uID[]"]', function () {
        if (!$(this).is(':checked')) {
            $('[data-search-checkbox="select-all"]').attr('checked', false);
        }
        checkChecked();
    });
    $(document).on('change', '[data-search-checkbox="select-all"]', function () {
        if ($(this).is(':checked')) {
            $.each($('[name="uID[]"]'), function (i, n) {
                $(n).prop('checked', true);
            });
        } else {
            $.each($('[name="uID[]"]'), function (i, n) {
                $(n).prop('checked', false);
            });
        }
        checkChecked();
    });
    $(document).on('click', '#save_export_settings', function (e) {
        e.preventDefault();
        var attr = $('.toesslab-attributes').find('input'),
            csvSet = attr.map(function (i, n) {
            return {
                handle: $(n).attr('data-handle'),
                val: $(n).is(':checked')
            }
        }).get();
        saveExportSettings(csvSet);
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
        var forbiddenChar = '*',
            csvData = $('[id^="csv-"]').map(function (i, n) {
                var value = $(n).val();
                return {
                    handle: $(n).attr('id'),
                    val: (value.length === 0 || value.length > 1 || value === forbiddenChar) ? window.csvSettings[$(n).attr('id')] : value,
                    name: $(n).prev('label').text()
                }
            }).get();
        e.preventDefault();
        saveCSVSettings(csvData);
    });
    $(document).on('change blur', '#csv_filename', function (e) {
        var val = $(this).val(),
            csvData = $(this).map(function (i, n) {
                    return {
                        handle: $(n).attr('id'),
                        val: $(n).val(),
                        name: $(n).prev('label').text()
                    }
            }).get();
        e.preventDefault();
        if (val.indexOf('.') > -1) {
            val = val.substring(0, val.indexOf('.'));
        }
        val = val.replace(/\W+/g, '');
        if (val.length === 0) {
            setMessages(window.warning_csv_file, true);
            $(this).val(window.csvSettings[$(this).attr('id')]);

        } else {

            $(this).val(val);
        }
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
        if ($(this).attr('id') === 'chooseUserGroup_3') {
            if (state) {
                $('.bootstrap-switch-id-adminInc').show();
            } else {
                $('.bootstrap-switch-id-adminInc').hide();
            }
        }
        if ($(this).attr('data-first') === '1') {
            if (state) {
                $('input[name^="chooseUserGroup"][data-first!="1"]').bootstrapSwitch('state', false);
                $('.bootstrap-switch-id-adminInc').hide();
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
    $(document).on('switchChange.bootstrapSwitch', 'input[name^="chooseAll_"]', function (event, state) {
        var id = $(this).attr('name').split('_')[1];
        $('[name^="choose' + id + '"]').bootstrapSwitch('state', state);
    });
    $(document).on('switchChange.bootstrapSwitch', 'input[id^="chooseBaseColumns_"]', function (event, state) {
        var id = $(this).attr('id').split('_')[0];
        checkAllStates('[id^="' + id + '_"]', '[name="chooseAll_BaseColumns"]')
    });
    $(document).on('switchChange.bootstrapSwitch', 'input[id^="chooseColumns_"]', function (event, state) {
        var id = $(this).attr('id').split('_')[0];
        checkAllStates('[id^="' + id + '_"]', '#chooseAll_Columns')
    });
}(jQuery));
