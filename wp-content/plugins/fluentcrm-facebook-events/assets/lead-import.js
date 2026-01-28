/* global FcrmFbLeadImport */
(function ($) {
    'use strict';

    function updateStatus($status, type, message) {
        $status.removeClass('notice-success notice-error notice-info');
        if (type) {
            $status.addClass(type);
        }
        $status.find('p').text(message);
        $status.show();
    }

    function setProgress($progress, percent, message) {
        $progress.find('.fcrm-fb-lead-import-progress-bar span').css('width', percent + '%');
        if (message) {
            $progress.find('.fcrm-fb-lead-import-message').text(message);
        }
    }

    $(function () {
        var $form = $('#fcrm-fb-lead-import-form');
        if (!$form.length || typeof FcrmFbLeadImport === 'undefined') {
            return;
        }

        var $status = $('#fcrm-fb-lead-import-status');
        var $progress = $('#fcrm-fb-lead-import-progress');

        $form.on('submit', function (event) {
            event.preventDefault();

            $status.hide();
            $progress.show();
            setProgress($progress, 15, FcrmFbLeadImport.loadingText);

            var data = $form.serializeArray();
            data.push({ name: 'action', value: 'fcrm_fb_events_import_leads_ajax' });
            data.push({ name: 'nonce', value: FcrmFbLeadImport.nonce });

            $.post(FcrmFbLeadImport.ajaxUrl, $.param(data))
                .done(function (response) {
                    if (response && response.success) {
                        setProgress($progress, 100, response.data.message || FcrmFbLeadImport.doneText);
                        updateStatus($status, 'notice-success', response.data.message || FcrmFbLeadImport.doneText);
                    } else {
                        setProgress($progress, 100, FcrmFbLeadImport.errorText);
                        updateStatus(
                            $status,
                            'notice-error',
                            (response && response.data && response.data.message) ? response.data.message : FcrmFbLeadImport.errorText
                        );
                    }
                })
                .fail(function () {
                    setProgress($progress, 100, FcrmFbLeadImport.errorText);
                    updateStatus($status, 'notice-error', FcrmFbLeadImport.errorText);
                })
                .always(function () {
                    setTimeout(function () {
                        $progress.hide();
                    }, 800);
                });
        });
    });
})(jQuery);
