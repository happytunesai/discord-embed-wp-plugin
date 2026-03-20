jQuery(document).ready(function($) {
    // Debug flag
    window.discordEmbedDebug = false;
    
    // Localization helper function
    function __l(key, fallback) {
        return (typeof discordEmbedL10n !== 'undefined' && discordEmbedL10n[key]) ? discordEmbedL10n[key] : fallback;
    }
    
    // Loading flag to prevent auto-save during initialization
    let isLoadingWebhookSettings = false;
    // Message History Pagination Variables
    let currentHistoryPage = 1;
    let currentHistoryFilter = 'all';
    let isLoadingHistory = false;
    
    // Debug logging function
    function debugLog(message, type = 'info', data = null) {
        if (window.discordEmbedDebug) {
            console.log(`[${new Date().toISOString()}] [Discord Embed] ${type.toUpperCase()}: ${message}`, data || '');
        }
        
        // Also log to debug console if available
        if (typeof window.debugLog === 'function') {
            const logMessage = data ? `${message} | ${JSON.stringify(data)}` : message;
            window.debugLog(logMessage, type);
        }
    }

    debugLog('Discord Embed Admin JS loaded');
    
    // Template Card Event Handlers - for the new template cards at the top
    $(document).on('click', '.load-template', function() {
        const templateId = $(this).data('template-id');
        const templateName = $(this).data('template-name');
        const embedDataRaw = $(this).attr('data-embed-data'); // Use attr() instead of data() to avoid HTML escaping
        
        debugLog('Loading template from card', 'info', {
            templateId, 
            templateName, 
            embedDataRaw: embedDataRaw?.substring(0, 100) + '...'
        });
        
        try {
            // Parse the embed data - mehrere Ansätze probieren
            let embed;
            
            if (typeof embedDataRaw === 'string') {
                // Versuche direktes JSON.parse
                embed = JSON.parse(embedDataRaw);
            } else if (typeof embedDataRaw === 'object') {
                // If jQuery has already parsed it
                embed = embedDataRaw;
            } else {
                throw new Error('Unknown data type: ' + typeof embedDataRaw);
            }
            
            debugLog('Parsed embed data', 'info', embed);
            
            // Fill form fields - Use loadTemplateData function for consistency
            loadTemplateData(embed, templateName);
            
            // Update preview after template load
            debugLog('Updating preview after template load', 'info');
            
            // Preview update is handled by loadTemplateData function
            
            // Show success message
            showToast(__l('templateLoadedSuccessfully', 'Template "' + templateName + '" loaded successfully!'), 'success');
            
            debugLog('Template loaded successfully', 'success');
            
        } catch (error) {
            debugLog('Error loading template', 'error', {
                error: error.message,
                embedDataRaw: embedDataRaw?.substring(0, 200),
                embedDataType: typeof embedDataRaw
            });
            showToast(__l('errorLoadingTemplate', 'Error loading template: ') + error.message, 'error');
        }
    });
    
    $(document).on('click', '.delete-template-card', function() {
        const templateId = $(this).data('template-id');
        const templateName = $(this).data('template-name');
        
        showCustomConfirm(
            __l('deleteTemplateConfirm', 'Do you really want to delete the template "%s"?').replace('%s', templateName),
            function() {
                // Confirm - delete template
                debugLog('Deleting template from card', 'info', {templateId, templateName});
                
                $.ajax({
                    url: discordEmbed.ajaxUrl,
                    method: 'POST',
                    data: {
                        action: 'delete_embed_template',
                        template_id: templateId,
                        nonce: discordEmbed.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            // Remove the template card from DOM
                            $(`.template-card[data-template-id="${templateId}"]`).fadeOut(300, function() {
                                $(this).remove();
                                
                                // Check if no templates left
                                if ($('.template-card').length === 0) {
                                    $('#saved-templates').parent().html(
                                        '<h2 style="margin-top: 0; color: #5865f2; border-bottom: 2px solid #5865f2; padding-bottom: 10px;">📋 Saved Templates</h2>' +
                                        '<p style="color: #666; font-style: italic;">No templates saved yet. Create your first template below!</p>'
                                    );
                                }
                            });
                            
                            showToast(__l('templateDeletedSuccessfully', 'Template "' + templateName + '" deleted successfully!'), 'success');
                            debugLog('Template deleted successfully', 'success');
                        } else {
                            showToast(__l('errorDeleting', 'Error deleting: ') + response.data, 'error');
                            debugLog('Error deleting template', 'error', response.data);
                        }
                    },
                    error: function(xhr, status, error) {
                        showToast(__l('ajaxErrorDeletingTemplate', 'AJAX error deleting template'), 'error');
                        debugLog('AJAX error deleting template', 'error', {status, error});
                    }
                });
            },
            function() {
                // Cancel - do nothing
                showToast(__l('deletionCancelled', 'Deletion cancelled.'), 'info');
            }
        );
    });
    
    // Check if required elements exist
    debugLog('Checking required elements', 'info', {
        'save-template': $('#save-template').length,
        'send-embed': $('#send-embed').length,
        'template-name': $('#template-name').length,
        'webhook-url': $('#webhook-url').length
    });

    // Test AJAX availability
    if (typeof discordEmbed === 'undefined') {
        debugLog('ERROR: discordEmbed object not found!', 'error');
        alert(__l('jsConfigError', 'JavaScript configuration error. Please reload the page.'));
        return;
    } else {
        debugLog('discordEmbed object found', 'success', discordEmbed);
    }

    // Load saved webhook data on page load
    isLoadingWebhookSettings = true;
    loadWebhookSettings();
    
    // Auto-load roles if credentials are available (delayed to ensure role manager is loaded AND database settings are loaded)
    setTimeout(function() {
        const serverId = $('#server-id').val();
        const botToken = $('#bot-token').val();
        
        if (serverId && botToken && window.DiscordRoleManager) {
            debugLog('Auto-loading roles on page load...');
            window.DiscordRoleManager.loadAndDisplayRoles(botToken, serverId);
        }
        
        // Load message history after everything is initialized
        loadMessageHistory();
    }, 5000); // Increased to 5 seconds to account for database loading

    // Webhook type toggle
    $('#webhook-type').on('change', function() {
        const type = $(this).val();
        debugLog('Webhook type changed', 'info', type);
        if (type === 'server') {
            $('#webhook-url').closest('.form-row').hide();
            $('#server-webhook-config').show();
        } else {
            $('#webhook-url').closest('.form-row').show();
            $('#server-webhook-config').hide();
        }
        saveWebhookSettings();
    });

    // Save webhook settings when any field changes
    $('#webhook-url, #bot-token, #server-id, #channel-id').on('change', saveWebhookSettings);

    // Server ID change - load channels AND roles
    $('#server-id').on('change', function() {
        const serverId = $(this).val();
        const botToken = $('#bot-token').val();
        
        if (serverId && botToken) {
            loadServerChannels();
            // Also trigger role loading if role manager is available
            if (window.DiscordRoleManager) {
                setTimeout(() => {
                    window.DiscordRoleManager.loadAndDisplayRoles(botToken, serverId);
                }, 500);
            }
        }
        saveWebhookSettings();
    });

    // Bot Token change - load channels AND roles  
    $('#bot-token').on('change', function() {
        const botToken = $(this).val();
        const serverId = $('#server-id').val();
        
        if (serverId && botToken) {
            loadServerChannels();
            // Also trigger role loading if role manager is available
            if (window.DiscordRoleManager) {
                setTimeout(() => {
                    window.DiscordRoleManager.loadAndDisplayRoles(botToken, serverId);
                }, 500);
            }
        }
        saveWebhookSettings();
    });

    // Color input
    $('#embed-color').on('input', function() {
        updateColorPreview();
        updatePreview();
    });

    // Text inputs - Live preview updates
    $('#embed-title, #embed-description, #embed-url, #thumbnail-url, #image-url, #author-name, #author-url, #author-icon, #footer-text, #footer-icon').on('input', updatePreview);

    // Add field button
    $('#add-field').on('click', function() {
        addField();
    });

    // Remove field button
    $(document).on('click', '.remove-field', function() {
        $(this).closest('.embed-field').remove();
        updatePreview();
    });

    // Field inputs
    $(document).on('input', '.field-input', updatePreview);
    $(document).on('change', '.inline-checkbox', updatePreview);

    // Store original emoji picker button text so we can restore it after loading
    let originalEmojiPickerText = $('.open-emoji-picker').first().text() || '😃';

    // Markdown toolbar buttons
    $(document).on('click', '.md-btn', function() {
        const markdownRaw = $(this).data('md');
        const markdown = (typeof markdownRaw === 'string') ? markdownRaw : '';
        // try to find a textarea or input next to the toolbar
        let textarea = $(this).closest('.markdown-toolbar').next('textarea, input[type="text"]');
        if (!textarea || textarea.length === 0) {
            // fallback: try a parent form row, then any focused input/textarea
            textarea = $(this).closest('form').find('textarea:visible, input[type="text"]:visible').first();
        }
        if (textarea && textarea.length) {
            insertMarkdown(textarea[0], markdown);
        } else {
            // As last resort try active element
            const active = document.activeElement;
            if (active && (active.tagName === 'TEXTAREA' || (active.tagName === 'INPUT' && active.type === 'text'))) {
                insertMarkdown(active, markdown);
            } else {
                // Nothing found - show gentle notice
                showToast(__l('noTargetForMarkdown', 'No target field found for markdown insertion.'), 'warning');
            }
        }
    });

    // --- Emoji picker for main editor (reuses server endpoint used by live notifications) ---
    // Unified emoji picker handler for main editor and live notifications
    $(document).on('click', '#tab-embed-creator .open-emoji-picker', function() {
        // Prefer bot token/server id from main webhook fields, fallback to live fields
        const botToken = $('#bot-token').val() || $('#live-bot-token').val();
        const serverId = $('#server-id').val() || $('#live-server-id').val();

        if (!botToken || !serverId) {
            alert('Bitte Bot Token und Server ID eingeben (Webhook settings)');
            return;
        }

        const $btn = $(this);
        const originalText = $btn.text() || '😃';
        // Disable all picker buttons and show loading state (keeps UI consistent)
        $('.open-emoji-picker').prop('disabled', true).text(discordEmbedL10n.loadingEmojis);

        $.ajax({
            url: discordEmbed.ajaxUrl,
            method: 'POST',
            data: {
                action: 'load_server_emojis',
                nonce: discordEmbed.nonce,
                bot_token: botToken,
                server_id: serverId
            },
            success: function(response) {
                try {
                    if (response.success && response.data && response.data.emojis) {
                        renderEmojiPickerMain(response.data.emojis);
                        openEmojiPickerMain();
                    } else {
                        alert(discordEmbedL10n.errorLoadingEmojis + (response.data || 'Unknown error'));
                    }
                } catch (e) {
                    console.error('Error handling emoji response', e);
                    alert(discordEmbedL10n.errorProcessingEmojis + e.message);
                }
            },
            error: function(xhr, status, error) {
                alert(discordEmbedL10n.errorLoadingEmojis + error);
            },
            complete: function() {
                // Restore each button to its stored original text and remove the stored data
                $('.open-emoji-picker').each(function() {
                    const $el = $(this);
                    const txt = $el.data('original-text') || '😃';
                    $el.prop('disabled', false).text(txt).removeData('original-text');
                });
            }
        });
    });

    function renderEmojiPickerMain(emojis) {
        // Reuse the same modal container used by live-notifications if present,
        // otherwise create a minimal one here
        let modal = $('#emoji-picker-modal');
        if (modal.length === 0) {
            $('body').append(`
                <div id="emoji-picker-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:20000; padding:20px; box-sizing:border-box;">
                    <div style="background:#fff; border-radius:8px; padding:16px; width:100%; max-width:900px; max-height:calc(100vh - 120px); overflow:auto; margin:auto;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                            <h3 style="margin:0;">Emoji Picker</h3>
                            <div><button type="button" id="close-emoji-picker" class="button">Schließen</button></div>
                        </div>
                        <div id="emoji-picker-grid" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(60px, 1fr)); gap:8px;"></div>
                    </div>
                </div>
            `);
            modal = $('#emoji-picker-modal');
        }

        const grid = modal.find('#emoji-picker-grid');
        grid.empty();
        emojis.forEach(function(e) {
            const code = (e.animated ? '<a:' : '<:') + e.name + ':' + e.id + '>';
            const safeName = escapeHtml(e.name);
            const el = $(
                `<div class="emoji-item" title="${safeName}" style="text-align:center; padding:6px; border:1px solid #eee; border-radius:6px; cursor:pointer;">
                    <img src="${e.url}" style="width:48px; height:48px; object-fit:cover; display:block; margin:0 auto 4px;">
                    <div style="font-size:11px; color:#333; word-break:break-word;">${safeName}</div>
                </div>`
            );
            el.on('click', function() {
                insertEmojiCodeIntoActiveField(code);
                // Use centralized close so body overflow is restored consistently
                closeEmojiPickerMain();
            });
            grid.append(el);
        });
    }

    function insertEmojiCodeIntoActiveField(code) {
        const active = document.activeElement;
        if (!active) return;
        if (active.tagName === 'TEXTAREA' || (active.tagName === 'INPUT' && active.type === 'text')) {
            const start = active.selectionStart || 0;
            const end = active.selectionEnd || 0;
            const val = active.value;
            const newVal = val.substring(0, start) + code + val.substring(end);
            active.value = newVal;
            const pos = start + code.length;
            try { active.setSelectionRange(pos, pos); } catch (e) {}
            $(active).trigger('input');
            return;
        }

        // Default: append to main description
        const desc = $('#embed-description')[0];
        if (desc) {
            desc.value = desc.value + '\n' + code;
            $(desc).trigger('input');
        }
    }

    function openEmojiPickerMain() {
        const modal = $('#emoji-picker-modal');
        modal.css('display', 'flex');
        $('body').css('overflow', 'hidden');
        modal.attr('tabindex', '-1').focus();
    }

    function closeEmojiPickerMain() {
        const modal = $('#emoji-picker-modal');
        // hide with same display manipulation used by opener
        modal.css('display', 'none');
        // restore page scrolling explicitly
        $('body').css('overflow', 'auto');
    }

    // Close button handler (works for both pickers)
    $(document).on('click', '#close-emoji-picker', function() {
        closeEmojiPickerMain();
    });
    // --- End Emoji picker main ---

    // Event handler for webhook settings updates from modal
    $(document).on('discord-webhook-settings-updated', function(e, data) {
        debugLog('Webhook settings updated from modal', 'info', {
            webhook_url: data.webhook_url ? 'SET' : 'EMPTY',
            bot_token: data.bot_token ? 'SET' : 'EMPTY',
            server_id: data.server_id ? 'SET' : 'EMPTY'
        });
        
        // Update webhook type if needed
        if (data.webhook_type) {
            $('#webhook-type').val(data.webhook_type).trigger('change');
        }
    });

    // Template management with robust error handling
    $('#save-template').off('click').on('click', function() {
        debugLog('Save template button clicked');
        try {
            saveTemplate();
        } catch (error) {
            debugLog('Error in save template', 'error', error);
            alert(__l('errorSavingConfig', 'Error saving: %s').replace('%s', error.message));
        }
    });
    
    $('#send-embed').off('click').on('click', function() {
        debugLog('Send embed button clicked');
        try {
            sendDiscordMessage();
        } catch (error) {
            debugLog('Error in send message', 'error', error);
            alert(__l('errorSending', 'Error sending: %s').replace('%s', error.message));
        }
    });

    // Load channels button
    $('#load-channels').on('click', loadServerChannels);

    // Load message history
    $('#load-history').on('click', loadMessageHistory);

    // Initialize with error handling
    try {
        updateColorPreview();
        updatePreview();
        debugLog('Initialization completed successfully');
    } catch (error) {
        debugLog('Initialization error', 'error', error);
    }

    // Global function is now provided by autofill-prevention.js
    // This ensures proper readonly field handling with autofill protection

    function loadWebhookSettings() {
        debugLog('Loading webhook settings from database...', 'info');
        // prevent auto-save while populating fields
        isLoadingWebhookSettings = true;
        
        // The webhook modal now handles all data loading and status updates
        // This function is simplified to work with the modal system
        $.ajax({
            url: discordEmbed.ajaxUrl,
            method: 'POST',
            data: {
                action: 'load_webhook_settings',
                nonce: discordEmbed.nonce
            },
            success: function(response) {
                debugLog('Webhook settings loaded from database', 'success', response);
                
                if (response.success && response.data) {
                    const data = response.data;
                    
                    // Load settings into form fields
                    if (data.webhook_type) {
                        $('#webhook-type').val(data.webhook_type);
                        // Update UI without triggering save
                        if (data.webhook_type === 'server') {
                            $('#webhook-url').closest('.form-row').hide();
                            $('#server-webhook-config').show();
                        } else {
                            $('#webhook-url').closest('.form-row').show();
                            $('#server-webhook-config').hide();
                        }
                    }
                    if (data.bot_token) $('#bot-token').val(data.bot_token);
                    if (data.server_id) $('#server-id').val(data.server_id);
                    if (data.channel_id) $('#channel-id').val(data.channel_id);
                    if (data.webhook_url) $('#webhook-url').val(data.webhook_url);
                    
                    // Update localStorage with database values
                    localStorage.setItem('discord_embed_webhook_settings', JSON.stringify(data));
                    
                    debugLog('Database settings applied and localStorage updated', 'info', data);
                    // done loading, allow saves
                    isLoadingWebhookSettings = false;
                    
                    // Fire event for other scripts
                    $(document).trigger('discord-webhook-settings-loaded', data);
                }
            },
            error: function(xhr, status, error) {
                debugLog('Error loading from database, falling back to localStorage', 'warning', {status, error});
                
                // Fallback: Load from localStorage if database fails
                loadWebhookSettingsFromLocalStorage();
                // done loading, allow saves
                isLoadingWebhookSettings = false;
            }
        });
    }
    
    function loadWebhookSettingsFromLocalStorage() {
        try {
            const settings = localStorage.getItem('discord_embed_webhook_settings');
            if (settings) {
                const data = JSON.parse(settings);
                
                if (data.webhook_type) $('#webhook-type').val(data.webhook_type).trigger('change');
                if (data.channel_id) $('#channel-id').val(data.channel_id);
                
                debugLog('Webhook settings loaded from localStorage (fallback)', 'info', data);
                
                // Fire event for other scripts
                $(document).trigger('discord-webhook-settings-loaded', data);
            } else {
                debugLog('No settings found in localStorage', 'info');
            }
        } catch (e) {
            console.error('Error loading webhook settings from localStorage:', e);
        }
    }

    function saveWebhookSettings() {
        // Skip saving while loading initial settings
        if (isLoadingWebhookSettings) {
            debugLog('Skipping saveWebhookSettings during load', 'info');
            return;
        }
        try {
            const settings = {
                webhook_type: $('#webhook-type').val(),
                bot_token: $('#bot-token').val(),
                server_id: $('#server-id').val(),
                channel_id: $('#channel-id').val(),
                webhook_url: $('#webhook-url').val()
            };
            
            // Save to localStorage for immediate UI updates
            localStorage.setItem('discord_embed_webhook_settings', JSON.stringify(settings));
            
            // Also save to WordPress database via AJAX
            $.ajax({
                url: discordEmbed.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'save_webhook_settings',
                    nonce: discordEmbed.nonce,
                    webhook_type: settings.webhook_type,
                    bot_token: settings.bot_token,
                    server_id: settings.server_id,
                    channel_id: settings.channel_id,
                    webhook_url: settings.webhook_url
                },
                success: function(response) {
                    debugLog('Webhook settings saved to database', 'success', response);
                },
                error: function(xhr, status, error) {
                    debugLog('Error saving webhook settings to database', 'error', {status, error, response: xhr.responseText});
                }
            });
            
            debugLog('Webhook settings saved', 'info', settings);
        } catch (e) {
            console.error('Error saving webhook settings:', e);
        }
    }

    function loadServerChannels() {
        // Get values from hidden fields first, then from global webhook data as fallback
        let serverId = $('#server-id').val();
        let botToken = $('#bot-token').val();
        
        // Fallback to global webhook data if hidden fields are empty
        if ((!serverId || !botToken) && window.discordWebhookData) {
            serverId = serverId || window.discordWebhookData.server_id;
            botToken = botToken || window.discordWebhookData.bot_token;
            
            // Update hidden fields with the fallback data
            if (serverId) $('#server-id').val(serverId);
            if (botToken) $('#bot-token').val(botToken);
        }
        
        debugLog('Loading server channels with data', 'info', {
            serverId: serverId ? 'PROVIDED' : 'MISSING',
            botToken: botToken ? 'PROVIDED' : 'MISSING',
            source: window.discordWebhookData ? 'GLOBAL_FALLBACK' : 'FORM_FIELDS'
        });
        
        if (!serverId || !botToken) {
            alert(__l('configureBotTokenFirst', 'Please configure Bot Token and Server ID in the webhook settings first.'));
            return;
        }
        
        $.ajax({
            url: discordEmbed.ajaxUrl,
            method: 'POST',
            data: {
                action: 'load_server_channels',
                nonce: discordEmbed.nonce,
                server_id: serverId,
                bot_token: botToken
            },
            success: function(response) {
                debugLog('Server channels response', 'success', response);
                if (response.success && response.data.channels) {
                    const channelSelect = $('#channel-id');
                    channelSelect.empty().append('<option value="">Select channel...</option>');
                    
                    response.data.channels.forEach(function(channel) {
                        if (channel.type === 0 || channel.type === 5) { // Text channel (0) or Announcement channel (5)
                            const channelIcon = channel.type === 5 ? '📢' : '#';
                            channelSelect.append(`<option value="${channel.id}">${channelIcon} ${channel.name}</option>`);
                        }
                    });
                    
                    const totalChannels = response.data.channels.filter(c => c.type === 0 || c.type === 5).length;
                    alert(`${totalChannels} channels loaded!`);
                } else {
                    alert(__l('errorLoadingChannels', 'Error loading channels: %s').replace('%s', (response.data || 'Unknown error')));
                }
            },
            error: function(xhr, status, error) {
                debugLog('Error loading server channels', 'error', {status, error, response: xhr.responseText});
                alert(__l('errorLoadingServerChannels', 'Error loading server channels: %s').replace('%s', error));
            }
        });
    }

    function insertMarkdown(element, markdown) {
        // Accept jQuery objects
        if (element && element.jquery) element = element[0];

        // Defensive: element may be undefined or not support .value
        if (!element || !(element instanceof HTMLTextAreaElement || (element instanceof HTMLInputElement && element.type === 'text'))) {
            // Try to fall back to active element
            const active = document.activeElement;
            if (active && (active.tagName === 'TEXTAREA' || (active.tagName === 'INPUT' && active.type === 'text'))) {
                element = active;
            } else {
                // Nothing to insert into
                return;
            }
        }

        // Safely read value
        const value = (typeof element.value === 'string') ? element.value : '';

        // Compute selection positions defensively
        const start = (typeof element.selectionStart === 'number') ? element.selectionStart : value.length;
        const end = (typeof element.selectionEnd === 'number') ? element.selectionEnd : start;
        const safeStart = Math.max(0, Math.min(start, value.length));
        const safeEnd = Math.max(0, Math.min(end, value.length));
        const selectedText = value.substring(safeStart, safeEnd);

        let replacement;
        if (markdown === '') {
            replacement = '' + selectedText + '';
        } else if (markdown === '[text](url)') {
            replacement = selectedText ? `[${selectedText}](url)` : '[text](url)';
        } else {
            replacement = markdown + selectedText + markdown;
        }

        // Build new value
        const newValue = value.substring(0, safeStart) + replacement + value.substring(safeEnd);
        try { element.value = newValue; } catch (e) { /* ignore */ }

        // Focus and set cursor position
        try { element.focus(); } catch (e) {}
        const newPos = selectedText ? safeStart + replacement.length : safeStart + (markdown ? markdown.length : 0);
        try { element.setSelectionRange(newPos, newPos); } catch (e) {}

        // Trigger preview update
        try { updatePreview(); } catch (e) {}
    }

    function addField(name = '', value = '', inline = false) {
        const fieldHtml = `
            <div class="embed-field">
                <div class="field-actions">
                    <button type="button" class="button remove-field">Remove</button>
                </div>
                <div class="field-inline-checkbox">
                    <input type="checkbox" class="field-input inline-checkbox" ${inline ? 'checked' : ''}>
                    <label>Display inline</label>
                </div>
                <div class="form-row">
                    <label>Field Name:</label>
                    <div class="markdown-toolbar">
                        <button type="button" class="md-btn" data-md="**" title="Bold"><strong>B</strong></button>
                        <button type="button" class="md-btn" data-md="*" title="Italic"><em>I</em></button>
                        <button type="button" class="md-btn" data-md="__" title="Underlined"><u>U</u></button>
                        <button type="button" class="md-btn" data-md="~~" title="Strikethrough"><del>S</del></button>
                        <button type="button" class="md-btn" data-md="\`" title="Code"><code>C</code></button>
                    </div>
                    <input type="text" class="field-input field-name" value="${escapeHtml(name)}" placeholder="Field Name">
                </div>
                <div class="form-row">
                    <label>Field Value:</label>
                    <div class="markdown-toolbar">
                        <button type="button" class="md-btn" data-md="**" title="Bold"><strong>B</strong></button>
                        <button type="button" class="md-btn" data-md="*" title="Italic"><em>I</em></button>
                        <button type="button" class="md-btn" data-md="__" title="Underlined"><u>U</u></button>
                        <button type="button" class="md-btn" data-md="~~" title="Strikethrough"><del>S</del></button>
                        <button type="button" class="md-btn" data-md="\`" title="Code"><code>C</code></button>
                        <button type="button" class="md-btn" data-md="[text](url)" title="Link">🔗</button>
                    </div>
                    <textarea class="field-input field-value" rows="3" placeholder="Field Value">${escapeHtml(value)}</textarea>
                </div>
            </div>
        `;
        $('#fields-container').append(fieldHtml);
        updatePreview();
    }

    function updateColorPreview() {
        const color = $('#embed-color').val();
        $('.color-preview').css('background-color', color);
    }

    function updatePreview() {
        const embedData = collectEmbedData();
        renderPreview(embedData);
    }

    function collectEmbedData() {
        const embedData = {};
        
        // Basic data
        const title = $('#embed-title').val();
        if (title) embedData.title = title;

        const description = $('#embed-description').val();
        if (description) embedData.description = description;

        const url = $('#embed-url').val();
        if (url) embedData.url = url;

        const color = $('#embed-color').val();
        if (color) embedData.color = parseInt(color.replace('#', ''), 16);

        const thumbnail = $('#thumbnail-url').val();
        if (thumbnail) embedData.thumbnail = { url: thumbnail };

        const image = $('#image-url').val();
        if (image) embedData.image = { url: image };

        // Author
        const authorName = $('#author-name').val();
        const authorUrl = $('#author-url').val();
        const authorIcon = $('#author-icon').val();
        if (authorName || authorUrl || authorIcon) {
            embedData.author = {};
            if (authorName) embedData.author.name = authorName;
            if (authorUrl) embedData.author.url = authorUrl;
            if (authorIcon) embedData.author.icon_url = authorIcon;
        }

        // Footer
        const footerText = $('#footer-text').val();
        const footerIcon = $('#footer-icon').val();
        if (footerText || footerIcon) {
            embedData.footer = {};
            if (footerText) embedData.footer.text = footerText;
            if (footerIcon) embedData.footer.icon_url = footerIcon;
        }

        // Fields
        const fields = [];
        $('.embed-field').each(function() {
            const name = $(this).find('.field-name').val();
            const value = $(this).find('.field-value').val();
            const inline = $(this).find('.inline-checkbox').is(':checked');
            
            if (name && value) {
                fields.push({ name, value, inline });
            }
        });
        if (fields.length > 0) embedData.fields = fields;

        return embedData;
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function parseMarkdown(text) {
        if (!text) return '';
        
        // Extract Discord-specific content first to preserve it during HTML escaping
        let discordElements = [];
        let elementIndex = 0;
        let html = text;
        
        // Discord custom emojis (render as images)
        html = html.replace(/<:([^:]+):(\d+)>/g, function(match, name, id) {
            debugLog('Found custom emoji in main editor: ' + match, 'info', {name, id});
            const placeholder = `DISCORDPLACEHOLDER${elementIndex}ENDPLACEHOLDER`;
            discordElements[elementIndex] = `<img src="https://cdn.discordapp.com/emojis/${id}.png" alt=":${name}:" class="discord-custom-emoji" style="width: 20px; height: 20px; vertical-align: middle; margin: 0 1px; object-fit: contain;">`;
            elementIndex++;
            return placeholder;
        });
        
        // Animated Discord emojis
        html = html.replace(/<a:([^:]+):(\d+)>/g, function(match, name, id) {
            debugLog('Found animated emoji in main editor: ' + match, 'info', {name, id});
            const placeholder = `DISCORDPLACEHOLDER${elementIndex}ENDPLACEHOLDER`;
            discordElements[elementIndex] = `<img src="https://cdn.discordapp.com/emojis/${id}.gif" alt=":${name}:" class="discord-custom-emoji" style="width: 20px; height: 20px; vertical-align: middle; margin: 0 1px; object-fit: contain;">`;
            elementIndex++;
            return placeholder;
        });
        
        // Role mentions
        html = html.replace(/<@&(\d+)>/g, function(match, roleId) {
            const placeholder = `DISCORDPLACEHOLDER${elementIndex}ENDPLACEHOLDER`;
            discordElements[elementIndex] = `<span class="discord-role-mention" style="color: #5865f2; background: rgba(88, 101, 242, 0.1); padding: 2px 4px; border-radius: 3px;">@role</span>`;
            elementIndex++;
            return placeholder;
        });
        
        // User mentions  
        html = html.replace(/<@!?(\d+)>/g, function(match, userId) {
            const placeholder = `DISCORDPLACEHOLDER${elementIndex}ENDPLACEHOLDER`;
            discordElements[elementIndex] = `<span class="discord-user-mention" style="color: #5865f2; background: rgba(88, 101, 242, 0.1); padding: 2px 4px; border-radius: 3px;">@user</span>`;
            elementIndex++;
            return placeholder;
        });
        
        // Channel mentions
        html = html.replace(/<#(\d+)>/g, function(match, channelId) {
            const placeholder = `DISCORDPLACEHOLDER${elementIndex}ENDPLACEHOLDER`;
            discordElements[elementIndex] = `<span class="discord-channel-mention" style="color: #5865f2; background: rgba(88, 101, 242, 0.1); padding: 2px 4px; border-radius: 3px;">#channel</span>`;
            elementIndex++;
            return placeholder;
        });
        
        // Now escape HTML for remaining content
        html = escapeHtml(html);
        
        // Standard markdown
        // Bold
        html = html.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        
        // Italic
        html = html.replace(/\*(.*?)\*/g, '<em>$1</em>');
        
        // Underline
        html = html.replace(/__(.*?)__/g, '<u>$1</u>');
        
        // Strikethrough
        html = html.replace(/~~(.*?)~~/g, '<del>$1</del>');
        
        // Code
        html = html.replace(/`(.*?)`/g, '<code>$1</code>');
        
        // Links - only allow safe protocols (http/https)
        html = html.replace(/\[([^\]]+)\]\(([^)]+)\)/g, function(match, linkText, url) {
            if (/^https?:\/\//i.test(url)) {
                return '<a href="' + url + '" target="_blank">' + linkText + '</a>';
            }
            return match; // Leave unsafe links as-is
        });
        
        // Line breaks
        html = html.replace(/\n/g, '<br>');
        
        // Restore Discord elements
        for (let i = 0; i < discordElements.length; i++) {
            html = html.replace(`DISCORDPLACEHOLDER${i}ENDPLACEHOLDER`, discordElements[i]);
        }
        
        debugLog('parseMarkdown result', 'info', {input: text.substring(0, 50), output: html.substring(0, 100)});
        
        return html;
    }

    function renderPreview(embedData) {
        let html = '';
        
        // Color bar
        const color = embedData.color ? '#' + embedData.color.toString(16).padStart(6, '0') : '#5865f2';
        html += `<div class="embed-color-bar" style="background-color: ${color};"></div>`;
        
        html += '<div class="embed-content">';
        
        // Author
        if (embedData.author) {
            html += `
                <div class="embed-author">
                    ${embedData.author.icon_url ? `<img src="${embedData.author.icon_url}" class="embed-author-icon" alt="Author Icon">` : ''}
                    ${embedData.author.url ? `<a href="${embedData.author.url}" class="embed-author-name" target="_blank">${parseMarkdown(embedData.author.name)}</a>` : `<span class="embed-author-name">${parseMarkdown(embedData.author.name)}</span>`}
                </div>
            `;
        }
        
        // Title
        if (embedData.title) {
            const titleHtml = embedData.url ? 
                `<a href="${embedData.url}" class="embed-title" target="_blank">${parseMarkdown(embedData.title)}</a>` :
                `<div class="embed-title">${parseMarkdown(embedData.title)}</div>`;
            html += titleHtml;
        }
        
        // Description
        if (embedData.description) {
            html += `<div class="embed-description">${parseMarkdown(embedData.description)}</div>`;
        }
        
        // Fields
        if (embedData.fields && embedData.fields.length > 0) {
            html += '<div class="embed-fields">';
            embedData.fields.forEach(field => {
                const fieldClass = field.inline ? 'embed-field inline' : 'embed-field';
                html += `
                    <div class="${fieldClass}">
                        <div class="embed-field-name">${parseMarkdown(field.name)}</div>
                        <div class="embed-field-value">${parseMarkdown(field.value)}</div>
                    </div>
                `;
            });
            html += '</div>';
        }
        
        // Thumbnail
        if (embedData.thumbnail && embedData.thumbnail.url) {
            html += `<div class="embed-thumbnail"><img src="${embedData.thumbnail.url}" alt="Thumbnail"></div>`;
        }
        
        // Image
        if (embedData.image && embedData.image.url) {
            html += `<div class="embed-image"><img src="${embedData.image.url}" alt="Image"></div>`;
        }
        
        // Footer
        if (embedData.footer) {
            html += `
                <div class="embed-footer">
                    ${embedData.footer.icon_url ? `<img src="${embedData.footer.icon_url}" class="embed-footer-icon" alt="Footer Icon">` : ''}
                    <span class="embed-footer-text">${escapeHtml(embedData.footer.text)}</span>
                </div>
            `;
        }
        
        html += '</div>';
        
        // Update the preview container and set the border color
        const $preview = $('#embed-preview');
        $preview.html(html);
        
        // Apply the border color to the outer container
        const borderColor = embedData.color ? '#' + embedData.color.toString(16).padStart(6, '0') : '#5865f2';
        $preview.css('border-left-color', borderColor);
    }

    function saveTemplate() {
        debugLog('saveTemplate function called');
        
        const templateName = $('#template-name').val();
        debugLog('Template name value', 'info', templateName);
        
        if (!templateName) {
            alert(__l('enterTemplateName', 'Please enter a template name.'));
            return;
        }

        const embedData = collectEmbedData();
        debugLog('Collected embed data', 'info', embedData);
        
        debugLog('Saving template', 'info', {templateName, embedData});

        const requestData = {
            action: 'save_embed_template',
            nonce: discordEmbed.nonce,
            template_name: templateName,
            embed_data: JSON.stringify(embedData)
        };
        
        debugLog('Request data', 'info', requestData);

        $.ajax({
            url: discordEmbed.ajaxUrl,
            method: 'POST',
            data: requestData,
            success: function(response) {
                debugLog('Save template response', 'success', response);
                
                if (response.success) {
                    alert(__l('templateSavedSuccessfully', 'Template saved successfully!'));
                    $('#template-name').val('');
                    // Template saved successfully - cards will update on page reload
                } else {
                    debugLog('Save template failed', 'error', response);
                    alert(__l('errorSavingTemplate', 'Error saving: %s').replace('%s', (response.data || 'Unknown error')));
                }
            },
            error: function(xhr, status, error) {
                debugLog('AJAX Error saving template', 'error', {xhr: xhr.responseText, status, error});
                alert(__l('errorSavingTemplateStatus', 'Error saving template. Status: %s, Error: %s').replace('%s', status).replace('%s', error));
            }
        });
    }

    function loadTemplateData(embedData, templateName) {
        debugLog('Loading template data into form', 'info', {embedData, templateName});
        
        // Fill form fields
        $('#embed-title').val(embedData.title || '');
        $('#embed-description').val(embedData.description || '');
        $('#embed-url').val(embedData.url || '');
        $('#thumbnail-url').val(embedData.thumbnail ? embedData.thumbnail.url || '' : '');
        $('#image-url').val(embedData.image ? embedData.image.url || '' : '');
        $('#template-name').val(templateName || '');
        
        if (embedData.color) {
            let colorValue = embedData.color;
            if (typeof colorValue === 'number') {
                colorValue = '#' + colorValue.toString(16).padStart(6, '0');
            } else if (typeof colorValue === 'string' && !colorValue.startsWith('#')) {
                colorValue = '#' + colorValue;
            }
            $('#embed-color').val(colorValue);
        } else {
            // Set default color if no color in template
            $('#embed-color').val('#5865f2');
        }
        
        // Author fields
        if (embedData.author) {
            $('#author-name').val(embedData.author.name || '');
            $('#author-url').val(embedData.author.url || '');
            $('#author-icon').val(embedData.author.icon_url || '');
        } else {
            // Clear author fields if no author data
            $('#author-name, #author-url, #author-icon').val('');
        }
        
        // Footer fields
        if (embedData.footer) {
            $('#footer-text').val(embedData.footer.text || '');
            $('#footer-icon').val(embedData.footer.icon_url || '');
        } else {
            // Clear footer fields if no footer data
            $('#footer-text, #footer-icon').val('');
        }
        
        // Clear existing fields
        $('#fields-container').empty();
        
        // Add fields
        if (embedData.fields && embedData.fields.length > 0) {
            embedData.fields.forEach(function(field) {
                addField(field.name, field.value, field.inline);
            });
        }
        
        updateColorPreview();
        
        // Use our enhanced updatePreview if available, otherwise fallback
        if (typeof window.updatePreview === 'function') {
            console.log('Using enhanced updatePreview function for template loading');
            window.updatePreview();
        } else {
            updatePreview();
        }
        
        // Trigger custom event for ui-fix.js
        $(document).trigger('template-loaded.uifix');
        
        debugLog('Template data loaded successfully!');
    }

    function clearForm() {
        $('#embed-title, #embed-description, #embed-url, #thumbnail-url, #image-url, #template-name').val('');
        $('#author-name, #author-url, #author-icon').val('');
        $('#footer-text, #footer-icon').val('');
        $('#embed-color').val('#000000');
        $('#fields-container').empty();
        updateColorPreview();
        updatePreview();
    }

    function loadTemplateById(templateId, templateName = 'Template') {
        if (!templateId) return;

        debugLog('Loading template by ID', 'info', {templateId, templateName});

        $.ajax({
            url: discordEmbed.ajaxUrl,
            method: 'POST',
            data: {
                action: 'load_embed_template',
                nonce: discordEmbed.nonce,
                template_id: templateId
            },
            success: function(response) {
                debugLog('Load template by ID response', 'success', response);
                
                if (response.success && response.data) {
                    const embedData = response.data;
                    
                    loadTemplateData(embedData, templateName);
                    
                    alert(__l('templateLoadedSuccessfully', 'Template loaded successfully!'));
                } else {
                    alert(__l('errorLoadingTemplate', 'Error loading template: %s').replace('%s', (response.data || 'Template not found')));
                }
            },
            error: function(xhr, status, error) {
                debugLog('Error loading template by ID', 'error', {status, error, response: xhr.responseText});
                alert(__l('errorLoadingTemplateGeneric', 'Error loading template.'));
            }
        });
    }

    function deleteTemplateById(templateId, templateName) {
        if (!templateId) {
            showToast(__l('templateIdMissing', 'Template ID missing.'), 'error');
            return;
        }

        // Bessere Bestätigung mit Custom Confirm
        showCustomConfirm(
            __l('deleteTemplateConfirm', 'Do you really want to delete the template "%s"?').replace('%s', templateName),
            function() {
                // Bestätigt - Template löschen
                debugLog('Deleting template', 'info', {templateId, templateName});

                $.ajax({
                    url: discordEmbed.ajaxUrl,
                    method: 'POST',
                    data: {
                        action: 'delete_embed_template',
                        nonce: discordEmbed.nonce,
                        template_id: templateId
                    },
                    success: function(response) {
                        debugLog('Delete template response', 'success', response);
                        if (response.success) {
                            showToast(__l('templateDeletedSuccessfully', `Template "${templateName}" deleted successfully!`), 'success');
                            // Template deleted - page reload needed to update cards
                            location.reload();
                        } else {
                            showToast(__l('errorDeleting', 'Error deleting: ') + (response.data || __l('unknownError', 'Unknown error')), 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                        debugLog('Error deleting template', 'error', {status, error, response: xhr.responseText});
                        showToast(__l('errorDeletingTemplate', 'Error deleting template.'), 'error');
                    }
                });
            },
            function() {
                // Abgebrochen
                showToast(__l('deletionCancelled', 'Deletion cancelled.'), 'info');
            }
        );
    }

    function sendDiscordMessage() {
        debugLog('sendDiscordMessage function called');
        
        const embedData = collectEmbedData();
        const webhookType = $('#webhook-type').val();
        
        debugLog('Sending Discord message', 'info', {webhookType, embedData});
        
        let requestData = {
            action: 'send_discord_message',
            nonce: discordEmbed.nonce,
            embed_data: JSON.stringify(embedData),
            webhook_type: webhookType
        };

        if (webhookType === 'server') {
            requestData.bot_token = $('#bot-token').val();
            requestData.channel_id = $('#channel-id').val();
            
            debugLog('Server webhook mode', 'info', {
                bot_token: requestData.bot_token ? 'PROVIDED' : 'MISSING',
                channel_id: requestData.channel_id || 'MISSING'
            });
            
            if (!requestData.bot_token || !requestData.channel_id) {
                alert(__l('fillBotTokenChannelId', 'Please fill in Bot Token and Channel ID.'));
                return;
            }
        } else {
            requestData.webhook_url = $('#webhook-url').val();
            
            debugLog('Channel webhook mode', 'info', {
                webhook_url: requestData.webhook_url ? 'PROVIDED' : 'MISSING'
            });
            
            if (!requestData.webhook_url) {
                alert(__l('enterWebhookUrl', 'Please enter a webhook URL.'));
                return;
            }
        }

        $('#send-embed').prop('disabled', true).text('Sending...');
        
        debugLog('Request data for Discord', 'info', requestData);

        debugLog('Sending Discord message', 'info', {webhookType, embedData});

        $.ajax({
            url: discordEmbed.ajaxUrl,
            method: 'POST',
            data: requestData,
            success: function(response) {
                debugLog('Send message response', 'success', response);
                if (response.success) {
                    alert(__l('messageSentSuccessfully', 'Message sent successfully!'));
                    loadMessageHistory(); // Reload history
                } else {
                    alert(__l('errorSendingMessage', 'Error sending: %s').replace('%s', (response.data || 'Unknown error')));
                }
            },
            error: function(xhr, status, error) {
                debugLog('AJAX Error sending message', 'error', {xhr: xhr.responseText, status, error});
                alert(__l('errorSendingMessageConsole', 'Error sending message. Check console for details.'));
            },
            complete: function() {
                $('#send-embed').prop('disabled', false).text('Send to Discord');
            }
        });
    }

    function loadMessageHistory(page = 1, filter = 'all', append = false) {
        if (isLoadingHistory) return;
        
        debugLog('Loading message history', 'info', {page, filter, append});
        isLoadingHistory = true;
        
        // Update button state
        const $loadMoreBtn = $('#load-more-messages');
        if (append) {
            $loadMoreBtn.prop('disabled', true).text('Lädt...');
        }
        
        $.ajax({
            url: discordEmbed.ajaxUrl,
            method: 'POST',
            data: {
                action: 'load_message_history',
                nonce: discordEmbed.nonce,
                page: page,
                per_page: 10,
                filter: filter
            },
            success: function(response) {
                debugLog('Message history response', 'success', response);
                isLoadingHistory = false;
                
                if (response.success && response.data) {
                    const data = response.data;
                    let html = '';
                    
                    if (data.messages && data.messages.length > 0) {
                        data.messages.forEach(function(message) {
                            const date = new Date(message.sent_at).toLocaleString('de-DE');
                            const hasMessageId = message.discord_message_id && message.channel_id;
                            
                            // Create a unique ID for this message item
                            const messageItemId = `msg-item-${message.id}`;
                            
                            const editButton = hasMessageId ? 
                                `<button type="button" class="button button-small load-for-edit" 
                                    data-message-id="${message.discord_message_id}" 
                                    data-channel-id="${message.channel_id}"
                                    data-message-item-id="${messageItemId}">
                                    Load in Editor
                                </button>` : '';
                            
                            let title = 'No Title';
                            try {
                                const embedData = JSON.parse(message.embed_data);
                                title = embedData.title || embedData.description?.substring(0, 50) + '...' || 'No Title';
                            } catch (e) {
                                title = 'Corrupted Data';
                            }
                            
                            const statusIcon = message.status === 'sent' ? '✅' : '❌';
                            const webhookType = message.webhook_type === 'server' ? 'Bot' : 'Webhook';
                            
                            html += `
                                <div class="history-item" data-message-id="${message.id}" id="${messageItemId}">
                                    <div class="history-header">
                                        <span class="history-date">${date}</span>
                                        <span class="history-status">${statusIcon} ${webhookType}</span>
                                        <span class="history-actions">${editButton}</span>
                                    </div>
                                    <div class="history-content">
                                        <strong>${escapeHtml(title)}</strong>
                                        ${message.channel_id ? `<br><small>Channel: ${escapeHtml(message.channel_id)}</small>` : ''}
                                        ${message.error_message ? `<br><small style="color: #d63384;">Error: ${escapeHtml(message.error_message)}</small>` : ''}
                                    </div>
                                    <script type="application/json" class="embed-data">${message.embed_data}</script>
                                </div>
                            `;
                        });
                    }
                    
                    if (append) {
                        $('#message-history').append(html);
                    } else {
                        $('#message-history').html(html || '<p>No messages found.</p>');
                    }
                    
                    // Update pagination controls
                    updateHistoryPagination(data);
                    
                    // Update stats
                    const showing = append ? 
                        `${(currentHistoryPage * data.per_page)} of ${data.total}` :
                        `${Math.min(data.per_page, data.total)} of ${data.total}`;
                    $('#history-stats-text').text(`${showing} messages displayed`);
                    
                } else {
                    $('#message-history').html('<p>Error loading history.</p>');
                    $('#history-stats-text').text('Error loading');
                }
            },
            error: function(xhr, status, error) {
                debugLog('Error loading message history', 'error', {status, error, response: xhr.responseText});
                isLoadingHistory = false;
                
                if (append) {
                    showToast(__l('errorLoadingMoreMessages', 'Error loading more messages.'), 'error');
                } else {
                    $('#message-history').html('<p>' + __l('errorLoadingHistory', 'Error loading history.') + '</p>');
                }
                
                $('#load-more-messages').prop('disabled', false).text('Load More');
            }
        });
    }
    
    function updateHistoryPagination(data) {
        const $loadMoreBtn = $('#load-more-messages');
        const $paginationInfo = $('#pagination-info-text');
        
        if (data.has_more) {
            $loadMoreBtn.show().prop('disabled', false).text('Load More');
        } else {
            $loadMoreBtn.hide();
        }
        
        const totalPages = Math.ceil(data.total / data.per_page);
        $paginationInfo.text(`Page ${data.page} of ${totalPages}`);
    }

    // Event handlers for pagination
    $('#load-more-messages').on('click', function() {
        if (!isLoadingHistory) {
            currentHistoryPage++;
            loadMessageHistory(currentHistoryPage, currentHistoryFilter, true);
        }
    });
    
    $('#history-filter').on('change', function() {
        const newFilter = $(this).val();
        currentHistoryFilter = newFilter;
        currentHistoryPage = 1;
        loadMessageHistory(1, newFilter, false);
    });
    
    $('#refresh-history').on('click', function() {
        currentHistoryPage = 1;
        loadMessageHistory(1, currentHistoryFilter, false);
        showToast(__l('messagesUpdated', 'Messages updated'), 'success');
    });
    
    // Load message from Discord URL
    $('#load-message').on('click', function() {
        const discordUrl = $('#discord-message-url').val().trim();
        
        if (!discordUrl) {
            alert(__l('enterDiscordMessageUrl', 'Please enter a Discord message URL.'));
            return;
        }
        
        // Validate URL format
        const urlPattern = /^https:\/\/discord\.com\/channels\/\d+\/\d+\/\d+$/;
        if (!urlPattern.test(discordUrl)) {
            alert(__l('invalidUrlFormat', 'Invalid URL. Format: https://discord.com/channels/server_id/channel_id/message_id'));
            return;
        }
        
        const botToken = $('#bot-token').val();
        if (!botToken) {
            alert(__l('botTokenRequired', 'Bot token is required to load messages.'));
            return;
        }
        
        const button = $(this);
        button.prop('disabled', true).text(discordEmbedL10n.loadingGeneric);
        
        debugLog('Loading message from URL', 'info', {url: discordUrl});
        
        $.post(discordEmbed.ajaxUrl, {
            action: 'load_message_by_url',
            nonce: discordEmbed.nonce,
            discord_url: discordUrl,
            bot_token: botToken
        }, function(response) {
            debugLog('Load message response', 'info', response);
            
            if (response.success && response.data.embed_data) {
                const embedData = response.data.embed_data;
                
                // Enter edit mode
                enterEditMode(response.data.message_id, response.data.channel_id);
                
                // Load embed data into form
                if (embedData.title) $('#embed-title').val(embedData.title);
                if (embedData.description) $('#embed-description').val(embedData.description);
                if (embedData.url) $('#embed-url').val(embedData.url);
                if (embedData.color) $('#embed-color').val('#' + embedData.color.toString(16).padStart(6, '0'));
                
                // Load footer data
                if (embedData.footer) {
                    if (embedData.footer.text) $('#footer-text').val(embedData.footer.text);
                    if (embedData.footer.icon_url) $('#footer-icon').val(embedData.footer.icon_url);
                }
                
                // Load author data
                if (embedData.author) {
                    if (embedData.author.name) $('#author-name').val(embedData.author.name);
                    if (embedData.author.url) $('#author-url').val(embedData.author.url);
                    if (embedData.author.icon_url) $('#author-icon').val(embedData.author.icon_url);
                }
                
                // Load fields
                clearFields();
                if (embedData.fields && embedData.fields.length > 0) {
                    embedData.fields.forEach(function(field) {
                        addField(field.name, field.value, field.inline || false);
                    });
                }
                
                // Load images
                if (embedData.image && embedData.image.url) $('#embed-image').val(embedData.image.url);
                if (embedData.thumbnail && embedData.thumbnail.url) $('#embed-thumbnail').val(embedData.thumbnail.url);
                
                // Trigger preview update
                if (typeof updatePreview === 'function') {
                    updatePreview();
                }
                
                alert(__l('messageLoadedSuccess', 'Message loaded successfully! You can now edit it.'));
                $('#discord-message-url').val(''); // Clear the URL field
                
            } else {
                alert('Error loading message: ' + (response.data || 'Unknown error'));
            }
        })
        .fail(function(xhr) {
            debugLog('Error loading message', 'error', xhr.responseText);
            showToast(__l('errorLoadingMessage', 'Error loading message. Check console for details.'), 'error');
        })
        .always(function() {
            button.prop('disabled', false).text('Load Message');
        });
    });
    
    // Load message for editing from history
    $(document).on('click', '.load-for-edit', function() {
        const messageId = $(this).data('message-id');
        const channelId = $(this).data('channel-id');
        const messageItemId = $(this).data('message-item-id');
        
        if (!messageId || !channelId || !messageItemId) {
            showToast(__l('messageCannotBeEdited', 'Message cannot be edited - missing IDs.'), 'error');
            return;
        }
        
        // Get embed data from the safe script tag
        const $messageItem = $('#' + messageItemId);
        const $embedDataScript = $messageItem.find('.embed-data');
        
        if ($embedDataScript.length === 0) {
            showToast(__l('embedDataNotFound', 'Embed data not found.'), 'error');
            return;
        }
        
        const embedDataString = $embedDataScript.text();
        
        debugLog('Loading message for edit', 'info', {
            messageId: messageId,
            channelId: channelId,
            embedDataString: embedDataString?.substring(0, 100) + '...'
        });
        
        try {
            // message.embed_data is already a JSON string from the database
            let embed;
            if (typeof embedDataString === 'string') {
                embed = JSON.parse(embedDataString);
            } else {
                embed = embedDataString;
            }
            
            debugLog('Parsed embed data for edit', 'success', embed);
            
            // Enter edit mode
            enterEditMode(messageId, channelId);
            
            // Load embed data into form
            if (embed.title) $('#embed-title').val(embed.title);
            if (embed.description) $('#embed-description').val(embed.description);
            if (embed.url) $('#embed-url').val(embed.url);
            if (embed.color) {
                // Handle color as number or hex string
                let colorValue = embed.color;
                if (typeof colorValue === 'number') {
                    colorValue = '#' + colorValue.toString(16).padStart(6, '0');
                } else if (typeof colorValue === 'string' && !colorValue.startsWith('#')) {
                    colorValue = '#' + colorValue;
                }
                $('#embed-color').val(colorValue);
            }
            
            // Load other data...
            if (embed.footer) {
                if (embed.footer.text) $('#footer-text').val(embed.footer.text);
                if (embed.footer.icon_url) $('#footer-icon').val(embed.footer.icon_url);
            }
            
            if (embed.author) {
                if (embed.author.name) $('#author-name').val(embed.author.name);
                if (embed.author.url) $('#author-url').val(embed.author.url);
                if (embed.author.icon_url) $('#author-icon').val(embed.author.icon_url);
            }
            
            // Load fields - with the same system as templates
            if (embed.fields && Array.isArray(embed.fields) && embed.fields.length > 0) {
                debugLog('Loading fields from history', 'info', {fieldsCount: embed.fields.length, fields: embed.fields});
                
                // First delete all existing fields
                $('#fields-container').empty();
                
                // Then add new fields using the same structure as addField()
                embed.fields.forEach(function(field, index) {
                    debugLog('Adding field from history', 'info', {index: index, field: field});
                    
                    // Use the same field HTML structure as addField() function for consistency
                    const fieldHtml = `
                        <div class="embed-field">
                            <div class="field-actions">
                                <button type="button" class="button remove-field">Remove</button>
                            </div>
                            <div class="field-inline-checkbox">
                                <input type="checkbox" class="field-input inline-checkbox" ${field.inline ? 'checked' : ''}>
                                <label>Display inline</label>
                            </div>
                            <div class="form-row">
                                <label>Field Name:</label>
                                <div class="markdown-toolbar">
                                    <button type="button" class="md-btn" data-md="**" title="Bold"><strong>B</strong></button>
                                    <button type="button" class="md-btn" data-md="*" title="Italic"><em>I</em></button>
                                    <button type="button" class="md-btn" data-md="__" title="Underlined"><u>U</u></button>
                                    <button type="button" class="md-btn" data-md="~~" title="Strikethrough"><del>S</del></button>
                                    <button type="button" class="md-btn" data-md="\`" title="Code"><code>C</code></button>
                                </div>
                                <input type="text" class="field-input field-name" value="${field.name || ''}" placeholder="Field Name">
                            </div>
                            <div class="form-row">
                                <label>Field Value:</label>
                                <div class="markdown-toolbar">
                                    <button type="button" class="md-btn" data-md="**" title="Bold"><strong>B</strong></button>
                                    <button type="button" class="md-btn" data-md="*" title="Italic"><em>I</em></button>
                                    <button type="button" class="md-btn" data-md="__" title="Underlined"><u>U</u></button>
                                    <button type="button" class="md-btn" data-md="~~" title="Strikethrough"><del>S</del></button>
                                    <button type="button" class="md-btn" data-md="\`" title="Code"><code>C</code></button>
                                    <button type="button" class="md-btn" data-md="[text](url)" title="Link">🔗</button>
                                </div>
                                <textarea class="field-input field-value" rows="3" placeholder="Field Value">${field.value || ''}</textarea>
                            </div>
                        </div>
                    `;
                    
                    $('#fields-container').append(fieldHtml);
                });
                
                debugLog('Fields added to container from history', 'success', {fieldsInContainer: $('#fields-container .embed-field').length});
            } else {
                debugLog('No fields in history message or fields empty', 'info');
                // Leere fields container falls keine Fields vorhanden
                $('#fields-container').empty();
            }
            
            if (embed.image && embed.image.url) $('#embed-image').val(embed.image.url);
            if (embed.thumbnail && embed.thumbnail.url) $('#embed-thumbnail').val(embed.thumbnail.url);
            
            // Update preview - ROBUST SYSTEM like templates
            debugLog('Updating preview after history load', 'info');
            
            // 1. Trigger change events on relevant fields
            $('#embed-title, #embed-description, #embed-url, #embed-color').trigger('change');
            
            // 2. Also trigger field events if fields were loaded
            setTimeout(function() {
                $('.field-name, .field-value, .field-inline').trigger('change');
                debugLog('Triggered field change events from history', 'info');
            }, 50);
            
            // 3. Call updatePreview directly if available
            setTimeout(function() {
                if (typeof window.updatePreview === 'function') {
                    debugLog('Calling window.updatePreview() from history', 'info');
                    window.updatePreview();
                } else if (typeof updatePreview === 'function') {
                    debugLog('Calling updatePreview() from history', 'info');
                    updatePreview();
                } else {
                    debugLog('updatePreview not found from history, trying fallback', 'warning');
                    // 4. Last fallback: trigger change events
                    $('#embed-description').trigger('input');
                }
            }, 150);
            
            showToast(__l('messageLoadedForEditing', 'Message loaded for editing!'), 'success');
            
        } catch (error) {
            debugLog('Error parsing embed data for edit', 'error', {
                error: error.toString(),
                embedDataString: embedDataString,
                embedDataType: typeof embedDataString,
                embedDataLength: embedDataString ? embedDataString.length : 0,
                embedDataSample: embedDataString ? embedDataString.substring(0, 100) : 'null'
            });
            showToast(__l('errorLoadingMessageForEditing', 'Error loading message for editing: ') + error.message, 'error');
        }
    });
    
    // Enter edit mode
    function enterEditMode(messageId, channelId) {
        $('#editing-message-id').val(messageId);
        $('#editing-channel-id').val(channelId);
        $('#send-embed').hide();
        $('#update-message').show();
        $('#cancel-edit').show();
        $('.message-edit-section').addClass('editing-active');
        debugLog('Entered edit mode', 'info', {messageId, channelId});
    }
    
    // Exit edit mode
    function exitEditMode() {
        $('#editing-message-id').val('');
        $('#editing-channel-id').val('');
        $('#send-embed').show();
        $('#update-message').hide();
        $('#cancel-edit').hide();
        $('.message-edit-section').removeClass('editing-active');
        debugLog('Exited edit mode', 'info');
    }
    
    // Cancel edit button
    $('#cancel-edit').on('click', function() {
        showCustomConfirm(
            'Cancel editing? All unsaved changes will be lost.',
            function() {
                // Confirm - exit edit mode
                exitEditMode();
                showToast(__l('editingCancelled', 'Editing cancelled.'), 'info');
            },
            function() {
                // Cancel - do nothing, stay in edit mode
                showToast(__l('editingContinues', 'Editing continues.'), 'info');
            }
        );
    });
    
    // Update message button
    $('#update-message').on('click', function() {
        const messageId = $('#editing-message-id').val();
        const channelId = $('#editing-channel-id').val();
        const botToken = $('#bot-token').val();
        
        if (!messageId || !channelId || !botToken) {
            showToast(__l('missingDataForEditing', 'Missing data for editing.'), 'error');
            return;
        }
        
        const embedData = collectEmbedData();
        const button = $(this);
        button.prop('disabled', true).text('Bearbeite...');
        
        debugLog('Updating Discord message', 'info', {messageId, channelId});
        
        $.post(discordEmbed.ajaxUrl, {
            action: 'edit_discord_message',
            nonce: discordEmbed.nonce,
            message_id: messageId,
            channel_id: channelId,
            bot_token: botToken,
            embed_data: JSON.stringify({embeds: [embedData]})
        }, function(response) {
            debugLog('Update message response', 'info', response);
            
            if (response.success) {
                showToast(__l('messageEditedSuccessfully', 'Message edited successfully!'), 'success');
                exitEditMode();
                loadMessageHistory(); // Reload history
            } else {
                showToast(__l('errorEditing', 'Error editing: ') + (response.data || __l('unknownError', 'Unknown error')), 'error');
            }
        })
        .fail(function(xhr) {
            debugLog('Error updating message', 'error', xhr.responseText);
            showToast(__l('errorEditingMessage', 'Error editing message. See console for details.'), 'error');
        })
        .always(function() {
            button.prop('disabled', false).text('Edit Message');
        });
    });
    
    // Helper function to clear all fields
    function clearFields() {
        $('#fields-container').empty();
    }

    // Timestamp functionality
    initializeTimestampControls();

    function initializeTimestampControls() {
        debugLog('Initializing timestamp controls');

        // "Jetzt" Button - Set current date and time
        $('#current-time').on('click', function() {
            const now = new Date();
            const localDateTime = formatDateTimeLocal(now);
            $('#embed-timestamp').val(localDateTime);
            
            // Also update Unix timestamp field
            const unixTimestamp = Math.floor(now.getTime() / 1000);
            $('#unix-timestamp').val(unixTimestamp);
            
            // Update the display with German formatting
            updateTimestampDisplay(now);
            
            debugLog('Current time set', 'success', {
                localDateTime: localDateTime,
                unixTimestamp: unixTimestamp,
                formatted: formatGermanDateTime(now)
            });
        });

        // Hammertime.cyou Button - Generate Discord timestamp and open in new tab
        $('#hammertime-link').on('click', function() {
            const timestampInput = $('#embed-timestamp').val();
            
            if (!timestampInput) {
                showToast(__l('selectDateTimeFirst', 'Please select a date and time first.'), 'warning');
                return;
            }
            
            const selectedDate = new Date(timestampInput);
            const unixTimestamp = Math.floor(selectedDate.getTime() / 1000);
            
            // Generate Discord timestamp formats
            const discordTimestamps = generateDiscordTimestamps(unixTimestamp);
            
            // Show modal with Discord timestamp options
            showDiscordTimestampModal(discordTimestamps, formatGermanDateTime(selectedDate));
            
            debugLog('Discord timestamps generated', 'success', {
                selectedDate: selectedDate.toISOString(),
                unixTimestamp: unixTimestamp,
                timestamps: discordTimestamps
            });
        });

        // Unix timestamp input handler
        $('#unix-timestamp').on('change blur', function() {
            const unixTimestamp = parseInt($(this).val());
            
            if (isNaN(unixTimestamp) || unixTimestamp <= 0) {
                return;
            }
            
            const date = new Date(unixTimestamp * 1000);
            const localDateTime = formatDateTimeLocal(date);
            $('#embed-timestamp').val(localDateTime);
            
            updateTimestampDisplay(date);
            
            debugLog('Unix timestamp converted', 'success', {
                unixTimestamp: unixTimestamp,
                localDateTime: localDateTime,
                formatted: formatGermanDateTime(date)
            });
        });

        // Datetime-local input handler
        $('#embed-timestamp').on('change', function() {
            const timestampInput = $(this).val();
            
            if (!timestampInput) {
                $('#unix-timestamp').val('');
                $('#timestamp-display').text('');
                return;
            }
            
            const selectedDate = new Date(timestampInput);
            const unixTimestamp = Math.floor(selectedDate.getTime() / 1000);
            $('#unix-timestamp').val(unixTimestamp);
            
            updateTimestampDisplay(selectedDate);
            
            debugLog('Datetime input changed', 'success', {
                input: timestampInput,
                unixTimestamp: unixTimestamp,
                formatted: formatGermanDateTime(selectedDate)
            });
        });
    }

    // Helper function to format date for datetime-local input
    function formatDateTimeLocal(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        
        return `${year}-${month}-${day}T${hours}:${minutes}`;
    }

    // Helper function to format date and time
    function formatGermanDateTime(date) {
        const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        const months = [
            'January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December'
        ];
        
        const dayName = days[date.getDay()];
        const day = date.getDate();
        const month = months[date.getMonth()];
        const year = date.getFullYear();
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        
        return `${dayName}, ${day}. ${month} ${year} ${hours}:${minutes}`;
    }

    // Helper function to generate Discord timestamp formats
    function generateDiscordTimestamps(unixTimestamp) {
        return {
            short_time: `<t:${unixTimestamp}:t>`,      // 16:20
            long_time: `<t:${unixTimestamp}:T>`,       // 16:20:30
            short_date: `<t:${unixTimestamp}:d>`,      // 20/04/2021
            long_date: `<t:${unixTimestamp}:D>`,       // 20 April 2021
            short_datetime: `<t:${unixTimestamp}:f>`,  // 20 April 2021 16:20
            long_datetime: `<t:${unixTimestamp}:F>`,   // Tuesday, 20 April 2021 16:20
            relative: `<t:${unixTimestamp}:R>`         // 2 months ago
        };
    }

    // Helper function to update timestamp display
    function updateTimestampDisplay(date) {
        const formatted = formatGermanDateTime(date);
        let displayElement = $('#timestamp-display');
        
        if (displayElement.length === 0) {
            // Create display element if it doesn't exist
            $('.timestamp-controls').append('<div id="timestamp-display" style="margin-top: 5px; font-size: 12px; color: #666;"></div>');
            displayElement = $('#timestamp-display');
        }
        
        displayElement.text(formatted);
    }

    // Function to show Discord timestamp modal
    function showDiscordTimestampModal(timestamps, germanFormatted) {
        const modalHtml = `
            <div id="timestamp-modal" style="
                position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
                background: rgba(0,0,0,0.5); z-index: 10000; display: flex; 
                align-items: center; justify-content: center;
            ">
                <div style="
                    background: white; padding: 20px; border-radius: 8px; 
                    max-width: 600px; width: 90%; max-height: 80vh; overflow-y: auto;
                    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
                ">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <h3 style="margin: 0; color: #333;">Discord Timestamps</h3>
                        <button id="close-timestamp-modal" style="
                            background: #f1f1f1; border: none; border-radius: 4px; 
                            padding: 5px 10px; cursor: pointer; font-size: 16px;
                        ">&times;</button>
                    </div>
                    <p style="margin-bottom: 15px; color: #666;">
                        <strong>Ausgewählte Zeit:</strong> ${germanFormatted}
                    </p>
                    <div style="margin-bottom: 20px;">
                        <h4 style="margin-bottom: 10px; color: #333;">Discord Timestamp Formats:</h4>
                        <div style="display: grid; gap: 10px;">
                            ${Object.entries(timestamps).map(([format, code]) => `
                                <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px; background: #f9f9f9; border-radius: 4px;">
                                    <div>
                                        <strong>${getFormatDescription(format)}:</strong>
                                        <code style="margin-left: 10px; background: #e8e8e8; padding: 2px 4px; border-radius: 2px;">${code}</code>
                                    </div>
                                    <button class="copy-timestamp" data-timestamp="${code}" style="
                                        background: #0073aa; color: white; border: none; 
                                        padding: 4px 8px; border-radius: 3px; cursor: pointer; font-size: 12px;
                                    ">Kopieren</button>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                    <div style="text-align: center; margin-top: 20px;">
                        <button id="open-hammertime" style="
                            background: #7289da; color: white; border: none; 
                            padding: 10px 20px; border-radius: 4px; cursor: pointer; margin-right: 10px;
                        ">Hammertime.cyou öffnen</button>
                        <button id="close-timestamp-modal-btn" style="
                            background: #666; color: white; border: none; 
                            padding: 10px 20px; border-radius: 4px; cursor: pointer;
                        ">Schließen</button>
                    </div>
                </div>
            </div>
        `;
        
        $('body').append(modalHtml);
        
        // Copy timestamp functionality
        $('.copy-timestamp').on('click', function() {
            const timestamp = $(this).data('timestamp');
            navigator.clipboard.writeText(timestamp).then(function() {
                const btn = $(this);
                const originalText = btn.text();
                btn.text('Copied!').css('background', '#28a745');
                setTimeout(function() {
                    btn.text(originalText).css('background', '#0073aa');
                }, 1500);
            }.bind(this));
        });
        
        // Open Hammertime
        $('#open-hammertime').on('click', function() {
            const unixTimestamp = Math.floor(new Date($('#embed-timestamp').val()).getTime() / 1000);
            window.open(`https://hammertime.cyou/en-GB/${unixTimestamp}`, '_blank');
        });
        
        // Close modal
        $('#close-timestamp-modal, #close-timestamp-modal-btn').on('click', function() {
            $('#timestamp-modal').remove();
        });
        
        // Close on backdrop click
        $('#timestamp-modal').on('click', function(e) {
            if (e.target === this) {
                $(this).remove();
            }
        });
    }

    // Helper function to get format descriptions
    function getFormatDescription(format) {
        const descriptions = {
            short_time: 'Kurze Zeit',
            long_time: 'Lange Zeit',
            short_date: 'Kurzes Datum',
            long_date: 'Langes Datum',
            short_datetime: 'Kurz (Datum + Zeit)',
            long_datetime: 'Lang (Datum + Zeit)',
            relative: 'Relativ'
        };
        return descriptions[format] || format;
    }

    // Mark admin script initialized for other scripts (e.g., ui-fix.js)
    window.discordEmbedAdminInitialized = true;
});
