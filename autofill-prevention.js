/**
 * Autofill Prevention Script
 * Verhindert automatisches Ausfüllen der Discord Embed Plugin Felder
 */

jQuery(document).ready(function($) {
    console.log('Autofill Prevention: Starting initialization...');
    
    // Target fields that should be protected from autofill
    const protectedFields = [
        '#webhook-url',
        '#bot-token', 
        '#server-id'
    ];
    
    // Check if main admin.js loaded our helper function
    function waitForAdminJS() {
        return new Promise((resolve) => {
            let attempts = 0;
            const check = () => {
                if (window.setReadonlyFieldValue || attempts > 50) {
                    resolve(window.setReadonlyFieldValue);
                } else {
                    attempts++;
                    setTimeout(check, 100);
                }
            };
            check();
        });
    }
    
    // Function to make field readonly until user interaction
    function makeFieldReadonlyUntilInteraction(selector) {
        const $field = $(selector);
        if ($field.length === 0) return;
        
        console.log('Autofill Prevention: Protecting field', selector);
        
        // Set readonly initially if not already set
        if (!$field.attr('readonly')) {
            $field.attr('readonly', true);
        }
        
        // Remove readonly on user interaction
        $field.off('click.autofill focus.autofill mousedown.autofill').on('click.autofill focus.autofill mousedown.autofill', function() {
            $(this).removeAttr('readonly');
            console.log('Autofill Prevention: Field unlocked', selector);
        });
        
        // Additional protection: Clear any auto-filled values after a short delay
        setTimeout(function() {
            if ($field.attr('readonly') && $field.val() !== '' && !$field.data('user-filled')) {
                console.log('Autofill Prevention: Clearing auto-filled value in', selector);
                $field.val('');
            }
        }, 1000);
    }
    
    // Function to add dummy fields that confuse autofill
    function addDummyFields() {
        console.log('Autofill Prevention: Adding dummy fields...');
        
        // Add invisible dummy fields before webhook-url
        const $webhookRow = $('#webhook-url').closest('.form-row');
        if ($webhookRow.length) {
            $webhookRow.before(`
                <div style="position: absolute; left: -9999px; opacity: 0; height: 0; overflow: hidden;">
                    <input type="text" name="fake_username" autocomplete="username" tabindex="-1">
                    <input type="password" name="fake_password" autocomplete="current-password" tabindex="-1">
                    <input type="url" name="fake_url" autocomplete="url" tabindex="-1">
                </div>
            `);
        }
    }
    
    // Function to prevent form data persistence
    function preventFormPersistence() {
        console.log('Autofill Prevention: Setting up form persistence prevention...');
        
        // Prevent browser from remembering form data
        protectedFields.forEach(function(selector) {
            const $field = $(selector);
            if ($field.length) {
                $field.attr('autocomplete', 'new-password'); // Browser-specific prevention
                $field.attr('data-form-type', 'other'); // Additional hint
            }
        });
    }
    
    // Function to handle template loading without triggering autofill
    function setupTemplateLoadingProtection() {
        // When templates are loaded, temporarily make fields readonly to prevent interference
        $(document).on('discord-template-loading', function() {
            console.log('Autofill Prevention: Template loading detected, protecting fields...');
            protectedFields.forEach(function(selector) {
                $(selector).attr('readonly', true);
            });
        });
        
        $(document).on('discord-template-loaded', function() {
            console.log('Autofill Prevention: Template loaded, re-enabling fields...');
            setTimeout(function() {
                protectedFields.forEach(function(selector) {
                    const $field = $(selector);
                    // Only remove readonly if field has content (was loaded from template)
                    if ($field.val() !== '') {
                        $field.removeAttr('readonly');
                        $field.data('user-filled', true); // Mark as legitimately filled
                    }
                });
            }, 100);
        });
    }
    
    // Function to work with admin.js setReadonlyFieldValue
    function setupAdminJSIntegration() {
        console.log('Autofill Prevention: Setting up admin.js integration...');
        
        // Create global helper function immediately
        window.setReadonlyFieldValue = function(selector, value) {
            const $field = $(selector);
            if ($field.length && value !== undefined && value !== null && value !== '') {
                console.log('Autofill Prevention: Setting value for', selector, 'value length:', String(value).length);
                
                // Force remove readonly attribute multiple times to ensure it's gone
                $field.removeAttr('readonly');
                $field.prop('readonly', false);
                
                // Set the value with multiple methods to ensure it takes
                $field.val(value);
                $field[0].value = value; // Direct DOM property
                
                // Mark the field appropriately
                $field.data('user-filled', true);
                $field.data('programmatic-set', true);
                $field.data('last-set-value', value);
                
                // Re-add readonly protection after value is confirmed set
                setTimeout(() => {
                    if ($field.val() === String(value)) {
                        $field.attr('readonly', true);
                        console.log('Autofill Prevention: Value confirmed and field protected', selector);
                    } else {
                        console.warn('Autofill Prevention: Value not set correctly, retrying...', selector);
                        $field.val(value);
                        setTimeout(() => $field.attr('readonly', true), 50);
                    }
                }, 100);
                
                return true;
            }
            return false;
        };
        
        // Create additional helper for bulk value setting
        window.setMultipleReadonlyValues = function(values) {
            console.log('Autofill Prevention: Setting multiple values', Object.keys(values));
            
            // Remove readonly from all fields first
            Object.keys(values).forEach(selector => {
                const $field = $(selector);
                if ($field.length) {
                    $field.removeAttr('readonly');
                    $field.prop('readonly', false);
                }
            });
            
            // Wait a bit, then set all values
            setTimeout(() => {
                Object.keys(values).forEach(selector => {
                    if (values[selector] !== undefined && values[selector] !== null && values[selector] !== '') {
                        const $field = $(selector);
                        if ($field.length) {
                            $field.val(values[selector]);
                            $field[0].value = values[selector]; // Direct DOM
                            $field.data('user-filled', true);
                            $field.data('programmatic-set', true);
                        }
                    }
                });
                
                // Re-protect all fields after values are set
                setTimeout(() => {
                    Object.keys(values).forEach(selector => {
                        const $field = $(selector);
                        if ($field.length && $field.val() !== '') {
                            $field.attr('readonly', true);
                        }
                    });
                    console.log('Autofill Prevention: Bulk values set and protected');
                }, 100);
            }, 50);
        };
        
        console.log('Autofill Prevention: Admin.js integration ready');
    }
    
    // Initialize all protection measures
    function initializeAutofillPrevention() {
        console.log('Autofill Prevention: Initializing all protection measures...');
        
        // Add dummy fields first
        addDummyFields();
        
        // Set up form persistence prevention
        preventFormPersistence();
        
        // Protect each field
        protectedFields.forEach(makeFieldReadonlyUntilInteraction);
        
        // Setup template loading protection
        setupTemplateLoadingProtection();
        
        // Setup admin.js integration
        setupAdminJSIntegration();
        
        console.log('Autofill Prevention: Initialization complete');
    }
    
    // Initialize with delay to ensure DOM is ready
    setTimeout(initializeAutofillPrevention, 100);
    
    // Re-initialize if new content is loaded dynamically
    $(document).on('discord-form-updated', initializeAutofillPrevention);
    
    // Also initialize when admin.js fires webhook settings loaded
    $(document).on('discord-webhook-settings-loaded', function(e, data) {
        console.log('Autofill Prevention: Webhook settings loaded event received', data);
        
        setTimeout(function() {
            // Use our global function to set values properly
            if (data && typeof data === 'object') {
                if (data.webhook_url) {
                    window.setReadonlyFieldValue('#webhook-url', data.webhook_url);
                }
                if (data.bot_token) {
                    window.setReadonlyFieldValue('#bot-token', data.bot_token);
                }
                if (data.server_id) {
                    window.setReadonlyFieldValue('#server-id', data.server_id);
                }
            } else {
                // Fallback: check existing values and protect them
                protectedFields.forEach(function(selector) {
                    const $field = $(selector);
                    if ($field.val() !== '') {
                        console.log('Autofill Prevention: Protecting existing value in', selector);
                        $field.data('user-filled', true);
                        $field.data('programmatic-set', true);
                        if (!$field.attr('readonly')) {
                            $field.attr('readonly', true);
                        }
                    }
                });
            }
        }, 100);
    });
    
    // Export for debugging and external use
    window.DiscordAutofillPrevention = {
        protectedFields: protectedFields,
        reinitialize: initializeAutofillPrevention,
        makeFieldReadonly: makeFieldReadonlyUntilInteraction,
        setReadonlyValue: function(selector, value) {
            const $field = $(selector);
            if ($field.length && value) {
                const wasReadonly = $field.attr('readonly') !== undefined;
                if (wasReadonly) {
                    $field.removeAttr('readonly');
                }
                $field.val(value);
                if (wasReadonly) {
                    $field.attr('readonly', true);
                }
                console.log('Autofill Prevention: Set readonly field value', selector, '(value provided)');
            }
        }
    };
});
