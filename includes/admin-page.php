<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$embeds_table = $wpdb->prefix . 'discord_embeds';
$templates = $wpdb->get_results("SELECT * FROM $embeds_table ORDER BY updated_at DESC");
?>

<div class="wrap">
    <h1>Discord Embed Creator (<?php echo esc_html(DISCORD_EMBED_VERSION); ?>)</h1>
    
    <!-- Tab Navigation -->
    <div class="nav-tab-wrapper" style="margin-bottom: 20px;">
        <a href="#tab-embed-creator" class="nav-tab nav-tab-active" id="tab-embed-creator-link">
            📝 <?php echo esc_html(__('Embed Creator', 'discord-embed-creator')); ?>
        </a>
        <a href="#tab-live-notifications" class="nav-tab" id="tab-live-notifications-link">
            🔴 <?php echo esc_html(__('Live Notifications', 'discord-embed-creator')); ?>
        </a>
    </div>
    
    <!-- Tab Content: Embed Creator -->
    <div id="tab-embed-creator" class="tab-content active">
    
    <!-- Gespeicherte Vorlagen Section - jetzt ganz oben -->
    <div class="form-section" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; margin-bottom: 20px;">
        <h2 style="margin-top: 0; color: #5865f2; border-bottom: 2px solid #5865f2; padding-bottom: 10px;">📋 <?php echo esc_html(__('Saved Templates', 'discord-embed-creator')); ?></h2>
        <?php if (empty($templates)): ?>
            <p style="color: #666; font-style: italic;"><?php echo esc_html(__('No templates saved yet. Create your first template below!', 'discord-embed-creator')); ?></p>
        <?php else: ?>
            <div id="saved-templates" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 15px; margin-top: 15px;">
                <?php foreach ($templates as $template): ?>
                    <div class="template-card" style="background: #f9f9f9; border: 1px solid #ddd; border-radius: 8px; padding: 15px; transition: all 0.2s ease;">
                        <h4 style="margin: 0 0 8px 0; color: #23282d;"><?php echo esc_html($template->name); ?></h4>
                        <p style="color: #666; font-size: 12px; margin: 0 0 10px 0;">
                            <?php echo esc_html(__('Created', 'discord-embed-creator')); ?>: <?php echo date('d.m.Y H:i', strtotime($template->created_at)); ?><br>
                            <?php echo esc_html(__('Modified', 'discord-embed-creator')); ?>: <?php echo date('d.m.Y H:i', strtotime($template->updated_at)); ?>
                        </p>
                        <div style="display: flex; gap: 8px;">
                            <button type="button" class="button button-primary load-template" 
                                    data-template-id="<?php echo esc_attr($template->id); ?>"
                                    data-template-name="<?php echo esc_attr($template->name); ?>"
                                    data-embed-data="<?php echo esc_attr($template->embed_data); ?>"
                                    style="flex: 1;">
                                <?php echo esc_html(__('Load', 'discord-embed-creator')); ?>
                            </button>
                            <button type="button" class="button button-secondary delete-template-card" 
                                    data-template-id="<?php echo esc_attr($template->id); ?>"
                                    data-template-name="<?php echo esc_attr($template->name); ?>"
                                    style="color: #dc3545;">
                                <?php echo esc_html(__('Delete', 'discord-embed-creator')); ?>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="discord-embed-container">
        <div class="embed-editor">
            <div class="editor-header">
                <h2><?php echo esc_html(__('Embed Editor', 'discord-embed-creator')); ?></h2>
            </div>
            
            <form id="embed-form">
                <div class="form-section">
                    <h3><?php echo esc_html(__('Template Settings', 'discord-embed-creator')); ?></h3>
                    <div class="form-row">
                        <label for="template-name"><?php echo esc_html(__('Template Name', 'discord-embed-creator')); ?>:</label>
                        <input type="text" id="template-name" name="template_name" placeholder="<?php echo esc_attr(__('Name for this template', 'discord-embed-creator')); ?>">
                    </div>
                </div>
                
                <div class="form-section">
                    <h3><?php echo esc_html(__('Webhook Settings', 'discord-embed-creator')); ?></h3>
                    
                    <!-- Webhook Status Indicators -->
                    <div class="webhook-status-panel" style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 15px; margin-bottom: 15px;">
                        <h4 style="margin: 0 0 10px 0; color: #495057;">🔧 <?php echo esc_html(__('Webhook Configuration Status', 'discord-embed-creator')); ?></h4>
                        <div class="status-grid" style="display: grid; grid-template-columns: auto 1fr auto; gap: 10px; align-items: center;">
                            <span class="status-label"><?php echo esc_html(__('Webhook URL', 'discord-embed-creator')); ?>:</span>
                            <span class="status-text" id="webhook-status-text"><?php echo esc_html(__('Not configured', 'discord-embed-creator')); ?></span>
                            <span class="status-icon" id="webhook-status-icon">❌</span>
                            
                            <span class="status-label"><?php echo esc_html(__('Bot Token', 'discord-embed-creator')); ?>:</span>
                            <span class="status-text" id="token-status-text"><?php echo esc_html(__('Not configured', 'discord-embed-creator')); ?></span>
                            <span class="status-icon" id="token-status-icon">❌</span>
                            
                            <span class="status-label"><?php echo esc_html(__('Server ID', 'discord-embed-creator')); ?>:</span>
                            <span class="status-text" id="server-status-text"><?php echo esc_html(__('Not configured', 'discord-embed-creator')); ?></span>
                            <span class="status-icon" id="server-status-icon">❌</span>
                        </div>
                        <button type="button" id="configure-webhook" class="button button-primary" style="margin-top: 15px; width: 100%;">
                            🛠️ <?php echo esc_html(__('Configure Webhook Settings', 'discord-embed-creator')); ?>
                        </button>
                    </div>
                    
                    <div class="form-row">
                        <label for="webhook-type"><?php echo esc_html(__('Webhook Type', 'discord-embed-creator')); ?>:</label>
                        <select id="webhook-type" name="webhook_type">
                            <option value="channel"><?php echo esc_html(__('Channel Webhook', 'discord-embed-creator')); ?></option>
                            <option value="server"><?php echo esc_html(__('Server Webhook (with Channel Selection)', 'discord-embed-creator')); ?></option>
                        </select>
                    </div>
                    
                    <!-- Hidden inputs for actual values -->
                    <input type="hidden" id="webhook-url" name="webhook_url">
                    <input type="hidden" id="bot-token" name="bot_token">
                    <input type="hidden" id="server-id" name="server_id">
                    
                    <div class="form-row" id="server-webhook-config" style="display: none;">
                        <button type="button" id="load-channels" class="button"><?php echo esc_html(__('Load Channels', 'discord-embed-creator')); ?></button>
                        <label for="channel-id"><?php echo esc_html(__('Select Channel', 'discord-embed-creator')); ?>:</label>
                        <select id="channel-id" name="channel_id">
                            <option value=""><?php echo esc_html(__('Select Channel...', 'discord-embed-creator')); ?></option>
                        </select>
                    </div>
                    <div class="form-row" id="channel-selection" style="display: none;">
                        <label for="target-channel"><?php echo esc_html(__('Target Channel ID', 'discord-embed-creator')); ?>:</label>
                        <input type="text" id="target-channel" name="target_channel" placeholder="<?php echo esc_attr(__('Channel ID (e.g. 123456789012345678)', 'discord-embed-creator')); ?>">
                        <small><?php echo esc_html(__('Right-click on Channel → "Copy ID" (Developer Mode must be enabled)', 'discord-embed-creator')); ?></small>
                    </div>
                </div>
                
                <!-- Webhook Configuration Modal/Tab -->
                <div id="webhook-config-modal" class="webhook-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 10000;">
                    <div class="webhook-modal-content" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; border-radius: 12px; padding: 30px; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto;">
                        <div class="modal-header" style="border-bottom: 2px solid #5865f2; padding-bottom: 15px; margin-bottom: 25px;">
                            <h2 style="margin: 0; color: #5865f2;">🔧 <?php echo esc_html(__('Configure Webhook Settings', 'discord-embed-creator')); ?></h2>
                            <button type="button" id="close-webhook-modal" style="position: absolute; top: 15px; right: 15px; background: none; border: none; font-size: 24px; cursor: pointer; color: #666;">×</button>
                        </div>
                        
                        <div class="webhook-config-form">
                            <div class="config-field">
                                <label for="modal-webhook-url"><?php echo esc_html(__('Discord Webhook URL', 'discord-embed-creator')); ?>:</label>
                                <input type="url" id="modal-webhook-url" placeholder="https://discord.com/api/webhooks/..." style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace;">
                                <small style="color: #666; display: block; margin-top: 5px;">
                                    ✅ <?php echo esc_html(__('Example', 'discord-embed-creator')); ?>: https://discord.com/api/webhooks/139934832377279692/p3UNSxvyROaWazJsOQ88H0KgWF7PLI9P4GpxUlHJSTaeIdfGh
                                </small>
                                <div class="validation-feedback" id="webhook-url-feedback"></div>
                            </div>
                            
                            <div class="config-field" style="margin-top: 20px;">
                                <label for="modal-bot-token"><?php echo esc_html(__('Bot Token (for Server Webhook)', 'discord-embed-creator')); ?>:</label>
                                <input type="password" id="modal-bot-token" placeholder="<?php echo esc_attr(__('Bot Token', 'discord-embed-creator')); ?>" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace;">
                                <small style="color: #666; display: block; margin-top: 5px;">
                                    ✅ <?php echo esc_html(__('Example', 'discord-embed-creator')); ?>: MTI5NTcdsfsaMDUzNTAxMjQwNA.G-LOQP.XVpRYFaN9jAHHQG2cZpBAJjYJWRNU-i6gWpK40
                                </small>
                                <div class="validation-feedback" id="bot-token-feedback"></div>
                            </div>
                            
                            <div class="config-field" style="margin-top: 20px;">
                                <label for="modal-server-id"><?php echo esc_html(__('Server ID (for Server Webhook)', 'discord-embed-creator')); ?>:</label>
                                <input type="text" id="modal-server-id" placeholder="<?php echo esc_attr(__('Server ID', 'discord-embed-creator')); ?>" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace;">
                                <small style="color: #666; display: block; margin-top: 5px;">
                                    ✅ <?php echo esc_html(__('Example', 'discord-embed-creator')); ?>: 7372148290723867332
                                </small>
                                <div class="validation-feedback" id="server-id-feedback"></div>
                            </div>
                            
                            <div class="validation-info" style="background: #e3f2fd; border-left: 4px solid #2196f3; padding: 15px; margin: 20px 0; border-radius: 4px;">
                                <h4 style="margin: 0 0 10px 0; color: #1976d2;">💡 <?php echo esc_html(__('Validation & Tips', 'discord-embed-creator')); ?></h4>
                                <ul style="margin: 0; padding-left: 20px; color: #555;">
                                    <li><strong><?php echo esc_html(__('Webhook URL', 'discord-embed-creator')); ?>:</strong> <?php echo esc_html(__('Must start with "https://discord.com/api/webhooks/"', 'discord-embed-creator')); ?></li>
                                    <li><strong><?php echo esc_html(__('Bot Token', 'discord-embed-creator')); ?>:</strong> <?php echo esc_html(__('Format: [Base64].[Part2].[Part3] separated by dots', 'discord-embed-creator')); ?></li>
                                    <li><strong><?php echo esc_html(__('Server ID', 'discord-embed-creator')); ?>:</strong> <?php echo esc_html(__('Numeric, 17-19 characters long', 'discord-embed-creator')); ?></li>
                                    <li><strong><?php echo esc_html(__('Tip', 'discord-embed-creator')); ?>:</strong> <?php echo esc_html(__('All fields are automatically validated', 'discord-embed-creator')); ?> ✅</li>
                                </ul>
                            </div>
                            
                            <div class="modal-actions" style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 25px; border-top: 1px solid #eee; padding-top: 20px;">
                                <button type="button" id="cancel-webhook-config" class="button"><?php echo esc_html(__('Cancel', 'discord-embed-creator')); ?></button>
                                <button type="button" id="test-webhook-config" class="button button-secondary">🧪 <?php echo esc_html(__('Test Connection', 'discord-embed-creator')); ?></button>
                                <button type="button" id="save-webhook-config" class="button button-primary">💾 <?php echo esc_html(__('Save', 'discord-embed-creator')); ?></button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3><?php echo esc_html(__('Embed Content', 'discord-embed-creator')); ?></h3>
                    
                    <div class="form-row">
                        <label for="embed-title"><?php echo esc_html(__('Title', 'discord-embed-creator')); ?>:</label>
                        <input type="text" id="embed-title" name="title" placeholder="<?php echo esc_attr(__('Embed Title', 'discord-embed-creator')); ?>">
                    </div>
                    
                    <div class="form-row">
                        <label for="embed-description"><?php echo esc_html(__('Description', 'discord-embed-creator')); ?>:</label>
                        <div class="markdown-toolbar">
                            <button type="button" class="md-btn" data-md="**" title="<?php echo esc_attr(__('Bold', 'discord-embed-creator')); ?>"><strong>B</strong></button>
                            <button type="button" class="md-btn" data-md="*" title="<?php echo esc_attr(__('Italic', 'discord-embed-creator')); ?>"><em>I</em></button>
                            <button type="button" class="md-btn" data-md="__" title="<?php echo esc_attr(__('Underlined', 'discord-embed-creator')); ?>"><u>U</u></button>
                            <button type="button" class="md-btn" data-md="~~" title="<?php echo esc_attr(__('Strikethrough', 'discord-embed-creator')); ?>"><del>S</del></button>
                            <button type="button" class="md-btn" data-md="`" title="<?php echo esc_attr(__('Code', 'discord-embed-creator')); ?>"><code>C</code></button>
                            <button type="button" class="md-btn" data-md="[text](url)" title="<?php echo esc_attr(__('Link', 'discord-embed-creator')); ?>">🔗</button>
                            <!-- Emoji loader for main editor -->
                            <button type="button" class="md-btn open-emoji-picker" title="<?php echo esc_attr(__('Open Emoji Picker', 'discord-embed-creator')); ?>">😃</button>
                        </div>
                        <textarea id="embed-description" name="description" rows="4" placeholder="<?php echo esc_attr(__('Embed Description (Markdown supported)', 'discord-embed-creator')); ?>"></textarea>
                        <small><?php echo esc_html(__('Supports', 'discord-embed-creator')); ?>: **<?php echo esc_html(__('bold', 'discord-embed-creator')); ?>**, *<?php echo esc_html(__('italic', 'discord-embed-creator')); ?>*, __<?php echo esc_html(__('underlined', 'discord-embed-creator')); ?>__, ~~<?php echo esc_html(__('strikethrough', 'discord-embed-creator')); ?>~~, `<?php echo esc_html(__('code', 'discord-embed-creator')); ?>`, [<?php echo esc_html(__('links', 'discord-embed-creator')); ?>](url)</small>
                        <div style="margin-top:8px;">
                            <label style="display:block; font-weight:600; margin-bottom:4px;"><?php echo esc_html(__('Emoji Format & Usage', 'discord-embed-creator')); ?></label>
                            <p style="color:#666; margin:0;"><?php echo wp_kses(__('Use either the full emoji format <code>&lt;:name:ID&gt;</code> or a short placeholder <code>:shortcode:</code>. Example: <code>&lt;:twitch_logo:944912608136417293&gt;</code> or <code>:twitch_logo:</code>.', 'discord-embed-creator'), array('code' => array())); ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Role Mentions Section -->
                <div class="form-section" id="role-mentions-section" style="background: #f0f8ff; border-left: 4px solid #5865f2;">
                    <h3 style="color: #5865f2; margin-top: 0;">🏷️ Role Mentions</h3>
                    <div class="form-row">
                        <label><?php echo esc_html(__('Select Roles', 'discord-embed-creator')); ?>:</label>
                        <div id="role-selector-container">
                            <p class="description" style="color: #666; font-style: italic; margin: 8px 0;">
                                <?php echo esc_html(__('Bot Token and Server ID are automatically detected. Roles are loaded automatically.', 'discord-embed-creator')); ?>
                            </p>
                        </div>
                    </div>
                    <div class="form-row">
                        <small class="description" style="font-size: 12px; color: #666;">
                            📝 <strong><?php echo esc_html(__('Tip', 'discord-embed-creator')); ?>:</strong> <?php echo esc_html(__('Roles are inserted as', 'discord-embed-creator')); ?> <code>&lt;@&amp;ROLEID&gt;</code>. 
                            <?php echo esc_html(__('Example', 'discord-embed-creator')); ?>: <code>&lt;@&amp;739772583179845653&gt;</code>
                        </small>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3><?php echo esc_html(__('Additional Embed Options', 'discord-embed-creator')); ?></h3>
                    
                    <div class="form-row">
                        <label for="embed-url"><?php echo esc_html(__('URL', 'discord-embed-creator')); ?>:</label>
                        <input type="url" id="embed-url" name="url" placeholder="https://example.com">
                    </div>
                    
                    <div class="form-row">
                        <label for="embed-color"><?php echo esc_html(__('Color', 'discord-embed-creator')); ?>:</label>
                        <input type="color" id="embed-color" name="color" value="#7289da">
                        <span class="color-preview"></span>
                    </div>
                    
                    <div class="form-row">
                        <label for="embed-timestamp"><?php echo esc_html(__('Timestamp', 'discord-embed-creator')); ?>:</label>
                        <div class="timestamp-controls">
                            <input type="datetime-local" id="embed-timestamp" name="timestamp">
                            <button type="button" id="current-time" title="<?php echo esc_attr(__('Set current date and time', 'discord-embed-creator')); ?>"><?php echo esc_html(__('Now', 'discord-embed-creator')); ?></button>
                            <input type="text" id="unix-timestamp" placeholder="<?php echo esc_attr(__('Unix Timestamp', 'discord-embed-creator')); ?>" title="<?php echo esc_attr(__('Enter Unix Timestamp or automatically generated', 'discord-embed-creator')); ?>">
                            <button type="button" id="hammertime-link" title="<?php echo esc_attr(__('Generate Discord Timestamps', 'discord-embed-creator')); ?>"><?php echo esc_html(__('Discord Timestamps', 'discord-embed-creator')); ?></button>
                            <div class="timestamp-help">
                                <small>💡 <?php echo esc_html(__('Tip', 'discord-embed-creator')); ?>: <?php echo esc_html(__('With "Discord Timestamps" you can generate various time format codes for Discord', 'discord-embed-creator')); ?></small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3><?php echo esc_html(__('Author', 'discord-embed-creator')); ?></h3>
                    <div class="form-row">
                        <label for="author-name"><?php echo esc_html(__('Author Name', 'discord-embed-creator')); ?>:</label>
                        <input type="text" id="author-name" name="author_name" placeholder="<?php echo esc_attr(__('Author Name', 'discord-embed-creator')); ?>">
                    </div>
                    <div class="form-row">
                        <label for="author-url"><?php echo esc_html(__('Author URL', 'discord-embed-creator')); ?>:</label>
                        <input type="url" id="author-url" name="author_url" placeholder="https://example.com">
                    </div>
                    <div class="form-row">
                        <label for="author-icon"><?php echo esc_html(__('Author Icon URL', 'discord-embed-creator')); ?>:</label>
                        <input type="url" id="author-icon" name="author_icon_url" placeholder="https://example.com/icon.png">
                    </div>
                </div>
                
                <div class="form-section">
                    <h3><?php echo esc_html(__('Footer', 'discord-embed-creator')); ?></h3>
                    <div class="form-row">
                        <label for="footer-text"><?php echo esc_html(__('Footer Text', 'discord-embed-creator')); ?>:</label>
                        <div class="markdown-toolbar">
                            <button type="button" class="md-btn" data-md="**" title="<?php echo esc_attr(__('Bold', 'discord-embed-creator')); ?>"><strong>B</strong></button>
                            <button type="button" class="md-btn" data-md="*" title="<?php echo esc_attr(__('Italic', 'discord-embed-creator')); ?>"><em>I</em></button>
                            <button type="button" class="md-btn" data-md="__" title="<?php echo esc_attr(__('Underlined', 'discord-embed-creator')); ?>"><u>U</u></button>
                            <button type="button" class="md-btn" data-md="~~" title="<?php echo esc_attr(__('Strikethrough', 'discord-embed-creator')); ?>"><del>S</del></button>
                            <button type="button" class="md-btn" data-md="`" title="<?php echo esc_attr(__('Code', 'discord-embed-creator')); ?>"><code>C</code></button>
                        </div>
                        <input type="text" id="footer-text" name="footer_text" placeholder="<?php echo esc_attr(__('Footer Text', 'discord-embed-creator')); ?>">
                    </div>
                    <div class="form-row">
                        <label for="footer-icon"><?php echo esc_html(__('Footer Icon URL', 'discord-embed-creator')); ?>:</label>
                        <input type="url" id="footer-icon" name="footer_icon_url" placeholder="https://example.com/icon.png">
                    </div>
                </div>
                
                <div class="form-section">
                    <h3><?php echo esc_html(__('Images', 'discord-embed-creator')); ?></h3>
                    <div class="form-row">
                        <label for="image-url"><?php echo esc_html(__('Image URL', 'discord-embed-creator')); ?>:</label>
                        <input type="url" id="image-url" name="image_url" placeholder="https://example.com/image.png">
                    </div>
                    <div class="form-row">
                        <label for="thumbnail-url"><?php echo esc_html(__('Thumbnail URL', 'discord-embed-creator')); ?>:</label>
                        <input type="url" id="thumbnail-url" name="thumbnail_url" placeholder="https://example.com/thumbnail.png">
                    </div>
                </div>
                
                <div class="form-section">
                    <h3><?php echo esc_html(__('Fields', 'discord-embed-creator')); ?></h3>
                    <div id="fields-container">
                        <!-- Fields will be dynamically added here -->
                    </div>
                    <button type="button" id="add-field" class="button"><?php echo esc_html(__('Add Field', 'discord-embed-creator')); ?></button>
                </div>
                
                <div class="form-actions">
                    <input type="hidden" id="embed-id" name="embed_id" value="">
                    <input type="hidden" id="editing-message-id" name="editing_message_id" value="">
                    <input type="hidden" id="editing-channel-id" name="editing_channel_id" value="">
                    <button type="button" id="save-template" class="button button-secondary"><?php echo esc_html(__('Save Template', 'discord-embed-creator')); ?></button>
                    <button type="button" id="send-embed" class="button button-primary"><?php echo esc_html(__('Send to Discord', 'discord-embed-creator')); ?></button>
                    <button type="button" id="update-message" class="button button-primary" style="display: none;"><?php echo esc_html(__('Edit Message', 'discord-embed-creator')); ?></button>
                    <button type="button" id="cancel-edit" class="button" style="display: none;"><?php echo esc_html(__('Cancel Edit', 'discord-embed-creator')); ?></button>
                </div>
                
                <div class="form-section message-edit-section">
                    <h3><?php echo esc_html(__('Edit Message', 'discord-embed-creator')); ?></h3>
                    <p class="description"><?php echo esc_html(__('Load an existing Discord message for editing', 'discord-embed-creator')); ?>:</p>
                    <div class="form-row">
                        <label for="discord-message-url"><?php echo esc_html(__('Discord Message URL', 'discord-embed-creator')); ?>:</label>
                        <input type="url" id="discord-message-url" name="discord_message_url" placeholder="https://discord.com/channels/server_id/channel_id/message_id">
                        <button type="button" id="load-message" class="button"><?php echo esc_html(__('Load Message', 'discord-embed-creator')); ?></button>
                    </div>
                </div>
            </form>
        </div>
        
        <div class="embed-preview">
            <h2><?php echo esc_html(__('Live Preview', 'discord-embed-creator')); ?></h2>
            <div class="discord-mockup">
                <div class="discord-message">
                    <div class="discord-avatar"></div>
                    <div class="discord-content">
                        <div class="discord-username">Webhook Bot</div>
                        <div class="discord-embed" id="embed-preview">
                            <!-- Preview will be rendered here -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="history-section">
        <h2><?php echo esc_html(__('Sent Messages', 'discord-embed-creator')); ?></h2>
        <div class="history-controls">
            <button type="button" id="refresh-history" class="button"><?php echo esc_html(__('Refresh', 'discord-embed-creator')); ?></button>
            <select id="history-filter">
                <option value="all"><?php echo esc_html(__('All Messages', 'discord-embed-creator')); ?></option>
                <option value="today"><?php echo esc_html(__('Today', 'discord-embed-creator')); ?></option>
                <option value="week"><?php echo esc_html(__('This Week', 'discord-embed-creator')); ?></option>
                <option value="month"><?php echo esc_html(__('This Month', 'discord-embed-creator')); ?></option>
            </select>
            <div class="history-stats">
                <span id="history-stats-text"><?php echo esc_html(__('Loading messages...', 'discord-embed-creator')); ?></span>
            </div>
        </div>
        <div class="history-content" id="message-history">
            <!-- History wird hier geladen -->
        </div>
        <div class="history-pagination">
            <button type="button" id="load-more-messages" class="button" style="display: none;"><?php echo esc_html(__('Load More', 'discord-embed-creator')); ?></button>
            <div class="pagination-info">
                <span id="pagination-info-text"></span>
            </div>
        </div>
    </div>
    
    <!-- Debug Console -->
    <div class="form-section">
        <h3>🔧 <?php echo esc_html(__('Debug Console', 'discord-embed-creator')); ?></h3>
        <div style="background: #f0f0f0; padding: 15px; border-radius: 4px; font-family: monospace;">
            <div>
                <strong><?php echo esc_html(__('Plugin Status', 'discord-embed-creator')); ?>:</strong> 
                <span style="color: green;">✅ <?php echo esc_html(sprintf(__('Version %s Active', 'discord-embed-creator'), DISCORD_EMBED_VERSION)); ?></span>
            </div>
            <div>
                <strong><?php echo esc_html(__('Database Tables', 'discord-embed-creator')); ?>:</strong>
                <ul style="margin: 5px 0; padding-left: 20px;">
                    <li><?php echo $wpdb->prefix; ?>discord_embeds (<?php echo count($templates); ?> <?php echo esc_html(__('Templates', 'discord-embed-creator')); ?>)</li>
                    <li><?php echo $wpdb->prefix; ?>discord_sent_messages</li>
                </ul>
            </div>
            <div>
                <strong><?php echo esc_html(__('JavaScript Console', 'discord-embed-creator')); ?>:</strong> 
                <?php echo esc_html(__('Open Browser Developer Tools (F12) for detailed logs', 'discord-embed-creator')); ?>
            </div>
            <div style="margin-top: 10px;">
                <button type="button" id="test-ajax" class="button">🧪 <?php echo esc_html(__('AJAX Test', 'discord-embed-creator')); ?></button>
                <button type="button" id="clear-console" class="button">🗑️ <?php echo esc_html(__('Clear Console', 'discord-embed-creator')); ?></button>
                <button type="button" id="export-debug" class="button">📋 <?php echo esc_html(__('Debug Export', 'discord-embed-creator')); ?></button>
            </div>
        </div>
        
        <div id="debug-output" style="background: #000; color: #0f0; padding: 10px; border-radius: 4px; font-family: monospace; font-size: 11px; max-height: 200px; overflow-y: auto; margin-top: 10px; display: none;">
            <div id="debug-content"></div>
        </div>
    </div>
    
    </div> <!-- End tab-embed-creator -->
    
    <!-- Tab Content: Live Notifications -->
    <div id="tab-live-notifications" class="tab-content" style="display: none;">
        
        <!-- StreamWeasels Integration Status -->
        <div class="form-section" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; margin-bottom: 20px;">
            <h2 style="margin-top: 0; color: #5865f2; border-bottom: 2px solid #5865f2; padding-bottom: 10px;">🔗 <?php echo esc_html(__('StreamWeasels Integration Status', 'discord-embed-creator')); ?></h2>
            
            <div id="streamweasels-status" style="display: grid; grid-template-columns: auto 1fr auto; gap: 15px; align-items: center;">
                <span class="status-label"><strong>Twitch API:</strong></span>
                <span class="status-text" id="twitch-api-status"><?php echo esc_html(__('Checking...', 'discord-embed-creator')); ?></span>
                <span class="status-icon" id="twitch-api-icon">⏳</span>
                
                <span class="status-label"><strong>YouTube API:</strong></span>
                <span class="status-text" id="youtube-api-status"><?php echo esc_html(__('Checking...', 'discord-embed-creator')); ?></span>
                <span class="status-icon" id="youtube-api-icon">⏳</span>
            </div>
            
            <div class="streamweasels-info" style="background: #e3f2fd; border-left: 4px solid #2196f3; padding: 15px; margin: 15px 0; border-radius: 4px;">
                <h4 style="margin: 0 0 10px 0; color: #1976d2;">💡 <?php echo esc_html(__('StreamWeasels Integration Info', 'discord-embed-creator')); ?></h4>
                <p style="margin: 0; color: #555;">
                    <?php echo esc_html(__('This plugin uses the API credentials from StreamWeasels plugin automatically. Make sure that StreamWeasels is configured and the APIs are connected.', 'discord-embed-creator')); ?>
                </p>
                <ul style="margin: 10px 0 0 20px; color: #555;">
                    <li><strong>Twitch:</strong> <?php echo esc_html(__('Requires Client ID and Access Token', 'discord-embed-creator')); ?></li>
                    <li><strong>YouTube:</strong> <?php echo esc_html(__('Requires API Key', 'discord-embed-creator')); ?></li>
                </ul>
            </div>
            
            <button type="button" id="refresh-streamweasels-status" class="button button-secondary">
                <?php echo esc_html(__('🔄 Refresh Status', 'discord-embed-creator')); ?>
            </button>
        </div>
        
        <!-- Live Notification Settings -->
        <div class="form-section" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; margin-bottom: 20px;">
            <h2 style="margin-top: 0; color: #5865f2; border-bottom: 2px solid #5865f2; padding-bottom: 10px;">⚙️ <?php echo esc_html(__('Live Notification Settings', 'discord-embed-creator')); ?></h2>
            
            <form id="live-notification-form" onsubmit="return false;">
                <!-- Global Enable/Disable -->
                <div class="form-row" style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                    <label style="display: flex; align-items: center; font-weight: bold; color: #495057; cursor: pointer;">
                        <input type="checkbox" id="live-notifications-enabled" name="enabled" style="margin-right: 10px; width: auto; height: auto; transform: scale(1.2);">
                        🚀 <?php echo esc_html(__('Enable Live Notifications', 'discord-embed-creator')); ?>
                    </label>
                    <small style="color: #666; display: block; margin-top: 8px;">
                        <?php echo esc_html(__('Automatic Discord notifications when your stream goes live (checked every 3 minutes)', 'discord-embed-creator')); ?>
                    </small>
                </div>
                
                <!-- Platform Settings -->
                <div class="platform-settings" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    
                    <!-- Twitch Settings -->
                    <div class="platform-card" style="background: #f0f8ff; border: 2px solid #9146ff; border-radius: 12px; padding: 20px;">
                        <h3 style="margin: 0 0 15px 0; color: #9146ff; display: flex; align-items: center;">
                            🟣 Twitch Integration
                        </h3>
                        
                        <div class="form-row">
                            <label style="display: flex; align-items: center; font-weight: bold; cursor: pointer;">
                                <input type="checkbox" id="twitch-enabled" name="twitch_enabled" style="margin-right: 8px; width: auto; height: auto; cursor: pointer;">
                                <?php echo esc_html(__('Enable Twitch Live Notifications', 'discord-embed-creator')); ?>
                            </label>
                        </div>
                        
                        <div class="form-row">
                            <label for="twitch-channel"><?php echo esc_html(__('Twitch Channel Name:', 'discord-embed-creator')); ?></label>
                            <input type="text" id="twitch-channel" name="twitch_channel" 
                                   placeholder="<?php echo esc_attr(__('yourusername', 'discord-embed-creator')); ?>" 
                                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            <small style="color: #666; display: block; margin-top: 4px;">
                                <?php echo esc_html(__('Only the username, without "twitch.tv/" - e.g. "shroud"', 'discord-embed-creator')); ?>
                            </small>
                        </div>
                        
                        <button type="button" class="test-platform" data-platform="twitch" 
                                style="background: #9146ff; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; margin-top: 10px;">
                            <?php echo esc_html(__('🧪 Test Twitch Status', 'discord-embed-creator')); ?>
                        </button>
                    </div>
                    
                    <!-- YouTube Settings -->
                    <div class="platform-card" style="background: #fff0f0; border: 2px solid #ff0000; border-radius: 12px; padding: 20px;">
                        <h3 style="margin: 0 0 15px 0; color: #ff0000; display: flex; align-items: center;">
                            🔴 YouTube Integration
                        </h3>
                        
                        <div class="form-row">
                            <label style="display: flex; align-items: center; font-weight: bold; cursor: pointer;">
                                <input type="checkbox" id="youtube-enabled" name="youtube_enabled" style="margin-right: 8px; width: auto; height: auto; cursor: pointer;">
                                <?php echo esc_html(__('Enable YouTube Live Notifications', 'discord-embed-creator')); ?>
                            </label>
                        </div>
                        
                        <div class="form-row">
                            <label for="youtube-channel-id"><?php echo esc_html(__('YouTube Channel ID:', 'discord-embed-creator')); ?></label>
                            <input type="text" id="youtube-channel-id" name="youtube_channel_id" 
                                   placeholder="UCxxxxxxxxxxxxxxxxxxxxxx" 
                                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            <small style="color: #666; display: block; margin-top: 4px;">
                                <?php echo esc_html(__('YouTube Channel ID (starts with UC...) - you can find it in YouTube Studio', 'discord-embed-creator')); ?>
                            </small>
                        </div>
                        
                        <button type="button" class="test-platform" data-platform="youtube" 
                                style="background: #ff0000; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; margin-top: 10px;">
                            <?php echo esc_html(__('🧪 Test YouTube Status', 'discord-embed-creator')); ?>
                        </button>
                    </div>
                </div>
                
                <!-- Discord Settings -->
                <div class="form-row">
                    <h3><?php echo esc_html(__('Discord Target Configuration', 'discord-embed-creator')); ?></h3>
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                        <label for="live-webhook-type"><?php echo esc_html(__('Webhook Type:', 'discord-embed-creator')); ?></label>
                        <select id="live-webhook-type" name="webhook_type" style="width: 100%; padding: 8px;">
                            <option value="channel"><?php echo esc_html(__('Channel Webhook URL', 'discord-embed-creator')); ?></option>
                            <option value="server"><?php echo esc_html(__('Bot Token + Channel Selection', 'discord-embed-creator')); ?></option>
                        </select>
                    </div>
                    
                    <!-- Channel Webhook Settings -->
                    <div id="live-channel-webhook-config" class="webhook-config-section" style="display: none;">
                        <label for="live-webhook-url"><?php echo esc_html(__('Discord Webhook URL:', 'discord-embed-creator')); ?></label>
                        <input type="url" id="live-webhook-url" name="webhook_url" 
                               placeholder="https://discord.com/api/webhooks/..." 
                               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace;">
                    </div>
                    
                    <!-- Server Webhook Settings -->
                    <div id="live-server-webhook-config" class="webhook-config-section" style="display: none;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                            <div>
                                <label for="live-bot-token"><?php echo esc_html(__('Bot Token:', 'discord-embed-creator')); ?></label>
                                <input type="password" id="live-bot-token" name="bot_token" 
                                       placeholder="<?php echo esc_attr(__('Bot Token', 'discord-embed-creator')); ?>" 
                                       style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace;">
                            </div>
                            <div>
                                <label for="live-server-id"><?php echo esc_html(__('Server ID:', 'discord-embed-creator')); ?></label>
                                <input type="text" id="live-server-id" name="server_id" 
                                       placeholder="<?php echo esc_attr(__('Server ID', 'discord-embed-creator')); ?>" 
                                       style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace;">
                            </div>
                        </div>
                        
                        <div style="margin-top: 15px;">
                            <button type="button" id="load-live-channels" class="button"><?php echo esc_html(__('Load Channels', 'discord-embed-creator')); ?></button>
                            <label for="live-channel-id" style="margin-left: 15px;"><?php echo esc_html(__('Target Channel:', 'discord-embed-creator')); ?></label>
                            <select id="live-channel-id" name="channel_id" style="margin-left: 10px; padding: 8px;">
                                <option value=""><?php echo esc_html(__('Select Channel...', 'discord-embed-creator')); ?></option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Role Mentions Settings -->
                <div class="form-section" style="background: #f0f8ff; border-left: 4px solid #5865f2; padding: 15px; margin: 20px 0; border-radius: 4px;">
                    <h3 style="color: #5865f2; margin-top: 0;">🏷️ <?php echo esc_html(__('Role Mentions for Live Notifications', 'discord-embed-creator')); ?></h3>
                    <div id="live-role-selector-container">
                        <p class="description" style="color: #666; font-style: italic;">
                            <?php echo esc_html(__('Roles will be automatically loaded when Bot Token and Server ID are configured.', 'discord-embed-creator')); ?>
                        </p>
                    </div>
                </div>
                
                <!-- Live Notification Embed Editor with Preview -->
                <div class="form-section" style="background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin: 20px 0;">
                    <h3>📝 <?php echo esc_html(__('Live Notification Embed Template', 'discord-embed-creator')); ?></h3>
                    <p style="color: #666; margin-bottom: 15px;">
                        <?php echo esc_html(__('Design the embed for live notifications. You can use the same fields as in the main editor.', 'discord-embed-creator')); ?>
                        <?php echo __('Available placeholders: <code>{platform}</code>, <code>{title}</code>, <code>{url}</code>, <code>{thumbnail}</code> - Use {thumbnail} in image field for live thumbnails', 'discord-embed-creator'); ?>
                    </p>

                    <!-- Live Notification Editor Container with Preview -->
                    <div class="live-notification-editor-container" style="display: grid; grid-template-columns: 1fr 560px; gap: 20px; margin-top: 20px;">
                        
                        <!-- Live Embed Editor Fields -->
                        <div class="live-embed-editor">
                            <!-- Embed Template Fields (reusing existing embed editor components) -->
                        <div class="embed-template-editor">
                            <div class="form-row">
                                <label for="live-embed-title"><?php echo esc_html(__('Template Title:', 'discord-embed-creator')); ?></label>
                                <input type="text" id="live-embed-title" placeholder="✨ <?php echo esc_attr(__('STREAMER is Live!', 'discord-embed-creator')); ?>" 
                                       style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            </div>
                            
                            <div class="form-row">
                                <label for="live-embed-description"><?php echo esc_html(__('Template Description:', 'discord-embed-creator')); ?></label>
                                <div class="markdown-toolbar">
                                    <button type="button" class="md-btn" data-target="live-embed-description" data-md="**" title="<?php echo esc_attr(__('Bold', 'discord-embed-creator')); ?>"><strong>B</strong></button>
                                    <button type="button" class="md-btn" data-target="live-embed-description" data-md="*" title="<?php echo esc_attr(__('Italic', 'discord-embed-creator')); ?>"><em>I</em></button>
                                    <button type="button" class="md-btn" data-target="live-embed-description" data-md="__" title="<?php echo esc_attr(__('Underlined', 'discord-embed-creator')); ?>"><u>U</u></button>
                                    <button type="button" class="md-btn" data-target="live-embed-description" data-md="~~" title="<?php echo esc_attr(__('Strikethrough', 'discord-embed-creator')); ?>"><del>S</del></button>
                                    <button type="button" class="md-btn" data-target="live-embed-description" data-md="`" title="<?php echo esc_attr(__('Code', 'discord-embed-creator')); ?>"><code>C</code></button>
                                    <!-- Emoji loader for live editor (same as main editor) -->
                                    <button type="button" class="md-btn open-emoji-picker" title="<?php echo esc_attr(__('Open Emoji Picker', 'discord-embed-creator')); ?>">😃</button>
                                </div>
                                <textarea id="live-embed-description" rows="4" 
                                          placeholder="✨ Hey , STREAMER is live now at:&#10;📺 {url} !&#10;&#10;<?php echo esc_attr(__('Come join the fun! 🚀', 'discord-embed-creator')); ?>"
                                          style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"></textarea>
                            </div>
                            
                            <div class="form-row">
                                <label for="live-embed-color"><?php echo esc_html(__('Embed Color:', 'discord-embed-creator')); ?></label>
                                <input type="color" id="live-embed-color" value="#9146ff" style="width: 60px; height: 40px; border: none; border-radius: 4px;">
                                <span id="live-color-preview" style="margin-left: 10px; padding: 5px 10px; background: #9146ff; color: white; border-radius: 4px;"><?php echo esc_html(__('Live Stream', 'discord-embed-creator')); ?></span>
                            </div>
                            
                            <div class="form-row">
                                <label for="live-embed-footer"><?php echo esc_html(__('Footer Text:', 'discord-embed-creator')); ?></label>
                                <input type="text" id="live-embed-footer" placeholder="<?php echo esc_attr(__('Live now - Powered by Discord Embed Creator', 'discord-embed-creator')); ?>" 
                                       style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            </div>
                            
                            <div class="form-row">
                                <label for="live-footer-icon"><?php echo esc_html(__('Footer Icon URL:', 'discord-embed-creator')); ?></label>
                                <input type="url" id="live-footer-icon" placeholder="<?php echo esc_attr(__('https://example.com/icon.png', 'discord-embed-creator')); ?>" 
                                       style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            </div>
                            
                            <div class="form-row">
                                <label for="live-embed-image"><?php echo esc_html(__('Image URL:', 'discord-embed-creator')); ?></label>
                                <input type="url" id="live-embed-image" placeholder="<?php echo esc_attr(__('https://example.com/banner.png or use {thumbnail}', 'discord-embed-creator')); ?>" 
                                       style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                <small style="color: #666; display: block; margin-top: 5px;">
                                    <?php echo esc_html(__('Enter a fixed image URL or use {thumbnail} for live stream thumbnails. Leave empty for no image.', 'discord-embed-creator')); ?>
                                </small>
                            </div>
                        </div>
                        </div>
                        
                        <!-- Live Notification Preview -->
                        <div class="live-embed-preview" style="background: #36393f; padding: 20px; border-radius: 8px; color: #dcddde;">
                            <h4 style="margin: 0 0 15px 0; color: #fff; font-size: 16px;"><?php echo esc_html(__('Live Preview', 'discord-embed-creator')); ?></h4>
                            <div class="discord-mockup" style="background: transparent; border-radius: 4px; padding: 0;">
                                <div class="discord-message" style="display: flex; align-items: flex-start;">
                                    <div class="discord-avatar" style="width: 40px; height: 40px; border-radius: 50%; background: #5865f2; margin-right: 16px; flex-shrink: 0;"></div>
                                    <div class="discord-content" style="flex: 1;">
                                        <div class="discord-username" style="color: #ffffff; font-weight: 600; font-size: 16px; margin-bottom: 4px;">Webhook Bot</div>
                                        <div class="discord-embed" id="live-embed-preview" style="background: #2f3136; border-left: 4px solid #9146ff; padding: 12px 16px 16px 12px; border-radius: 4px; margin-top: 8px; min-width: 432px; max-width: 520px; position: relative;">
                                            <!-- Live preview will be rendered here -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                    </div>
                </div>

                <!-- Saved Live Notification Templates -->
                <div class="form-section" style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 20px 0;">
                    <h3>💾 <?php echo esc_html(__('Saved Live Notifications', 'discord-embed-creator')); ?></h3>
                    <p style="color: #666;"><?php echo esc_html(__('Save different notification variants and send them specifically to different channels.', 'discord-embed-creator')); ?></p>

                    <div style="display:flex; gap:10px; align-items:center; margin-bottom:10px;">
                        <input type="hidden" id="live-template-id" value="0">
                        <input type="text" id="live-template-name" placeholder="<?php echo esc_attr(__('Template Name', 'discord-embed-creator')); ?>" style="padding:8px; flex:1; min-width:200px;">
                        <select id="live-template-platform" style="padding:8px; min-width:100px;">
                            <option value="twitch">Twitch</option>
                            <option value="youtube">YouTube</option>
                        </select>
                        <button type="button" id="save-live-template" class="button" style="white-space:nowrap; margin-left:5px;">💾 <?php echo esc_html(__('Save Current as Template', 'discord-embed-creator')); ?></button>
                        <label style="display:flex; align-items:center; gap:6px; margin-left:10px; font-size:13px; color:#444; white-space:nowrap;">
                            <input type="checkbox" id="save-live-as-new" style="margin-right:6px;"> <?php echo esc_html(__('Save as New', 'discord-embed-creator')); ?>
                        </label>
                    </div>

                    <div id="saved-live-templates" style="display:grid; grid-template-columns: 1fr; gap:8px;">
                        <div style="color:#666;"><?php echo esc_html(__('Loading Templates...', 'discord-embed-creator')); ?></div>
                    </div>
                </div>
                
                <!-- Advanced Settings -->
                <div class="form-section" style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 20px 0;">
                    <h3>⚙️ <?php echo esc_html(__('Advanced Settings', 'discord-embed-creator')); ?></h3>
                    <div class="form-row">
                        <label for="cooldown-minutes"><?php echo esc_html(__('Cooldown between notifications (minutes):', 'discord-embed-creator')); ?></label>
                        <input type="number" id="cooldown-minutes" name="cooldown_minutes" value="10" min="1" max="120" 
                               style="width: 100px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        <small style="color: #666; margin-left: 10px;">
                            <?php echo esc_html(__('Prevents multiple notifications for the same stream', 'discord-embed-creator')); ?>
                        </small>
                    </div>
                            <div class="form-row" style="margin-top:10px;">
                        <!-- Emoji hint for live embed editor and unified picker button is provided in the editor toolbar -->
                        <label style="display:block; font-weight:600; margin-bottom:6px;"><?php echo esc_html(__('Emoji Format & Usage', 'discord-embed-creator')); ?></label>
                        <p style="color:#666; margin:0 0 8px 0;"><?php echo __('Use either the full emoji format <code>&lt;:name:ID&gt;</code> or a short placeholder <code>:shortcode:</code>. Example: <code>&lt;:twitch_logo:944912608136417293&gt;</code> or <code>:twitch_logo:</code>.', 'discord-embed-creator'); ?></p>
                    </div>
                </div>
                
                <!-- Form Actions -->
                <div class="form-actions" style="display: flex; gap: 15px; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
                    <button type="button" id="save-live-settings" class="button button-primary">
                        💾 <?php echo esc_html(__('Save Settings', 'discord-embed-creator')); ?>
                    </button>
                    <button type="button" id="test-live-notification" class="button button-secondary">
                        🧪 <?php echo esc_html(__('Send Test Notification', 'discord-embed-creator')); ?>
                    </button>
                    <div class="live-status-indicator" style="margin-left: auto; display: flex; align-items: center;">
                        <span id="live-status-text" style="margin-right: 10px; font-weight: bold;"><?php echo esc_html(__('Status: Disabled', 'discord-embed-creator')); ?></span>
                        <div id="live-status-dot" style="width: 12px; height: 12px; border-radius: 50%; background: #dc3545;"></div>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Live Notification History -->
        <div class="form-section" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px;">
            <h2 style="margin-top: 0; color: #5865f2; border-bottom: 2px solid #5865f2; padding-bottom: 10px;">📈 <?php echo esc_html(__('Live Notification History', 'discord-embed-creator')); ?></h2>
            
            <div id="live-notification-history" style="min-height: 200px;">
                <div style="text-align: center; color: #666; padding: 40px;">
                    <p><?php echo esc_html(__('Recent live notifications will be displayed here...', 'discord-embed-creator')); ?></p>
                    <small><?php echo esc_html(__('History will be implemented once the first notifications are sent.', 'discord-embed-creator')); ?></small>
                </div>
            </div>
        </div>
        
    </div> <!-- End tab-live-notifications -->
