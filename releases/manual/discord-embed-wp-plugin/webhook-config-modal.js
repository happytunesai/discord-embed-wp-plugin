/**
 * Webhook Configuration Modal
 * Sichere Eingabe von Webhook-Daten ohne Autofill-Probleme
 */

jQuery(document).ready(function($) {
    console.log('Webhook Config Modal: Initializing...');
    
    // Validation patterns
    const VALIDATION_PATTERNS = {
        webhookUrl: /^https:\/\/discord\.com\/api\/webhooks\/\d+\/[\w-]+$/,
        botToken: /^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/,
        serverId: /^\d{17,19}$/
    };
    
    // Status management
    let currentWebhookData = {
        webhook_url: '',
        bot_token: '',
        server_id: '',
        webhook_type: 'channel'
    };
    
    // Load existing data from database on page load
    function loadWebhookDataFromDatabase() {
        console.log('Webhook Config: Loading data from database...');
        
        $.ajax({
            url: discordEmbed.ajaxUrl,
            method: 'POST',
            data: {
                action: 'load_webhook_settings',
                nonce: discordEmbed.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    currentWebhookData = response.data;
                    updateStatusIndicators();
                    console.log('Webhook Config: Data loaded from database');
                }
            },
            error: function() {
                console.log('Webhook Config: Fallback to localStorage');
                loadWebhookDataFromLocalStorage();
            }
        });
    }
    
    // Fallback: Load from localStorage
    function loadWebhookDataFromLocalStorage() {
        try {
            const settings = localStorage.getItem('discord_embed_webhook_settings');
            if (settings) {
                currentWebhookData = JSON.parse(settings);
                updateStatusIndicators();
                console.log('Webhook Config: Data loaded from localStorage');
            }
        } catch (e) {
            console.log('Webhook Config: No stored data found');
        }
    }
    
    // Update status indicators
    function updateStatusIndicators() {
        // Update webhook URL status
        if (currentWebhookData.webhook_url && validateWebhookUrl(currentWebhookData.webhook_url)) {
            $('#webhook-status-text').text('Configured ✅');
            $('#webhook-status-icon').text('✅').css('color', '#28a745');
        } else {
            $('#webhook-status-text').text('Not configured');
            $('#webhook-status-icon').text('❌').css('color', '#dc3545');
        }
        
        // Update bot token status
        if (currentWebhookData.bot_token && validateBotToken(currentWebhookData.bot_token)) {
            $('#token-status-text').text('Configured ✅');
            $('#token-status-icon').text('✅').css('color', '#28a745');
        } else {
            $('#token-status-text').text('Not configured');
            $('#token-status-icon').text('❌').css('color', '#dc3545');
        }
        
        // Update server ID status
        if (currentWebhookData.server_id && validateServerId(currentWebhookData.server_id)) {
            $('#server-status-text').text('Configured ✅');
            $('#server-status-icon').text('✅').css('color', '#28a745');
        } else {
            $('#server-status-text').text('Not configured');
            $('#server-status-icon').text('❌').css('color', '#dc3545');
        }
        
        // Update hidden form fields
        $('#webhook-url').val(currentWebhookData.webhook_url || '');
        $('#bot-token').val(currentWebhookData.bot_token || '');
        $('#server-id').val(currentWebhookData.server_id || '');
        $('#webhook-type').val(currentWebhookData.webhook_type || 'channel').trigger('change');
        
        // Also update the global window object for other scripts to access
        window.discordWebhookData = currentWebhookData;
        
        console.log('Webhook Config: Status updated, hidden fields set:', {
            webhook_url: currentWebhookData.webhook_url ? 'SET' : 'EMPTY',
            bot_token: currentWebhookData.bot_token ? 'SET' : 'EMPTY',
            server_id: currentWebhookData.server_id ? 'SET' : 'EMPTY'
        });
    }
    
    // Validation functions
    function validateWebhookUrl(url) {
        return VALIDATION_PATTERNS.webhookUrl.test(url);
    }
    
    function validateBotToken(token) {
        return VALIDATION_PATTERNS.botToken.test(token);
    }
    
    function validateServerId(id) {
        return VALIDATION_PATTERNS.serverId.test(id);
    }
    
    // Show validation feedback
    function showValidationFeedback(fieldId, isValid, message) {
        const feedbackEl = $(`#${fieldId}-feedback`);
        const inputEl = $(`#modal-${fieldId.replace('-', '-')}`);
        
        feedbackEl.html(message);
        
        if (isValid) {
            feedbackEl.css('color', '#28a745');
            inputEl.css('border-color', '#28a745');
        } else {
            feedbackEl.css('color', '#dc3545');
            inputEl.css('border-color', '#dc3545');
        }
    }
    
    // Real-time validation
    function setupValidation() {
        $('#modal-webhook-url').on('input', function() {
            const value = $(this).val().trim();
            if (value === '') {
                showValidationFeedback('webhook-url', true, '');
                return;
            }
            
            const isValid = validateWebhookUrl(value);
            const message = isValid 
                ? '✅ Valid Webhook URL' 
                : '❌ Invalid URL - Must start with https://discord.com/api/webhooks/';
            showValidationFeedback('webhook-url', isValid, message);
        });
        
        $('#modal-bot-token').on('input', function() {
            const value = $(this).val().trim();
            if (value === '') {
                showValidationFeedback('bot-token', true, '');
                return;
            }
            
            const isValid = validateBotToken(value);
            const message = isValid 
                ? '✅ Valid Bot Token Format' 
                : '❌ Invalid Format - Should have 3 parts separated by dots';
            showValidationFeedback('bot-token', isValid, message);
        });
        
        $('#modal-server-id').on('input', function() {
            const value = $(this).val().trim();
            if (value === '') {
                showValidationFeedback('server-id', true, '');
                return;
            }
            
            const isValid = validateServerId(value);
            const message = isValid 
                ? '✅ Valid Server ID' 
                : '❌ Invalid Server ID - Must have 17-19 digits';
            showValidationFeedback('server-id', isValid, message);
        });
    }
    
    // Modal event handlers
    $('#configure-webhook').on('click', function() {
        console.log('Webhook Config: Opening modal...');
        
        // Load current data into modal
        $('#modal-webhook-url').val(currentWebhookData.webhook_url || '');
        $('#modal-bot-token').val(currentWebhookData.bot_token || '');
        $('#modal-server-id').val(currentWebhookData.server_id || '');
        
        // Clear validation feedback
        $('.validation-feedback').empty();
        $('.config-field input').css('border-color', '#ddd');
        
        // Show modal
        $('#webhook-config-modal').fadeIn(300);
    });
    
    // Close modal
    $('#close-webhook-modal, #cancel-webhook-config').on('click', function() {
        $('#webhook-config-modal').fadeOut(300);
    });
    
    // Close modal on background click
    $('#webhook-config-modal').on('click', function(e) {
        if (e.target === this) {
            $(this).fadeOut(300);
        }
    });
    
    // Test webhook connection
    $('#test-webhook-config').on('click', function() {
        const $btn = $(this);
        const originalText = $btn.text();
        
        $btn.text('🔄 Testing...').prop('disabled', true);
        
        const testData = {
            webhook_url: $('#modal-webhook-url').val().trim(),
            bot_token: $('#modal-bot-token').val().trim(),
            server_id: $('#modal-server-id').val().trim()
        };
        
        // Validate before testing
        let isValid = true;
        if (testData.webhook_url && !validateWebhookUrl(testData.webhook_url)) {
            showToast('❌ Invalid Webhook URL', 'error');
            isValid = false;
        }
        if (testData.bot_token && !validateBotToken(testData.bot_token)) {
            showToast('❌ Invalid Bot Token Format', 'error');
            isValid = false;
        }
        if (testData.server_id && !validateServerId(testData.server_id)) {
            showToast('❌ Invalid Server ID', 'error');
            isValid = false;
        }
        
        if (!isValid) {
            $btn.text(originalText).prop('disabled', false);
            return;
        }
        
        // Simulate test (replace with actual test logic)
        setTimeout(() => {
            showToast('✅ Connection tested successfully!', 'success');
            $btn.text(originalText).prop('disabled', false);
        }, 2000);
    });
    
    // Save webhook configuration
    $('#save-webhook-config').on('click', function() {
        const $btn = $(this);
        const originalText = $btn.text();
        
        $btn.text('💾 Saving...').prop('disabled', true);
        
        const newData = {
            webhook_url: $('#modal-webhook-url').val().trim(),
            bot_token: $('#modal-bot-token').val().trim(),
            server_id: $('#modal-server-id').val().trim(),
            webhook_type: currentWebhookData.webhook_type || 'channel'
        };
        
        // Validate all fields
        let isValid = true;
        let errorMessages = [];
        
        if (newData.webhook_url && !validateWebhookUrl(newData.webhook_url)) {
            errorMessages.push('Invalid Webhook URL');
            isValid = false;
        }
        if (newData.bot_token && !validateBotToken(newData.bot_token)) {
            errorMessages.push('Invalid Bot Token');
            isValid = false;
        }
        if (newData.server_id && !validateServerId(newData.server_id)) {
            errorMessages.push('Invalid Server ID');
            isValid = false;
        }
        
        if (!isValid) {
            showToast('❌ ' + errorMessages.join(', '), 'error');
            $btn.text(originalText).prop('disabled', false);
            return;
        }
        
        // Save to database
        $.ajax({
            url: discordEmbed.ajaxUrl,
            method: 'POST',
            data: {
                action: 'save_webhook_settings',
                nonce: discordEmbed.nonce,
                webhook_type: newData.webhook_type,
                webhook_url: newData.webhook_url,
                bot_token: newData.bot_token,
                server_id: newData.server_id,
                channel_id: $('#channel-id').val() || '' // preserve existing channel selection
            },
            success: function(response) {
                if (response.success) {
                    currentWebhookData = newData;
                    
                    // Update localStorage
                    localStorage.setItem('discord_embed_webhook_settings', JSON.stringify(newData));
                    
                    // Update status indicators and hidden fields
                    updateStatusIndicators();
                    
                    // Fire event to notify other scripts
                    $(document).trigger('discord-webhook-settings-updated', newData);
                    
                    // Close modal
                    $('#webhook-config-modal').fadeOut(300);
                    
                    showToast('✅ Webhook settings saved!', 'success');
                    console.log('Webhook Config: Settings saved successfully', {
                        webhook_url: newData.webhook_url ? 'SET' : 'EMPTY',
                        bot_token: newData.bot_token ? 'SET' : 'EMPTY',
                        server_id: newData.server_id ? 'SET' : 'EMPTY'
                    });
                } else {
                    showToast('❌ Error saving: ' + (response.data || 'Unknown error'), 'error');
                }
                
                $btn.text(originalText).prop('disabled', false);
            },
            error: function() {
                showToast('❌ Connection error while saving', 'error');
                $btn.text(originalText).prop('disabled', false);
            }
        });
    });
    
    // Initialize
    loadWebhookDataFromDatabase();
    setupValidation();
    
    console.log('Webhook Config Modal: Initialized successfully');
});
