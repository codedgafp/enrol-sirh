/**
 * Javascript containing function of the sync sirh enrol admin
 */

define([
    'jquery',
    'jqueryui',
    'local_mentor_core/mentor'
], function ($, ui, mentor) {
    var sync_sirh = {
        /**
         * Init JS
         */
        init: function (modaltitle, modalcontent) {
            var that = this;

            if ($('#table-read-more').length) {
                that.hiddenUserTable();

                $('#table-read-more').click(function () {
                    if ($('#user-session-table').hasClass('user-hidden')) {
                        that.showUserTable();
                    } else {
                        that.hiddenUserTable();
                    }
                });
            }

            // Check if import button exist
            if ($('#sirh-sync-user').children().length) {
                $('#sirh-sync-user .mform').on('submit', function (eventForm) {

                    if (
                        $('#report-preview').data('creation-number') === 0 &&
                        $('#report-preview').data('reactivation-number') === 0
                    ) {
                        return;
                    }

                    eventForm.preventDefault();
                    mentor.dialog('<p class="text-justify">' + modalcontent + '</p>', {
                        width: 700,
                        title: modaltitle,
                        close: function () {
                            $(this).dialog("destroy");
                        },
                        buttons: [
                            {
                                // Cancel button
                                text: M.util.get_string('no', 'moodle'),
                                class: "btn btn-secondary",
                                click: function () {//Just close the modal
                                    $(this).dialog("destroy");
                                }
                            },
                            {
                                // Confirm button
                                text: M.util.get_string('yes', 'moodle'),
                                id: 'confirm-form-import-csv',
                                class: "btn btn-primary",
                                click: function () {
                                    eventForm.target.submit();
                                }
                            },
                        ]
                    });

                });
            }
        },
        /**
         * Hide excess display users.
         */
        hiddenUserTable: function () {
            $('#user-session-table').find('tr').each(function (index, element) {
                if (index >= 6) {
                    $(element).addClass('hidden');
                }
            });
            $('#user-session-table').removeClass('user-show');
            $('#user-session-table').addClass('user-hidden');
            $('#table-read-more').html(M.util.get_string('showmore', 'enrol_sirh'));
            $('#gradientmask').removeClass('hide');
        },
        /**
         * Show excess display users.
         */
        showUserTable: function () {
            $('#user-session-table').find('tr').each(function (index, element) {
                if (index >= 6) {
                    $(element).removeClass('hidden');
                }
            });
            $('#user-session-table').removeClass('user-hidden');
            $('#user-session-table').addClass('user-show');
            $('#table-read-more').html(M.util.get_string('showless', 'enrol_sirh'));
            $('#gradientmask').addClass('hide');
        }
    };

    // Add object to window to be called outside require.
    window.sync_sirh = sync_sirh;
    return sync_sirh;
});