</div>

<script>
jQuery(document).ready(function($) {
    // Debug Console Functions
    function debugLog(message, type = 'info') {
        const timestamp = new Date().toLocaleTimeString();
        const logEntry = `[${timestamp}] ${type.toUpperCase()}: ${message}`;
        
        $('#debug-output').show();
        $('#debug-content').append('<div style="color: ' + (type === 'error' ? '#ff0000' : type === 'success' ? '#00ff00' : '#00ffff') + '">' + logEntry + '</div>');
        $('#debug-output').scrollTop($('#debug-output')[0].scrollHeight);
    }
    
    // Test AJAX Connection
    $('#test-ajax').on('click', function() {
        debugLog('Testing AJAX connection...', 'info');
        
        $.ajax({
            url: discordEmbed.ajaxUrl,
            method: 'POST',
            data: {
                action: 'load_embed_templates',
                nonce: discordEmbed.nonce
            },
            success: function(response) {
                debugLog('AJAX Test successful: ' + JSON.stringify(response), 'success');
            },
            error: function(xhr, status, error) {
                debugLog('AJAX Test failed: ' + status + ' - ' + error, 'error');
                debugLog('Response: ' + xhr.responseText, 'error');
            }
        });
    });
    
    // Clear Console
    $('#clear-console').on('click', function() {
        $('#debug-content').empty();
        $('#debug-output').hide();
    });
    
    // Export Debug Info
    $('#export-debug').on('click', function() {
        const debugInfo = {
            timestamp: new Date().toISOString(),
            templates_count: <?php echo count($templates); ?>,
            wp_version: '<?php echo get_bloginfo("version"); ?>',
            plugin_version: '<?php echo esc_js(DISCORD_EMBED_VERSION); ?>',
            debug_content: $('#debug-content').text()
        };
        
        const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(debugInfo, null, 2));
        const downloadAnchor = document.createElement('a');
        downloadAnchor.setAttribute("href", dataStr);
        downloadAnchor.setAttribute("download", "discord-embed-debug-" + Date.now() + ".json");
        document.body.appendChild(downloadAnchor);
        downloadAnchor.click();
        downloadAnchor.remove();
        
        debugLog('Debug info exported', 'success');
    });
    
    // Auto-log important events
    window.debugLog = debugLog;
    debugLog('Debug console initialized', 'info');
});
</script>

