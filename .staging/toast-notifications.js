/**
 * Toast Notification System for Discord Embed Plugin
 * Replaces disruptive alert() pop-ups
 */

(function($) {
    'use strict';
    
    // Get localized string helper function
    function __l(key, fallback) {
        return (typeof discordEmbedL10n !== 'undefined' && discordEmbedL10n[key]) ? discordEmbedL10n[key] : fallback;
    }
    
    // Create toast container
    function createToastContainer() {
        if ($('#toast-container').length === 0) {
            $('body').append(`
                <div id="toast-container" style="
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    z-index: 999999;
                    max-width: 350px;
                "></div>
            `);
        }
    }
    
    // Show toast notification
    function showToast(message, type = 'info', duration = 4000) {
        createToastContainer();
        
        const types = {
            success: { icon: '✅', color: '#28a745', bg: '#d4edda' },
            error: { icon: '❌', color: '#dc3545', bg: '#f8d7da' },
            warning: { icon: '⚠️', color: '#ffc107', bg: '#fff3cd' },
            info: { icon: 'ℹ️', color: '#17a2b8', bg: '#d1ecf1' }
        };
        
        const toastType = types[type] || types.info;
        const toastId = 'toast-' + Date.now();
        
        const toastHtml = `
            <div id="${toastId}" class="toast-notification" style="
                background: ${toastType.bg};
                border: 1px solid ${toastType.color};
                border-radius: 8px;
                padding: 15px;
                margin-bottom: 10px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                display: flex;
                align-items: center;
                gap: 10px;
                animation: slideInRight 0.3s ease-out;
                position: relative;
                overflow: hidden;
            ">
                <span style="font-size: 18px;">${toastType.icon}</span>
                <span style="
                    flex: 1;
                    color: ${toastType.color};
                    font-weight: 500;
                    line-height: 1.4;
                ">${message}</span>
                <button onclick="closeToast('${toastId}')" style="
                    background: none;
                    border: none;
                    color: ${toastType.color};
                    font-size: 18px;
                    cursor: pointer;
                    padding: 0;
                    margin: 0;
                    line-height: 1;
                ">×</button>
                <div class="toast-progress" style="
                    position: absolute;
                    bottom: 0;
                    left: 0;
                    height: 3px;
                    background: ${toastType.color};
                    animation: progressBar ${duration}ms linear;
                "></div>
            </div>
        `;
        
        $('#toast-container').append(toastHtml);
        
        // Auto-remove after duration
        setTimeout(() => {
            closeToast(toastId);
        }, duration);
    }
    
    // Close toast
    window.closeToast = function(toastId) {
        const $toast = $('#' + toastId);
        $toast.css('animation', 'slideOutRight 0.3s ease-in');
        setTimeout(() => {
            $toast.remove();
        }, 300);
    };
    
    // Add CSS animations
    if ($('#toast-styles').length === 0) {
        $('head').append(`
            <style id="toast-styles">
                @keyframes slideInRight {
                    from { transform: translateX(100%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
                @keyframes slideOutRight {
                    from { transform: translateX(0); opacity: 1; }
                    to { transform: translateX(100%); opacity: 0; }
                }
                @keyframes progressBar {
                    from { width: 100%; }
                    to { width: 0%; }
                }
                .toast-notification:hover .toast-progress {
                    animation-play-state: paused;
                }
            </style>
        `);
    }
    
    // Override original alert() function
    const originalAlert = window.alert;
    window.showToast = showToast;
    
    // Alert replacement for known messages
    window.alert = function(message) {
        if (typeof message === 'string') {
            if (message.includes('erfolgreich') || message.includes('successfully')) {
                showToast(message, 'success');
            } else if (message.includes('Fehler') || message.includes('Error') || message.includes('error')) {
                showToast(message, 'error');
            } else if (message.includes('Bitte') || message.includes('Please') || message.includes('geben Sie')) {
                showToast(message, 'warning');
            } else {
                showToast(message, 'info');
            }
        } else {
            originalAlert(message);
        }
    };
    
    // Custom Confirm Dialog
    function showCustomConfirm(message, onConfirm, onCancel) {
        createToastContainer();
        
        const confirmId = 'confirm-' + Date.now();
        
        const confirmHtml = `
            <div id="${confirmId}" class="toast-notification confirm-dialog" style="
                background: #fff3cd;
                border: 2px solid #ffc107;
                border-radius: 8px;
                padding: 20px;
                margin-bottom: 10px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.3);
                animation: slideInRight 0.3s ease-out;
                max-width: 400px;
            ">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px;">
                    <span style="font-size: 20px;">⚠️</span>
                    <span style="
                        flex: 1;
                        color: #856404;
                        font-weight: 500;
                        line-height: 1.4;
                    ">${message}</span>
                </div>
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button onclick="handleConfirmAction('${confirmId}', 'cancel')" style="
                        background: #6c757d;
                        color: white;
                        border: none;
                        padding: 8px 16px;
                        border-radius: 4px;
                        cursor: pointer;
                        font-weight: 500;
                    ">${__l('cancel', 'Cancel')}</button>
                    <button onclick="handleConfirmAction('${confirmId}', 'confirm')" style="
                        background: #dc3545;
                        color: white;
                        border: none;
                        padding: 8px 16px;
                        border-radius: 4px;
                        cursor: pointer;
                        font-weight: 500;
                    ">${__l('confirm', 'Confirm')}</button>
                </div>
            </div>
        `;
        
        $('#toast-container').append(confirmHtml);
        
        // Store callbacks for the confirm dialog
        window['confirmCallback_' + confirmId] = onConfirm;
        window['cancelCallback_' + confirmId] = onCancel;
    }
    
    // Handle confirm dialog actions
    window.handleConfirmAction = function(confirmId, action) {
        const $confirm = $('#' + confirmId);
        
        if (action === 'confirm' && window['confirmCallback_' + confirmId]) {
            window['confirmCallback_' + confirmId]();
        } else if (action === 'cancel' && window['cancelCallback_' + confirmId]) {
            window['cancelCallback_' + confirmId]();
        }
        
        // Clean up callbacks
        delete window['confirmCallback_' + confirmId];
        delete window['cancelCallback_' + confirmId];
        
        // Remove dialog
        $confirm.css('animation', 'slideOutRight 0.3s ease-in');
        setTimeout(() => {
            $confirm.remove();
        }, 300);
    };
    
    // Make functions globally available
    window.showCustomConfirm = showCustomConfirm;
    window.discordEmbedL10n_get = __l; // Make localization helper globally available
    
    console.log('Toast notification system loaded');
    
})(jQuery);
