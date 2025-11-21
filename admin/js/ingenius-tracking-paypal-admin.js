(function($) {
    'use strict';

    $(function() {
        var $app = $('#itp-sync-app');

        if (!$app.length) {
            return;
        }

        var $button = $('#itp-sync-button');
        var $log = $('#itp-sync-log');
        var pending = parseInt($app.data('pending'), 10) || 0;
        var batch = parseInt($app.data('batch'), 10) || 10;
        var nonce = $app.data('nonce');
        var messages = {
            idle: $app.data('label'),
            running: $app.data('running-text'),
            info: $app.data('running'),
            error: $app.data('error'),
        };

        var successTemplate = $app.data('success-template');

        function updateStats(stats) {
            if (typeof stats.pending !== 'undefined') {
                pending = parseInt(stats.pending, 10) || 0;
                $app.attr('data-pending', pending);
                $app.find('[data-itp-stat="pending"]').text(pending);
            }

            if (typeof stats.sent !== 'undefined') {
                $app.find('[data-itp-stat="sent"]').text(parseInt(stats.sent, 10) || 0);
            }

            if (typeof stats.total !== 'undefined') {
                $app.find('[data-itp-stat="total"]').text(parseInt(stats.total, 10) || 0);
            }

            var $hint = $app.find('.itp-sync-empty-hint');

            if (pending > 0) {
                $hint.hide();
            } else {
                $hint.show();
            }
        }

        function toggleLoading(isLoading) {
            $app.attr('data-loading', isLoading ? '1' : '0');
            $button.prop('disabled', isLoading || pending === 0);
            $button.text(isLoading ? messages.running : messages.idle);
        }

        function showMessage(type, message) {
            $log.removeClass('notice-error notice-success notice-info')
                .addClass('notice notice-' + type)
                .text(message)
                .show();
        }

        function runBatch() {
            $.post(ajaxurl, {
                action: 'itp_sync_orders',
                nonce: nonce,
                batch: batch
            }).done(function(response) {
                if (!response || !response.success) {
                    var message = response && response.data && response.data.message ? response.data.message : messages.error;
                    showMessage('error', message);
                    toggleLoading(false);
                    return;
                }

                var data = response.data;
                updateStats(data.stats || {});
                var processedMessage = data.message || successTemplate.replace('%d', data.processed || 0);
                showMessage(data.processed > 0 ? 'success' : 'info', processedMessage);

                if (data.stats && data.stats.pending > 0) {
                    runBatch();
                    return;
                }

                toggleLoading(false);
            }).fail(function() {
                showMessage('error', messages.error);
                toggleLoading(false);
            });
        }

        function startSync() {
            showMessage('info', messages.info);
            toggleLoading(true);
            runBatch();
        }

        toggleLoading(false);

        $button.on('click', function(event) {
            event.preventDefault();

            if ($button.prop('disabled')) {
                return;
            }

            startSync();
        });
    });
})(jQuery);