<style>
/* Tab System Styles */
.nav-tab-wrapper {
    border-bottom: 1px solid #ccd0d4;
    margin-bottom: 20px;
}

.nav-tab {
    position: relative;
    display: inline-block;
    padding: 12px 20px;
    text-decoration: none;
    border: 1px solid #ccd0d4;
    border-bottom: none;
    background: #f1f1f1;
    color: #555;
    margin-right: 5px;
    border-radius: 4px 4px 0 0;
    transition: all 0.2s ease;
}

.nav-tab:hover {
    background: #fff;
    color: #5865f2;
}

.nav-tab-active {
    background: #fff !important;
    color: #5865f2 !important;
    border-bottom: 1px solid #fff;
    position: relative;
    z-index: 10;
}

.nav-tab-active:after {
    content: '';
    position: absolute;
    bottom: -1px;
    left: 0;
    right: 0;
    height: 1px;
    background: #fff;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

/* Live Notifications Specific Styles */
.platform-card {
    transition: all 0.3s ease;
}

.platform-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.webhook-config-section {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    margin-top: 10px;
}

.live-roles-grid label:hover {
    background: #f0f8ff !important;
    border-color: #5865f2;
}

.everyone-role {
    opacity: 0.7;
}

.live-status-indicator {
    display: flex;
    align-items: center;
    font-weight: bold;
}

#live-status-dot {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% {
        opacity: 1;
    }
    50% {
        opacity: 0.5;
    }
    100% {
        opacity: 1;
    }
}

