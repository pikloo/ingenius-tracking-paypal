<?php

/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       http://example.com
 * @since      1.0.0
 *
 * @package    Ingenius_Tracking_Paypal
 * @subpackage Ingenius_Tracking_Paypal/admin/partials
 */
?>

<div class="wrap" id="itp-sync-wrapper">
    <h1><?php esc_html_e('Synchronisation des suivis PayPal', 'ingenius-tracking-paypal'); ?></h1>
    <p class="description">
        <?php esc_html_e('Envoyez ou renvoyez en masse les numéros de suivi vers PayPal pour les commandes PayPal éligibles.', 'ingenius-tracking-paypal'); ?>
    </p>

    <?php
    $pending = isset($stats['pending']) ? (int) $stats['pending'] : 0;
    $sent = isset($stats['sent']) ? (int) $stats['sent'] : 0;
    $total = isset($stats['total']) ? (int) $stats['total'] : 0;
    $button_label = __('Synchroniser maintenant', 'ingenius-tracking-paypal');
    $running_text = __('Synchronisation en cours…', 'ingenius-tracking-paypal');
    ?>

    <div
        id="itp-sync-app"
        class="itp-sync-app"
        data-nonce="<?php echo esc_attr($nonce); ?>"
        data-pending="<?php echo esc_attr($pending); ?>"
        data-batch="<?php echo esc_attr($batch_size); ?>"
        data-label="<?php echo esc_attr($button_label); ?>"
        data-running-text="<?php echo esc_attr($running_text); ?>"
        data-running="<?php echo esc_attr__('Veuillez patienter pendant la synchronisation…', 'ingenius-tracking-paypal'); ?>"
        data-error="<?php echo esc_attr__('Une erreur est survenue pendant la synchronisation.', 'ingenius-tracking-paypal'); ?>"
        data-success-template="<?php echo esc_attr__('%d commandes synchronisées.', 'ingenius-tracking-paypal'); ?>"
    >
        <div class="itp-sync-stats">
            <div class="itp-sync-card">
                <span class="itp-sync-card__label"><?php esc_html_e('En attente', 'ingenius-tracking-paypal'); ?></span>
                <span class="itp-sync-card__value" data-itp-stat="pending"><?php echo esc_html($pending); ?></span>
            </div>
            <div class="itp-sync-card">
                <span class="itp-sync-card__label"><?php esc_html_e('Envoyés', 'ingenius-tracking-paypal'); ?></span>
                <span class="itp-sync-card__value" data-itp-stat="sent"><?php echo esc_html($sent); ?></span>
            </div>
            <div class="itp-sync-card">
                <span class="itp-sync-card__label"><?php esc_html_e('Total suivis', 'ingenius-tracking-paypal'); ?></span>
                <span class="itp-sync-card__value" data-itp-stat="total"><?php echo esc_html($total); ?></span>
            </div>
        </div>

        <div class="itp-sync-actions">
            <button
                id="itp-sync-button"
                class="button button-primary button-hero"
                <?php echo 0 === $pending ? 'disabled' : ''; ?>
                type="button"
            >
                <?php echo esc_html($button_label); ?>
            </button>
            <span class="spinner" aria-hidden="true"></span>
            <p class="description itp-sync-empty-hint" <?php echo 0 === $pending ? '' : 'style="display:none;"'; ?>>
                <?php esc_html_e('Toutes les commandes disponibles sont déjà synchronisées.', 'ingenius-tracking-paypal'); ?>
            </p>
            <p class="description">
                <?php esc_html_e('Seules les commandes PayPal (PPCP) possédant un numéro de suivi seront traitées.', 'ingenius-tracking-paypal'); ?>
            </p>
        </div>

        <div id="itp-sync-log" class="notice" style="display:none;"></div>
    </div>
</div>
