/**
 * Javascript containing function of the sirh enrol admin
 */

define([
    'jquery',
    'format_edadmin/format_edadmin',
    'local_mentor_core/mentor',
    'local_mentor_core/select2',
    'local_mentor_core/cookie',
    'local_mentor_core/url',
    'jqueryui',
    'local_mentor_core/datatables'
], function ($, format_edadmin, mentor) {
    var enrol_sirh = {
        /**
         * Init JS
         */
        init: function (sessionid, managesessionsurl) {
            this.sessionid = sessionid;
            this.managesessionsurl = managesessionsurl;

            var that = this;

            that.initTable();

            // Submit form.
            $('#filter-button').click(function (event) {
                event.preventDefault();
                that.table.ajax.reload();
            });

            // Reset form.
            $('#reset-filter-button').click(function () {
                event.preventDefault();
                $('#sirh-filter-form').get(0).reset();
                that.table.ajax.reload();
            });

        },
        /**
         * Initialise the table as a dataTable
         */
        initTable: function () {
            var that = this;

            that.table = $('#sessions-table').DataTable({
                ajax: {
                    url: M.cfg.wwwroot + '/enrol/sirh/ajax/ajax.php',
                    "dataSrc": function (json) {
                        if (typeof json.success != 'undefined') {
                            $('#sessions-table_wrapper').hide();
                            $('<div class="error">' + json.message + '</div>').insertBefore('#sessions-table_wrapper');
                            return;
                        } else {
                            $('#sessions-table_wrapper').show();
                            $('.error').remove();
                            return json.data;
                        }
                    },
                    data: function (d) { // GET HTTP data setting.
                        d.controller = 'sirh';
                        d.plugintype = 'enrol';
                        d.action = 'get_sirh_sessions';
                        d.format = 'json';
                        d.sessionid = that.sessionid;
                        d.sirh = [$('#sirh').val()];
                        d.sirhtraining = $('#trainingsirh').val();
                        d.sirhsession = $('#sessionsirh').val();
                        d.sirhsessionname = $('#sessionlabelsirh').val();
                        d.sirhtrainingname = $('#traininglabelsirh').val();
                        d.datestart = $('#datestart').val();
                        d.dateend = $('#dateend').val();
                    }, error: function (xhr, error, code) {
                        console.log(xhr, code);
                    }
                },
                oLanguage: {
                    sUrl: M.cfg.wwwroot + '/local/mentor_core/datatables/lang/' + M.util.get_string('langfile', 'enrol_sirh') + ".json"
                },
                serverSide: true,//For use Ajax
                processing: true,
                pageLength: 50,
                ordering: true,
                order: [],
                dom: 'Blfrtip',
                fixedColumns: true,
                "searching": false,
                columns: [
                    {
                        data: function (data, type, row, meta) {
                            return data.sirhtraining;
                        }
                    },
                    {
                        data: function (data, type, row, meta) {
                            return data.sirhtrainingname;
                        }
                    },
                    {
                        data: function (data, type, row, meta) {
                            return data.sirhsession;
                        }
                    },
                    {
                        data: function (data, type, row, meta) {
                            return data.sirhsessionname;
                        }
                    },
                    {
                        data: function (data, type, row, meta) {
                            return data.startdate;
                        }
                    },
                    {
                        data: function (data, type, row, meta) {
                            return data.enddate;
                        }
                    },
                    {
                        data: function (data, type, row, meta) {

                            var url = M.cfg.wwwroot + '/enrol/sirh/pages/sync.php';

                            // Enrol SIRH instance exist.
                            if (data.instanceexists == true) {
                                return '<div class="session-actions"><span class="existing"><a href="' + url + '?sessionid=' + that.sessionid + '&instanceid=' + data.instanceid + '"><i title="' + M.util.get_string('reload', 'enrol_sirh') + '" class="fa fa-refresh"' + ' aria-hidden="true"></i></a></span></div>';
                            }

                            // Create button enrol SIRH instance.
                            return '<div class="session-actions"><form action="' + url + '?sessionid=' + that.sessionid + '" method="POST"><button type="submit"' +
                                ' class="create-instance-button' +
                                ' btn-link"><i title="' + M.util.get_string('viewenrol', 'enrol_sirh') + '"' +
                                'class="fa' +
                                ' fa-handshake-o"' +
                                ' aria-hidden="true"></i></button><input' +
                                ' type="hidden" name="sirh" value="' + data.sirh + '" /><input' +
                                ' type="hidden" name="sirhtraining" value="' + data.sirhtraining + '" /><input' +
                                ' type="hidden" name="sirhsession" value="' + data.sirhsession + '" /></form></div>';
                        }
                    }
                ]
            });
        }
    };

    // Add object to window to be called outside require.
    window.enrol_sirh = enrol_sirh;
    return enrol_sirh;
});