/* Enhanced form styles */
.form-section h2 {
    display: flex;
    align-items: center;
    gap: 10px;
}

.form-section h3 {
    display: flex;
    align-items: center;
    gap: 8px;
}

.status-grid {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.status-label {
    font-weight: 600;
    color: #495057;
}

.status-text {
    font-weight: 500;
}

.status-icon {
    font-size: 16px;
    text-align: center;
}

/* Responsive design */
@media (max-width: 768px) {
    .platform-settings {
        grid-template-columns: 1fr !important;
    }
    
    .nav-tab {
        padding: 8px 12px;
        font-size: 14px;
    }
    
    .status-grid {
        grid-template-columns: auto 1fr !important;
        gap: 10px;
    }
    
    .status-icon {
        grid-column: 2;
        justify-self: end;
    }
    
    /* Make live notification editor responsive */
    .live-notification-editor-container {
        grid-template-columns: 1fr !important;
        gap: 15px;
    }
    
    .live-embed-preview {
        order: -1; /* Show preview on top on mobile */
    }
}

/* Loading states */
.button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* Success/Error states */
.platform-card.success {
    border-color: #28a745;
    background: #d4edda;
}

.platform-card.error {
    border-color: #dc3545;
    background: #f8d7da;
}

/* Live Notification Preview Styles */
.live-embed-preview .discord-embed {
    border-left: 4px solid #5865f2;
    background: #2f3136;
    border-radius: 4px;
    padding: 16px;
    margin-top: 2px;
    max-width: 520px;
}

.live-embed-preview .embed-color-bar {
    position: absolute;
    left: -4px;
    top: 0;
    bottom: 0;
    width: 4px;
}

.live-embed-preview .embed-content {
    position: relative;
}

.live-embed-preview .embed-title {
    color: #00aff4;
    font-weight: 600;
    font-size: 16px;
    line-height: 1.375;
    margin-bottom: 8px;
    text-decoration: none;
}

.live-embed-preview .embed-title:hover {
    text-decoration: underline;
}

.live-embed-preview .embed-description {
    color: #dcddde;
    font-size: 14px;
    line-height: 1.125;
    white-space: pre-wrap;
    margin-bottom: 8px;
}

.live-embed-preview .embed-description strong {
    font-weight: 700;
}

.live-embed-preview .embed-description em {
    font-style: italic;
}

.live-embed-preview .embed-description u {
    text-decoration: underline;
}

.live-embed-preview .embed-description del {
    text-decoration: line-through;
}

.live-embed-preview .embed-description code {
    background: #202225;
    border-radius: 3px;
    padding: 2px 4px;
    font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, 'Courier New', monospace;
    font-size: 0.875em;
}

.live-embed-preview .embed-description a {
    color: #00aff4;
    text-decoration: none;
}

.live-embed-preview .embed-description a:hover {
    text-decoration: underline;
}

.live-embed-preview .embed-footer {
    display: flex;
    align-items: center;
    margin-top: 8px;
}

.live-embed-preview .embed-footer-icon {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    margin-right: 8px;
    object-fit: cover;
}

.live-embed-preview .embed-footer-text {
    color: #b9bbbe;
    font-size: 12px;
    line-height: 1.125;
}

.live-embed-preview .embed-image {
    margin-top: 16px;
}

.live-embed-preview .embed-image img {
    max-width: 400px;
    max-height: 300px;
    border-radius: 4px;
    object-fit: cover;
}

.live-embed-preview .embed-thumbnail {
    position: absolute;
    top: 0;
    right: 0;
    width: 80px;
    height: 80px;
}

.live-embed-preview .embed-thumbnail img {
    width: 100%;
    height: 100%;
    border-radius: 4px;
    object-fit: cover;
}

/* Placeholder text styling */
.live-embed-preview .embed-placeholder {
    color: #72767d;
    font-style: italic;
}

/* Animation for live preview updates */
.live-embed-preview .discord-embed {
    transition: border-left-color 0.3s ease;
}

.live-embed-preview .embed-content {
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from {
        opacity: 0.7;
    }
    to {
        opacity: 1;
    }
}
</style>

<!-- Emoji Picker Modal -->
<div id="emoji-picker-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:20000; padding:20px; box-sizing:border-box;">
    <div style="background:#fff; border-radius:8px; padding:16px; width:100%; max-width:900px; max-height:calc(100vh - 120px); overflow:auto; margin:auto;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
            <h3 style="margin:0;">Emoji Picker</h3>
            <div>
                <button type="button" id="close-emoji-picker" class="button"><?php echo esc_html(__('Close', 'discord-embed-creator')); ?></button>
            </div>
        </div>
        <div id="emoji-picker-grid" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(60px, 1fr)); gap:8px;"></div>
    </div>
</div>
