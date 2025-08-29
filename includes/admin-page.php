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
                        </div>
                        <textarea id="embed-description" name="description" rows="4" placeholder="<?php echo esc_attr(__('Embed Description (Markdown supported)', 'discord-embed-creator')); ?>"></textarea>
                        <small><?php echo esc_html(__('Supports', 'discord-embed-creator')); ?>: **<?php echo esc_html(__('bold', 'discord-embed-creator')); ?>**, *<?php echo esc_html(__('italic', 'discord-embed-creator')); ?>*, __<?php echo esc_html(__('underlined', 'discord-embed-creator')); ?>__, ~~<?php echo esc_html(__('strikethrough', 'discord-embed-creator')); ?>~~, `<?php echo esc_html(__('code', 'discord-embed-creator')); ?>`, [<?php echo esc_html(__('links', 'discord-embed-creator')); ?>](url)</small>
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
        <div class="history-grid" id="history-container">
            <!-- Legacy history container -->
        </div>
    </div>
    
    <!-- Enhanced Template Cards Container (will be populated by JavaScript) -->
    <div class="template-cards-container" style="display: none;">
        <!-- Template cards will be rendered here by ui-fix.js -->
    </div>
    
    <!-- Debug Console -->
    <div class="form-section">
        <h3>🔧 <?php echo esc_html(__('Debug Console', 'discord-embed-creator')); ?></h3>
        <div style="background: #f0f0f0; padding: 15px; border-radius: 4px; font-family: monospace;">
            <div>
                <strong><?php echo esc_html(__('Plugin Status', 'discord-embed-creator')); ?>:</strong> 
                <span style="color: green;">✅ <?php echo esc_html(__('Version 1.2.0 Active', 'discord-embed-creator')); ?></span>
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
            plugin_version: '1.2.0',
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
