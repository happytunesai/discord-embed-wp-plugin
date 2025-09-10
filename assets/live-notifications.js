/**
 * Live Notifications JavaScript for Discord Embed Plugin
 * Handles Twitch and YouTube live notification functionality
 */

jQuery(document).ready(function($) {
    console.log('Live Notifications JS loaded');
    
    // Tab Switching
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        
        // Remove active class from all tabs and content
        $('.nav-tab').removeClass('nav-tab-active');
        $('.tab-content').removeClass('active').hide();
        
        // Add active class to clicked tab
        $(this).addClass('nav-tab-active');
        
        // Show corresponding content
        const targetTab = $(this).attr('href');
        $(targetTab).addClass('active').show();
        
        // Initialize live notifications if switching to that tab
        if (targetTab === '#tab-live-notifications') {
            initializeLiveNotifications();
            // Small delay to ensure elements are visible before updating preview
            setTimeout(function() {
                updateLivePreview();
            }, 200);
        }
    });
    
    // Initialize live notifications functionality
    function initializeLiveNotifications() {
        console.log('Initializing live notifications...');
        
        // Check StreamWeasels status
        checkStreamWeaselsStatus();
        
        // Load current settings
        loadLiveNotificationSettings();
        
        // Setup event handlers if not already setup
        setupLiveNotificationHandlers();
        
        // Initialize live preview
        initializeLivePreview();
    }
    
    // Check StreamWeasels integration status
    function checkStreamWeaselsStatus() {
        console.log('Checking StreamWeasels credentials...');
        
        $.ajax({
            url: discordEmbed.ajaxUrl,
            method: 'POST',
            data: {
                action: 'check_streamweasels_credentials',
                nonce: discordEmbed.nonce
            },
            success: function(response) {
                console.log('StreamWeasels status:', response);
                
                if (response.success) {
                    updateStreamWeaselsStatus(response.data);
                } else {
                    console.error('Failed to check StreamWeasels status:', response.data);
                }
            },
            error: function(xhr, status, error) {
                console.error('StreamWeasels status check error:', error);
                updateStreamWeaselsStatus({
                    twitch_available: false,
                    youtube_available: false
                });
            }
        });
    }
    
    // Update StreamWeasels status display
    function updateStreamWeaselsStatus(status) {
        const twitchIcon = status.twitch_available ? '✅' : '❌';
        const twitchText = status.twitch_available ? discordEmbedL10n.connectedAndReady : discordEmbedL10n.notConfiguredOrMissingCredentials;
        const twitchColor = status.twitch_available ? 'green' : 'red';
        
        const youtubeIcon = status.youtube_available ? '✅' : '❌';
        const youtubeText = status.youtube_available ? discordEmbedL10n.connectedAndReady : discordEmbedL10n.notConfiguredOrMissingCredentials;
        const youtubeColor = status.youtube_available ? 'green' : 'red';
        
        $('#twitch-api-status').text(twitchText).css('color', twitchColor);
        $('#twitch-api-icon').text(twitchIcon);
        
        $('#youtube-api-status').text(youtubeText).css('color', youtubeColor);
        $('#youtube-api-icon').text(youtubeIcon);
        
        // Enable/disable platform cards based on availability
        $('#twitch-enabled').prop('disabled', !status.twitch_available);
        $('#youtube-enabled').prop('disabled', !status.youtube_available);
        
        if (!status.twitch_available) {
            $('.platform-card:first').css('opacity', '0.6');
        }
        if (!status.youtube_available) {
            $('.platform-card:last').css('opacity', '0.6');
        }
    }
    
    // Load live notification settings
    function loadLiveNotificationSettings() {
        console.log('Loading live notification settings...');
        
        $.ajax({
            url: discordEmbed.ajaxUrl,
            method: 'POST',
            data: {
                action: 'load_live_notification_settings',
                nonce: discordEmbed.nonce
            },
            success: function(response) {
                    console.log('Live settings loaded:', response);

                    if (response.success) {
                        populateLiveSettings(response.data);
                    } else {
                        console.error('Failed to load live settings:', response.data);
                    }
                },
            error: function(xhr, status, error) {
                console.error('Live settings load error:', error);
            }
        });
    }
    
    // Populate form with loaded settings
    function populateLiveSettings(settings) {
        console.log('Populating live settings:', settings);
        
        // Global settings
        $('#live-notifications-enabled').prop('checked', settings.enabled || false);
        
        // Platform settings
        $('#twitch-enabled').prop('checked', settings.twitch_enabled || false);
        $('#youtube-enabled').prop('checked', settings.youtube_enabled || false);
        $('#twitch-channel').val(settings.twitch_channel || '');
        $('#youtube-channel-id').val(settings.youtube_channel_id || '');
        
        // Discord settings
        $('#live-webhook-type').val(settings.webhook_type || 'channel');
        $('#live-webhook-url').val(settings.webhook_url || '');
        $('#live-bot-token').val(settings.bot_token || '');
        $('#live-server-id').val(settings.server_id || '');
        $('#live-channel-id').val(settings.channel_id || '');
        
        // Advanced settings
        $('#cooldown-minutes').val(settings.cooldown_minutes || 10);
    // Emoji shortcodes are edited directly in the template description or inserted via picker
        
        // Embed template
        if (settings.embed_template) {
            try {
                const template = JSON.parse(settings.embed_template);
                $('#live-embed-title').val(unescapeHtml(template.title || ''));
                $('#live-embed-description').val(unescapeHtml(template.description || ''));
                $('#live-embed-footer').val(unescapeHtml(template.footer?.text || ''));
                $('#live-footer-icon').val(template.footer?.icon_url || '');
                $('#live-embed-image').val(template.image?.url || '');
                if (template.color) {
                    $('#live-embed-color').val('#' + template.color.toString(16).padStart(6, '0'));
                    updateLiveColorPreview();
                }
            } catch (e) {
                console.error('Error parsing embed template:', e);
            }
        }
        
        // Update UI based on webhook type
        updateLiveWebhookTypeDisplay();
        updateLiveStatus(settings.enabled);
        
        // Load roles if bot token and server ID are available
        if (settings.bot_token && settings.server_id) {
            loadLiveRoles(settings.bot_token, settings.server_id, settings.selected_roles);
        }
        
        // Update live preview after populating settings
        setTimeout(function() {
            updateLivePreview();
        }, 100);
    }
    
    // Setup event handlers for live notifications
    let liveHandlersSetup = false;
    function setupLiveNotificationHandlers() {
        if (liveHandlersSetup) return;
        liveHandlersSetup = true;
        
        console.log('Setting up live notification handlers...');
        
        // Refresh StreamWeasels status
        $('#refresh-streamweasels-status').on('click', function() {
            $(this).prop('disabled', true).text(discordEmbedL10n.updating);
            checkStreamWeaselsStatus();
            
            setTimeout(() => {
                $(this).prop('disabled', false).text(discordEmbedL10n.refreshStatus);
            }, 2000);
        });
        
        // Webhook type change
        $('#live-webhook-type').on('change', function() {
            updateLiveWebhookTypeDisplay();
        });
        
        // Color picker change
        $('#live-embed-color').on('change', function() {
            updateLiveColorPreview();
        });
        
        // Live preview update handlers
        $('#live-embed-title, #live-embed-description, #live-embed-footer, #live-footer-icon, #live-embed-image').on('input', function() {
            updateLivePreview();
        });
        
        $('#live-embed-color').on('change', function() {
            updateLiveColorPreview();
            updateLivePreview();
        });
        
        // Load channels button
        $('#load-live-channels').on('click', function() {
            loadLiveChannels();
        });
        
        // Platform test buttons
        $('.test-platform').on('click', function() {
            const platform = $(this).data('platform');
            testPlatform(platform);
        });
        
        // Save settings button
        $('#save-live-settings').on('click', function() {
            saveLiveNotificationSettings();
        });
        
        // Test notification button
        $('#test-live-notification').on('click', function() {
            testLiveNotification();
        });

        // Saved templates handlers
        $('#save-live-template').on('click', function() {
            saveCurrentAsTemplate();
        });

        // Load templates initially
        loadSavedTemplates();
        
        // Markdown toolbar for live description
        $('.md-btn[data-target="live-embed-description"]').on('click', function() {
            const markdownRaw = $(this).data('md');
            const markdown = (typeof markdownRaw === 'string') ? markdownRaw : '';
            const target = $('#' + $(this).data('target'));
            if (target && target.length) {
                insertMarkdown(target[0], markdown);
            } else {
                // fallback to active element
                const active = document.activeElement;
                if (active && (active.tagName === 'TEXTAREA' || (active.tagName === 'INPUT' && active.type === 'text'))) {
                    insertMarkdown(active, markdown);
                } else {
                    showNotification('Kein Ziel für Markdown gefunden', 'warning');
                }
            }
        });
    }
    
    // Update webhook type display
    function updateLiveWebhookTypeDisplay() {
        const webhookType = $('#live-webhook-type').val();
        
        if (webhookType === 'channel') {
            $('#live-channel-webhook-config').show();
            $('#live-server-webhook-config').hide();
        } else {
            $('#live-channel-webhook-config').hide();
            $('#live-server-webhook-config').show();
        }
    }
    
    // Update color preview
    function updateLiveColorPreview() {
        const color = $('#live-embed-color').val();
        $('#live-color-preview').css('background', color);
    }
    
    // Update live status indicator
    function updateLiveStatus(enabled) {
        if (enabled) {
            $('#live-status-text').text('Status: Aktiv');
            $('#live-status-dot').css('background', '#28a745');
        } else {
            $('#live-status-text').text('Status: Deaktiviert');
            $('#live-status-dot').css('background', '#dc3545');
        }
    }
    
    // Load channels for server webhook
    function loadLiveChannels() {
        const botToken = $('#live-bot-token').val();
        const serverId = $('#live-server-id').val();
        
        if (!botToken || !serverId) {
            alert(discordEmbedL10n.pleaseBotTokenServerId);
            return;
        }
        
        $('#load-live-channels').prop('disabled', true).text(discordEmbedL10n.loadingGeneric);
        
        $.ajax({
            url: discordEmbed.ajaxUrl,
            method: 'POST',
            data: {
                action: 'load_server_channels',
                nonce: discordEmbed.nonce,
                bot_token: botToken,
                server_id: serverId
            },
            success: function(response) {
                console.log('Channels loaded:', response);
                
                if (response.success && response.data.channels) {
                    const channelSelect = $('#live-channel-id');
                    channelSelect.empty().append('<option value="">Channel auswählen...</option>');
                    
                    response.data.channels.forEach(function(channel) {
                        channelSelect.append(`<option value="${channel.id}"># ${channel.name}</option>`);
                    });
                    
                    // Load roles as well
                    loadLiveRoles(botToken, serverId);
                } else {
                    alert(discordEmbedL10n.errorLoadingChannels.replace('%s', response.data || discordEmbedL10n.unknownError));
                }
            },
            error: function(xhr, status, error) {
                console.error('Channel load error:', error);
                alert(discordEmbedL10n.errorLoadingChannels.replace('%s', error));
            },
            complete: function() {
                $('#load-live-channels').prop('disabled', false).text(discordEmbedL10n.loadChannels);
            }
        });
    }
    
    // Load server roles
    function loadLiveRoles(botToken, serverId, selectedRoles = []) {
        console.log('Loading roles for live notifications...');
        
        $.ajax({
            url: discordEmbed.ajaxUrl,
            method: 'POST',
            data: {
                action: 'load_server_roles',
                nonce: discordEmbed.nonce,
                bot_token: botToken,
                server_id: serverId
            },
            success: function(response) {
                console.log('Live roles loaded:', response);
                
                if (response.success && response.data.roles) {
                    renderLiveRoleSelector(response.data.roles, selectedRoles);
                } else {
                    console.error('Failed to load live roles:', response.data);
                    $('#live-role-selector-container').html('<p style="color: red;">' + discordEmbedL10n.errorLoadingRoles + '</p>');
                }
            },
            error: function(xhr, status, error) {
                console.error('Live roles load error:', error);
                $('#live-role-selector-container').html('<p style="color: red;">' + discordEmbedL10n.errorLoadingRoles + '</p>');
            }
        });
    }

    // Load server emojis and show picker
    function loadServerEmojis() {
        const botToken = $('#live-bot-token').val();
        const serverId = $('#live-server-id').val();

        if (!botToken || !serverId) {
            alert('Bitte Bot Token und Server ID eingeben');
            return;
        }

    // Disable any open-emoji-picker buttons while loading
        // Do NOT overwrite an already-saved original label (the click handler saves it).
        $('.open-emoji-picker').each(function() {
            if (typeof $(this).data('original-text') === 'undefined') {
                $(this).data('original-text', $(this).text() || '😃');
            }
        });
    // set loading state
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
                        renderEmojiPicker(response.data.emojis);
                        // Use the centralized opener so body overflow is handled consistently
                        openEmojiPicker();
                    } else {
                        alert(discordEmbedL10n.errorLoadingEmojis + (response.data || 'Unknown error'));
                    }
                } catch (e) {
                    console.error('Error processing emoji response', e);
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
    }

    function renderEmojiPicker(emojis) {
        const grid = $('#emoji-picker-grid');
        grid.empty();
        emojis.forEach(function(e) {
            const code = (e.animated ? '<a:' : '<:') + e.name + ':' + e.id + '>';
            const el = $(`
                <div class="emoji-item" title="${e.name}" style="text-align:center; padding:6px; border:1px solid #eee; border-radius:6px; cursor:pointer;">
                    <img src="${e.url}" style="width:48px; height:48px; object-fit:cover; display:block; margin:0 auto 4px;">
                    <div style="font-size:11px; color:#333; word-break:break-word;">${e.name}</div>
                </div>
            `);
            el.on('click', function() {
                insertEmojiCodeAtCursor(code);
                // Ensure the centralized closer runs so page scrolling is restored
                closeEmojiPicker();
            });
            grid.append(el);
        });
    }

    // Insert code into focused textarea/input
    function insertEmojiCodeAtCursor(code) {
        const active = document.activeElement;
        if (!active) return;
        if (active.tagName === 'TEXTAREA' || (active.tagName === 'INPUT' && active.type === 'text')) {
            try {
                const start = (typeof active.selectionStart === 'number') ? active.selectionStart : 0;
                const end = (typeof active.selectionEnd === 'number') ? active.selectionEnd : start;
                const val = (typeof active.value === 'string') ? active.value : '';
                const newVal = val.substring(0, start) + code + val.substring(end);
                active.value = newVal;
                // set cursor after inserted code
                const pos = start + code.length;
                try { active.setSelectionRange(pos, pos); } catch (e) {}
                $(active).trigger('input');
            } catch (e) {
                console.error('Error inserting emoji code at cursor', e);
            }
        } else {
            // If nothing focused, append to description
            const desc = $('#live-embed-description')[0];
            if (desc) {
                desc.value = (desc.value || '') + '\n' + code;
                $(desc).trigger('input');
            }
        }
    }

    // Emoji modal handlers
        // Ensure this handler only triggers from within the Live Notifications tab toolbar to avoid double-calls
        $(document).on('click', '#tab-live-notifications .open-emoji-picker', function() {
            // Ensure we capture the ORIGINAL label for each picker BEFORE we set the loading text.
            // If we set the loading text first, the loader would capture that as the "original"
            // and then restore the loading label permanently. Capture first, then set loading.
            const $btn = $(this);
            $('.open-emoji-picker').each(function() { $(this).data('original-text', $(this).text() || '😃'); });
            // temporarily disable and show loading on all open-emoji-picker buttons for consistent UX
            $('.open-emoji-picker').prop('disabled', true).text(discordEmbedL10n.loadingEmojis);
            // call loader (loader will restore text on complete)
            loadServerEmojis();
        });
        $(document).on('click', '#close-emoji-picker', function() { closeEmojiPicker(); });
    $(document).on('click', '#close-emoji-picker', function() { closeEmojiPicker(); });

    function openEmojiPicker() {
        const modal = $('#emoji-picker-modal');
        // Use flex-style centered layout and prevent body scroll
    modal.css('display', 'flex');
    $('body').css('overflow', 'hidden');
        // ensure focus is on modal for accessibility
        modal.attr('tabindex', '-1').focus();
    }

    function closeEmojiPicker() {
        const modal = $('#emoji-picker-modal');
    // Hide using the same display manipulation as openEmojiPicker
    modal.css('display', 'none');
    // Explicitly restore page scrolling to avoid missing scrollbar in some browsers
    $('body').css('overflow', 'auto');
    }
    
    // Render role selector for live notifications
    function renderLiveRoleSelector(roles, selectedRoles = []) {
        console.log('Rendering live role selector with', roles.length, 'roles');
        
        let html = `
            <div class="live-roles-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px; margin-top: 15px;">
        `;
        
        roles.forEach(function(role) {
            const isSelected = selectedRoles.includes(role.id);
            const colorStyle = role.color > 0 ? `color: #${role.color.toString(16).padStart(6, '0')}` : '';
            const everyoneClass = role.everyone ? 'everyone-role' : '';
            
            html += `
                <label class="live-role-option ${everyoneClass}" style="display: flex; align-items: center; padding: 8px; border: 1px solid #ddd; border-radius: 6px; cursor: pointer; background: ${isSelected ? '#e3f2fd' : '#fff'};">
                    <input type="checkbox" 
                           class="live-role-checkbox" 
                           value="${role.id}" 
                           ${isSelected ? 'checked' : ''}
                           style="margin-right: 8px;">
                    <span style="${colorStyle}; font-weight: ${role.hoist ? 'bold' : 'normal'};">
                        ${role.everyone ? '@everyone' : role.name}
                    </span>
                    ${role.mentionable ? '' : '<span style="color: #999; font-size: 11px; margin-left: auto;">' + discordEmbedL10n.notMentionable + '</span>'}
                </label>
            `;
        });
        
        html += `</div>`;
        
        $('#live-role-selector-container').html(html);
        
        // Add change handlers
        $('.live-role-checkbox').on('change', function() {
            const label = $(this).closest('label');
            if ($(this).is(':checked')) {
                label.css('background', '#e3f2fd');
            } else {
                label.css('background', '#fff');
            }
            
            // Update live preview when roles change
            updateLivePreview();
        });
    }
    
    // Test platform status
    function testPlatform(platform) {
        const channel = platform === 'twitch' ? $('#twitch-channel').val() : $('#youtube-channel-id').val();
        
        if (!channel) {
            alert(`Bitte ${platform === 'twitch' ? 'Twitch Channel Name' : 'YouTube Channel ID'} eingeben`);
            return;
        }
        
        const button = $(`.test-platform[data-platform="${platform}"]`);
        button.prop('disabled', true).text('🧪 Teste...');
        
        $.ajax({
            url: discordEmbed.ajaxUrl,
            method: 'POST',
            data: {
                action: 'test_live_notification',
                nonce: discordEmbed.nonce,
                platform: platform,
                channel: channel
            },
            success: function(response) {
                console.log(`${platform} test result:`, response);
                
                if (response.success) {
                    const data = response.data;
                    if (data.is_live) {
                        alert(`✅ ${platform.toUpperCase()} ${discordEmbedL10n.testSuccessful}!\n\n${discordEmbedL10n.statusLive}\n${discordEmbedL10n.title}: ${data.title}\n${discordEmbedL10n.url}: ${data.url}`);
                    } else {
                        alert(`✅ ${platform.toUpperCase()} ${discordEmbedL10n.connectionSuccessful}!\n\n${discordEmbedL10n.statusOffline}\n${discordEmbedL10n.channelNotLive}`);
                    }
                } else {
                    alert(`❌ ${platform.toUpperCase()} ${discordEmbedL10n.testFailed}:\n${response.data || discordEmbedL10n.unknownError}`);
                }
            },
            error: function(xhr, status, error) {
                console.error(`${platform} test error:`, error);
                alert(`❌ ${platform.toUpperCase()} Test failed:\n${error}`);
            },
            complete: function() {
                button.prop('disabled', false).text(`🧪 ${platform === 'twitch' ? 'Twitch' : 'YouTube'} Status test`);
            }
        });
    }
    
    // Save live notification settings
    function saveLiveNotificationSettings() {
        console.log('Saving live notification settings...');
        
        const embedTemplate = {
            title: $('#live-embed-title').val() || 'ᴛᴡɪᴛᴄʜ � 🅻🅸🆅🅴 🎧',
            description: $('#live-embed-description').val() || '✨ Hey , STREAMER is live now at:\n📺 {url} !\n\nCome join the fun! 🚀',
            color: parseInt($('#live-embed-color').val().replace('#', ''), 16),
            footer: {
                text: $('#live-embed-footer').val() || 'Live'
            }
        };
        
        // Add footer icon if provided
        const footerIconUrl = $('#live-footer-icon').val();
        if (footerIconUrl) {
            embedTemplate.footer.icon_url = footerIconUrl;
        }
        
        // Add image if provided
        const imageUrl = $('#live-embed-image').val();
        if (imageUrl) {
            embedTemplate.image = { url: imageUrl };
        }
        
        const selectedRoles = [];
        $('.live-role-checkbox:checked').each(function() {
            selectedRoles.push($(this).val());
        });
        
    const settings = {
            enabled: $('#live-notifications-enabled').is(':checked'),
            twitch_enabled: $('#twitch-enabled').is(':checked'),
            youtube_enabled: $('#youtube-enabled').is(':checked'),
            twitch_channel: $('#twitch-channel').val(),
            youtube_channel_id: $('#youtube-channel-id').val(),
            webhook_type: $('#live-webhook-type').val(),
            webhook_url: $('#live-webhook-url').val(),
            bot_token: $('#live-bot-token').val(),
            server_id: $('#live-server-id').val(),
            channel_id: $('#live-channel-id').val(),
            embed_template: JSON.stringify(embedTemplate),
            selected_roles: selectedRoles,
            cooldown_minutes: parseInt($('#cooldown-minutes').val()) || 10
            ,
            // emoji fields removed - emojis are saved inside the embed template/description
        };
        
        console.log('Saving settings:', settings);
        
        $('#save-live-settings').prop('disabled', true).text('💾 Speichere...');
        
        $.ajax({
            url: discordEmbed.ajaxUrl,
            method: 'POST',
            data: Object.assign({
                action: 'save_live_notification_settings',
                nonce: discordEmbed.nonce
            }, settings),
            success: function(response) {
                console.log('Live settings save result:', response);
                
                if (response.success) {
                    alert('✅ Live-Benachrichtigung Einstellungen gespeichert!');
                    updateLiveStatus(settings.enabled);
                } else {
                    alert(`❌ ${discordEmbedL10n.saveError}: ` + (response.data || discordEmbedL10n.unknownError));
                }
            },
            error: function(xhr, status, error) {
                console.error('Live settings save error:', error);
                alert(`❌ ${discordEmbedL10n.saveError}: ` + error);
            },
            complete: function() {
                $('#save-live-settings').prop('disabled', false).text(discordEmbedL10n.saveSettings);
            }
        });
    }
    
    // Test live notification
    function testLiveNotification() {
        // Reuse server-side test endpoint but build using current form values so correct channel names are used
        const platform = $('#live-template-platform').val() || ($('#twitch-enabled').is(':checked') ? 'twitch' : 'youtube');
        const channel = platform === 'twitch' ? $('#twitch-channel').val() : $('#youtube-channel-id').val();

        if (!channel) {
            alert('Bitte Channel/ID für die Plattform eingeben.');
            return;
        }

        const button = $('#test-live-notification');
        button.prop('disabled', true).text('🧪 Test send...');

        $.ajax({
            url: discordEmbed.ajaxUrl,
            method: 'POST',
            data: {
                action: 'test_live_notification',
                nonce: discordEmbed.nonce,
                platform: platform,
                channel: channel
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data || discordEmbedL10n.testSuccessful);
                } else {
                    alert(`${discordEmbedL10n.error}: ` + (response.data || discordEmbedL10n.unknownError));
                }
            },
            error: function(xhr, status, error) {
                alert(`${discordEmbedL10n.error}: ` + error);
            },
            complete: function() {
                button.prop('disabled', false).text(discordEmbedL10n.sendTestNotification);
            }
        });
    }

    // Templates: load saved templates
    function loadSavedTemplates() {
        $.ajax({
            url: discordEmbed.ajaxUrl,
            method: 'POST',
            data: {
                action: 'load_live_notification_templates',
                nonce: discordEmbed.nonce
            },
            success: function(response) {
                if (response.success) {
                    renderSavedTemplates(response.data || []);
                } else {
                    $('#saved-live-templates').html('<div style="color:red;">' + discordEmbedL10n.errorLoadingTemplates + '</div>');
                }
            },
            error: function() {
                $('#saved-live-templates').html('<div style="color:red;">' + discordEmbedL10n.errorLoadingTemplates + '</div>');
            }
        });
    }

    function renderSavedTemplates(templates) {
        if (!templates || templates.length === 0) {
            $('#saved-live-templates').html('<div style="color:#666;">No saved templates found.</div>');
            return;
        }

        let html = '';
        templates.forEach(function(t) {
            const enabled = t.enabled == 1 || t.enabled === true;
            html += `<div style="display:flex; align-items:center; gap:8px; padding:8px; border:1px solid #e0e0e0; border-radius:6px; background:${enabled ? '#fff' : '#f6f6f6'};">
                <div style="flex:1;"> <strong>${t.name}</strong> <br><small style="color:#666;">${t.platform}</small></div>
                <div style="display:flex; gap:6px;">
                    <button type="button" class="button button-secondary load-live-template" data-id="${t.id}">${discordEmbedL10n.loadTemplate}</button>
                    <button type="button" class="button send-live-template" data-id="${t.id}">${discordEmbedL10n.send}</button>
                    <button type="button" class="button toggle-live-template" data-id="${t.id}" data-enabled="${enabled}">${enabled ? discordEmbedL10n.pause : discordEmbedL10n.activate}</button>
                    <button type="button" class="button button-danger delete-live-template" data-id="${t.id}">${discordEmbedL10n.delete}</button>
                </div>
            </div>`;
        });

        $('#saved-live-templates').html(html);

        // Attach handlers
        $('.load-live-template').on('click', function(e) {
            e.preventDefault();
            const id = $(this).data('id');
            loadTemplateToForm(id);
        });
        $('.delete-live-template').on('click', function(e) {
            e.preventDefault();
            const id = $(this).data('id');
            window.showCustomConfirm(discordEmbedL10n.confirmDeleteTemplate, function() {
                deleteTemplate(id);
            }, function() {
                // cancelled
            });
        });
        $('.toggle-live-template').on('click', function(e) {
            e.preventDefault();
            const id = $(this).data('id');
            const current = $(this).data('enabled') === true || $(this).data('enabled') === '1';
            toggleTemplate(id, !current, $(this));
        });
        $('.send-live-template').on('click', function(e) {
            e.preventDefault();
            const id = $(this).data('id');
            window.showCustomConfirm(discordEmbedL10n.confirmSendTemplateNow, function() {
                sendSavedTemplate(id);
            }, function() {
                // cancelled
            });
        });
    }

    function saveCurrentAsTemplate() {
        const name = $('#live-template-name').val().trim();
        if (!name) {
            alert('Please enter a template name');
            return;
        }

        const platform = $('#live-template-platform').val();
    const templateId = parseInt($('#live-template-id').val() || 0, 10);
    const saveAsNew = $('#save-live-as-new').is(':checked');

        // Build config from current form values
        const embedTemplate = {
            title: $('#live-embed-title').val(),
            description: $('#live-embed-description').val(),
            color: parseInt($('#live-embed-color').val().replace('#',''), 16),
            footer: { text: $('#live-embed-footer').val() }
        };
        
        // Add footer icon if provided
        const footerIconUrl = $('#live-footer-icon').val();
        if (footerIconUrl) {
            embedTemplate.footer.icon_url = footerIconUrl;
        }
        
        // Add image if provided
        const imageUrl = $('#live-embed-image').val();
        if (imageUrl) {
            embedTemplate.image = { url: imageUrl };
        }

        const selectedRoles = [];
        $('.live-role-checkbox:checked').each(function() { selectedRoles.push($(this).val()); });

        const config = {
            platform: platform,
            twitch_channel: $('#twitch-channel').val(),
            youtube_channel_id: $('#youtube-channel-id').val(),
            webhook_type: $('#live-webhook-type').val(),
            webhook_url: $('#live-webhook-url').val(),
            bot_token: $('#live-bot-token').val(),
            server_id: $('#live-server-id').val(),
            channel_id: $('#live-channel-id').val(),
            embed_template: JSON.stringify(embedTemplate),
            selected_roles: selectedRoles,
            cooldown_minutes: parseInt($('#cooldown-minutes').val()) || 10
        };

        $('#save-live-template').prop('disabled', true).text('Speichere...');

        $.ajax({
            url: discordEmbed.ajaxUrl,
            method: 'POST',
            data: {
                action: 'save_live_notification_template',
                nonce: discordEmbed.nonce,
                name: name,
                platform: platform,
                config: JSON.stringify(config),
                template_id: templateId,
                save_as_new: saveAsNew ? 1 : 0
            },
            success: function(response) {
                if (response.success) {
                    loadSavedTemplates();
                    $('#live-template-name').val('');
                    // reset template id when saved as new to avoid accidental overwrite
                    if (saveAsNew) { $('#live-template-id').val('0'); $('#save-live-as-new').prop('checked', false); }
                    alert('Template gespeichert');
                } else {
                    alert(`${discordEmbedL10n.error}: ` + (response.data || discordEmbedL10n.unknownError));
                }
            },
            error: function(xhr, status, error) {
                alert(`${discordEmbedL10n.error}: ` + error);
            },
            complete: function() {
                $('#save-live-template').prop('disabled', false).text(discordEmbedL10n.saveCurrentAsTemplate);
            }
        });
    }

    function loadTemplateToForm(id) {
        $.ajax({
            url: discordEmbed.ajaxUrl,
            method: 'POST',
            data: { action: 'load_live_notification_template', nonce: discordEmbed.nonce, template_id: id },
            success: function(response) {
                if (response.success && response.data) {
                    const data = response.data;
                    const cfg = data.config || {};

                    // Populate fields from template config
                    $('#live-template-name').val(data.name);
                    $('#live-template-id').val(data.id);
                    $('#live-template-platform').val(data.platform);

                    $('#twitch-channel').val(cfg.twitch_channel || '');
                    $('#youtube-channel-id').val(cfg.youtube_channel_id || '');
                    $('#live-webhook-type').val(cfg.webhook_type || 'channel');
                    $('#live-webhook-url').val(cfg.webhook_url || '');
                    $('#live-bot-token').val(cfg.bot_token || '');
                    $('#live-server-id').val(cfg.server_id || '');
                    $('#live-channel-id').val(cfg.channel_id || '');

                    if (cfg.embed_template) {
                        try {
                            const t = JSON.parse(cfg.embed_template);
                            $('#live-embed-title').val(unescapeHtml(t.title || ''));
                            $('#live-embed-description').val(unescapeHtml(t.description || ''));
                            $('#live-embed-footer').val(unescapeHtml(t.footer?.text || ''));
                            $('#live-footer-icon').val(t.footer?.icon_url || '');
                            $('#live-embed-image').val(t.image?.url || '');
                            if (t.color) { $('#live-embed-color').val('#' + t.color.toString(16).padStart(6,'0')); updateLiveColorPreview(); }
                        } catch (e) { console.error('Invalid embed_template in template', e); }
                    }

                    // Roles
                    if (cfg.selected_roles && cfg.selected_roles.length) {
                        // If bot token and server id are present, load roles immediately and preselect
                        const botTokenVal = $('#live-bot-token').val() || cfg.bot_token || '';
                        const serverIdVal = $('#live-server-id').val() || cfg.server_id || '';
                        if (botTokenVal && serverIdVal) {
                            loadLiveRoles(botTokenVal, serverIdVal, cfg.selected_roles);
                        } else {
                            // store selected for later when roles are loaded
                            $('#live-role-selector-container').data('preselected', cfg.selected_roles);
                        }
                    }

                    updateLiveWebhookTypeDisplay();
                    
                    // Update live preview after loading template
                    setTimeout(function() {
                        updateLivePreview();
                    }, 100);
                } else {
                    alert(discordEmbedL10n.errorLoadingTemplate);
                }
            },
            error: function() { alert(discordEmbedL10n.errorLoadingTemplate); }
        });
    }

    function deleteTemplate(id) {
        $.ajax({
            url: discordEmbed.ajaxUrl,
            method: 'POST',
            data: { action: 'delete_live_notification_template', nonce: discordEmbed.nonce, template_id: id },
            success: function(response) { if (response.success) loadSavedTemplates(); else alert(`${discordEmbedL10n.error}: ` + (response.data || discordEmbedL10n.unknownError)); },
            error: function() { alert(`${discordEmbedL10n.error}: ${discordEmbedL10n.deleteFailed}`); }
        });
    }

    function toggleTemplate(id, enabled, btn) {
        $.ajax({
            url: discordEmbed.ajaxUrl,
            method: 'POST',
            data: { action: 'toggle_live_notification_template', nonce: discordEmbed.nonce, template_id: id, enabled: enabled ? 1 : 0 },
            success: function(response) { if (response.success) { loadSavedTemplates(); } else { alert('Fehler: ' + (response.data || 'Unbekannter Fehler')); } },
            error: function() { alert(`${discordEmbedL10n.error}: ${discordEmbedL10n.toggleFailed}`); }
        });
    }

    function sendSavedTemplate(id) {
        $.ajax({
            url: discordEmbed.ajaxUrl,
            method: 'POST',
            data: { action: 'send_saved_live_notification', nonce: discordEmbed.nonce, template_id: id },
            success: function(response) {
                if (response.success) alert('Nachricht gesendet'); else alert('Fehler: ' + (response.data || 'Unbekannter Fehler'));
            },
            error: function() { alert(`${discordEmbedL10n.error}: ${discordEmbedL10n.sendFailed}`); }
        });
    }
    
    // Helper function for markdown insertion
    function insertMarkdown(textarea, markdown) {
        // Accept jQuery objects
        if (textarea && textarea.jquery) textarea = textarea[0];

        // Defensive: ensure textarea is valid
        if (!textarea || !(textarea instanceof HTMLTextAreaElement || (textarea instanceof HTMLInputElement && textarea.type === 'text'))) {
            const active = document.activeElement;
            if (active && (active.tagName === 'TEXTAREA' || (active.tagName === 'INPUT' && active.type === 'text'))) {
                textarea = active;
            } else {
                showNotification('Kein Ziel für Markdown gefunden', 'warning');
                return;
            }
        }

        const value = (typeof textarea.value === 'string') ? textarea.value : '';
        const start = (typeof textarea.selectionStart === 'number') ? textarea.selectionStart : value.length;
        const end = (typeof textarea.selectionEnd === 'number') ? textarea.selectionEnd : start;
        const safeStart = Math.max(0, Math.min(start, value.length));
        const safeEnd = Math.max(0, Math.min(end, value.length));
        const selectedText = value.substring(safeStart, safeEnd);

        let newText;
        if (markdown === '[text](url)') {
            newText = selectedText ? `[${selectedText}](url)` : '[text](url)';
        } else {
            newText = selectedText ? `${markdown}${selectedText}${markdown}` : `${markdown}text${markdown}`;
        }

        try { textarea.value = value.substring(0, safeStart) + newText + value.substring(safeEnd); } catch (e) {}

        // Set cursor position
        const newCursorPos = safeStart + newText.length;
        try { textarea.setSelectionRange(newCursorPos, newCursorPos); } catch (e) {}
        try { textarea.focus(); } catch (e) {}
        try { $(textarea).trigger('input'); } catch (e) {}
        
        // Trigger live preview update
        updateLivePreview();
    }
    
    // Show notification
    function showNotification(message, type = 'info') {
        // Reuse existing toast system if available, otherwise use alert
        if (window.showToast) {
            window.showToast(message, type);
        } else {
            alert(message);
        }
    }
    
    // Live Preview Functions for Live Notifications
    function updateLivePreview() {
        const liveEmbedData = collectLiveEmbedData();
        renderLivePreview(liveEmbedData);
    }
    
    function collectLiveEmbedData() {
        const embedData = {};
        
        // Basic data
        const title = $('#live-embed-title').val();
        if (title) {
            // Replace placeholders with sample data for preview
            embedData.title = title
                .replace(/\{platform\}/gi, 'Twitch')
                .replace(/\{title\}/gi, 'The Mana Bar - 3rd Anniversary Special 🎉')
                .replace(/STREAMER/gi, 'The Mana Bar');
        }

        const description = $('#live-embed-description').val();
        if (description) {
            // Add role mentions if selected (for preview)
            let processedDescription = description;
            const selectedRoles = [];
            $('.live-role-checkbox:checked').each(function() {
                const roleName = $(this).closest('label').find('span').text().trim();
                selectedRoles.push(`<@&${$(this).val()}>`);
            });
            
            if (selectedRoles.length > 0) {
                processedDescription = selectedRoles.join(' ') + '\n\n' + processedDescription;
            }
            
            // Replace placeholders with sample data for preview
            embedData.description = processedDescription
                .replace(/\{platform\}/gi, 'Twitch')
                .replace(/\{url\}/gi, 'https://twitch.tv/themanabar')
                .replace(/\{title\}/gi, 'The Mana Bar - 3rd Anniversary Special 🎉')
                .replace(/STREAMER/gi, 'The Mana Bar')
                .replace(/<@&(\d+)>/g, '<span style="color: #5865f2; background: rgba(88, 101, 242, 0.1); padding: 2px 4px; border-radius: 3px;">@role</span>');
        }

        const color = $('#live-embed-color').val();
        if (color) {
            embedData.color = parseInt(color.replace('#', ''), 16);
        }

        // Footer
        const footerText = $('#live-embed-footer').val();
        const footerIcon = $('#live-footer-icon').val();
        if (footerText || footerIcon) {
            embedData.footer = {};
            if (footerText) {
                embedData.footer.text = footerText
                    .replace(/\{platform\}/gi, 'Twitch')
                    .replace(/\{title\}/gi, 'The Mana Bar - 3rd Anniversary Special 🎉');
            }
            if (footerIcon) {
                embedData.footer.icon_url = footerIcon;
            }
        }

        // Image - handle {thumbnail} placeholder
        const image = $('#live-embed-image').val();
        if (image) {
            if (image.includes('{thumbnail}')) {
                // Use a sample Twitch thumbnail for preview
                embedData.image = { 
                    url: 'https://static-cdn.jtvnw.net/previews-ttv/live_user_themanabar-1920x1080.jpg'
                };
            } else {
                embedData.image = { url: image };
            }
        }

        return embedData;
    }
    
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function unescapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.innerHTML = text;
        return div.textContent || div.innerText || '';
    }

    function parseMarkdown(text) {
        if (!text) return '';
        
        console.log('parseMarkdown input:', text);
        
        // Start with the original text
        let html = text;
        
        // Discord custom emojis (render as images) - test this first
        html = html.replace(/<:([^:]+):(\d+)>/g, function(match, name, id) {
            console.log('Found custom emoji:', match, name, id);
            return `<img src="https://cdn.discordapp.com/emojis/${id}.png" alt=":${name}:" class="discord-custom-emoji" style="width: 20px; height: 20px; vertical-align: middle; margin: 0 1px; object-fit: contain;">`;
        });
        
        // Animated Discord emojis
        html = html.replace(/<a:([^:]+):(\d+)>/g, function(match, name, id) {
            console.log('Found animated emoji:', match, name, id);
            return `<img src="https://cdn.discordapp.com/emojis/${id}.gif" alt=":${name}:" class="discord-custom-emoji" style="width: 20px; height: 20px; vertical-align: middle; margin: 0 1px; object-fit: contain;">`;
        });
        
        // Role mentions
        html = html.replace(/<@&(\d+)>/g, '<span class="discord-role-mention" style="color: #5865f2; background: rgba(88, 101, 242, 0.1); padding: 2px 4px; border-radius: 3px;">@role</span>');
        
        // User mentions  
        html = html.replace(/<@!?(\d+)>/g, '<span class="discord-user-mention" style="color: #5865f2; background: rgba(88, 101, 242, 0.1); padding: 2px 4px; border-radius: 3px;">@user</span>');
        
        // Channel mentions
        html = html.replace(/<#(\d+)>/g, '<span class="discord-channel-mention" style="color: #5865f2; background: rgba(88, 101, 242, 0.1); padding: 2px 4px; border-radius: 3px;">#channel</span>');
        
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
        
        // Links
        html = html.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank">$1</a>');
        
        // Line breaks
        html = html.replace(/\n/g, '<br>');
        
        console.log('parseMarkdown output:', html);
        
        return html;
    }

    function renderLivePreview(embedData) {
        let html = '';
        
        // Color bar (handled by CSS border-left)
        html += '<div class="embed-content">';
        
        // Title
        if (embedData.title) {
            html += `<div class="embed-title">${parseMarkdown(embedData.title)}</div>`;
        }
        
        // Description
        if (embedData.description) {
            html += `<div class="embed-description">${parseMarkdown(embedData.description)}</div>`;
        }
        
        // Image
        if (embedData.image && embedData.image.url) {
            html += `<div class="embed-image"><img src="${embedData.image.url}" alt="Stream Preview" onError="this.style.display='none'"></div>`;
        }
        
        // Footer
        if (embedData.footer) {
            html += `
                <div class="embed-footer">
                    ${embedData.footer.icon_url ? `<img src="${embedData.footer.icon_url}" class="embed-footer-icon" alt="Footer Icon" onError="this.style.display='none'">` : ''}
                    <span class="embed-footer-text">${parseMarkdown(embedData.footer.text || '')}</span>
                </div>
            `;
        }
        
        html += '</div>';
        
        // Update the preview container and set the border color
        const $preview = $('#live-embed-preview');
        $preview.html(html);
        
        // Apply the border color to the embed container
        const borderColor = embedData.color ? '#' + embedData.color.toString(16).padStart(6, '0') : '#9146ff';
        $preview.css('border-left-color', borderColor);
        
        console.log('Live preview updated', embedData);
    }
    
    // Initialize live preview when settings are loaded
    let livePreviewInitialized = false;
    function initializeLivePreview() {
        if (livePreviewInitialized) return;
        livePreviewInitialized = true;
        
        // Set up initial preview with default values
        updateLivePreview();
        
        console.log('Live preview initialized');
    }
    
    console.log('Live Notifications JS setup complete');
});
