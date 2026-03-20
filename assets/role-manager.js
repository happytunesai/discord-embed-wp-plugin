/**
 * Discord Role Manager - Handles role loading and mention formatting
 */

(function($) {
    'use strict';
    
    // Escape HTML to prevent XSS
    function escapeHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
    
    // Get localized string helper function
    function __l(key, fallback) {
        return (typeof discordEmbedL10n !== 'undefined' && discordEmbedL10n[key]) ? discordEmbedL10n[key] : fallback;
    }
    
    window.DiscordRoleManager = {
        
        // Cache for loaded roles
        rolesCache: {},
        
        /**
         * Load server roles via Bot Token
         */
        loadServerRoles: function(botToken, serverId) {
            return new Promise((resolve, reject) => {
                // Check cache first
                const cacheKey = `${serverId}`;
                if (this.rolesCache[cacheKey]) {
                    resolve(this.rolesCache[cacheKey]);
                    return;
                }
                
                // Make API request to load roles
                $.ajax({
                    url: discordEmbed.ajaxUrl || ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'load_server_roles',
                        bot_token: botToken,
                        server_id: serverId,
                        nonce: discordEmbed.nonce || $('#_wpnonce').val()
                    },
                    success: (response) => {
                        if (response.success) {
                            // Cache the roles
                            this.rolesCache[cacheKey] = response.data.roles;
                            resolve(response.data.roles);
                        } else {
                            reject(response.data.message || 'Error loading roles');
                        }
                    },
                    error: (xhr, status, error) => {
                        reject(`Network error: ${error}`);
                    }
                });
            });
        },
        
        /**
         * Create role selector dropdown
         */
        createRoleSelector: function(containerId, roles, placeholder = 'Select role...') {
            const container = $(containerId);
            
            let html = `
                <div class="role-selector-wrapper">
                    <select class="role-selector" data-placeholder="${placeholder}">
                        <option value="">${placeholder}</option>
            `;
            
            // Sort roles by position (higher = more important)
            const sortedRoles = roles
                .filter(role => role.name !== '@everyone') // Exclude @everyone
                .sort((a, b) => b.position - a.position);
            
            sortedRoles.forEach(role => {
                const color = role.color ? `#${role.color.toString(16).padStart(6, '0')}` : '#99aab5';
                html += `
                    <option value="${escapeHtml(role.id)}" data-color="${escapeHtml(color)}" data-name="${escapeHtml(role.name)}">
                        ${escapeHtml(role.name)}
                    </option>
                `;
            });
            
            html += `
                    </select>
                    <button type="button" class="button insert-role-mention">Insert Role</button>
                </div>
            `;
            
            container.html(html);
            
            // Style the dropdown options
            this.styleRoleSelector(container.find('.role-selector'));
            
            return container.find('.role-selector');
        },
        
        /**
         * Style role selector with colors
         */
        styleRoleSelector: function(selector) {
            selector.on('change', function() {
                const selectedOption = $(this).find('option:selected');
                const color = selectedOption.data('color');
                
                if (color && color !== '#99aab5') {
                    $(this).css('color', color);
                } else {
                    $(this).css('color', '');
                }
            });
        },
        
        /**
         * Insert role mention into textarea
         */
        insertRoleMention: function(targetTextareaId, roleId, roleName) {
            const textarea = $(targetTextareaId);
            const mention = `<@&${roleId}>`;
            
            // Get current cursor position
            const startPos = textarea[0].selectionStart;
            const endPos = textarea[0].selectionEnd;
            const currentValue = textarea.val();
            
            // Insert mention at cursor position
            const newValue = currentValue.substring(0, startPos) + mention + currentValue.substring(endPos);
            textarea.val(newValue);
            
            // Set cursor position after the mention
            const newCursorPos = startPos + mention.length;
            textarea[0].setSelectionRange(newCursorPos, newCursorPos);
            textarea.focus();
            
            // Trigger change event to update preview
            textarea.trigger('input');
            
            console.log(`Role mention inserted: ${mention} (${roleName})`);
        },
        
        /**
         * Format role mentions in text for display
         */
        formatRoleMentions: function(text, roles = null) {
            if (!text) return '';
            
            // Role mention pattern: <@&123456789>
            return text.replace(/<@&(\d+)>/g, (match, roleId) => {
                let roleName = 'Unknown Role';
                let roleColor = '#5865f2';
                
                // Try to find role name and color from roles data
                if (roles) {
                    const role = roles.find(r => r.id === roleId);
                    if (role) {
                        roleName = role.name;
                        roleColor = role.color ? `#${role.color.toString(16).padStart(6, '0')}` : '#5865f2';
                    }
                }
                
                return `<span style="background: ${escapeHtml(roleColor)}; color: #ffffff; padding: 1px 4px; border-radius: 3px; font-weight: 500;">@${escapeHtml(roleName)}</span>`;
            });
        },
        
        /**
         * Initialize role manager for a form
         */
        init: function(formSelector = '#embed-form') {
            const self = this;
            
            // Add role section to form if not exists
            this.addRoleSection(formSelector);
            
            // Handle server ID changes to load roles
            $(document).on('change', '#server-id', function() {
                const serverId = $(this).val();
                const botToken = $('#bot-token').val();
                
                if (serverId && botToken) {
                    self.loadAndDisplayRoles(botToken, serverId);
                }
            });
            
            // Handle bot token changes
            $(document).on('change', '#bot-token', function() {
                const serverId = $('#server-id').val();
                const botToken = $(this).val();
                
                if (serverId && botToken) {
                    self.loadAndDisplayRoles(botToken, serverId);
                }
            });
            
            // Handle role insertion
            $(document).on('click', '.insert-role-mention', function() {
                const wrapper = $(this).closest('.role-selector-wrapper');
                const selector = wrapper.find('.role-selector');
                const selectedOption = selector.find('option:selected');
                const roleId = selectedOption.val();
                const roleName = selectedOption.data('name');
                
                if (roleId) {
                    // Find target textarea (description by default)
                    const targetTextarea = '#embed-description';
                    self.insertRoleMention(targetTextarea, roleId, roleName);
                    
                    // Reset selector
                    selector.val('').trigger('change');
                } else {
                    alert(__l('pleaseSelectRole', 'Please select a role.'));
                }
            });
            
            
            console.log('Discord Role Manager initialized');
            
            // Check if bot token and server ID are already filled on page load
            setTimeout(() => {
                const serverId = $('#server-id').val();
                const botToken = $('#bot-token').val();
                
                console.log('Auto-checking for existing credentials...');
                
                if (serverId && botToken) {
                    console.log('Found existing credentials, auto-loading roles...');
                    self.loadAndDisplayRoles(botToken, serverId);
                }
            }, 2000); // Longer delay to ensure page is fully loaded
        },
        
        /**
         * Add role section to form (only if it doesn't exist)
         */
        addRoleSection: function(formSelector) {
            const form = $(formSelector);
            
            // Check if role section already exists
            if (form.find('#role-mentions-section').length > 0) {
                console.log('Role mentions section already exists in HTML');
                return;
            }
            
            const embedContentSection = form.find('.form-section:has(#embed-description)');
            
            if (embedContentSection.length) {
                embedContentSection.after(`
                    <div class="form-section" id="role-mentions-section">
                        <h3>🏷️ Role Mentions</h3>
                        <div class="form-row">
                            <label>Roles:</label>
                            <div id="role-selector-container">
                                <p class="description">
                                    Enter Bot Token and Server ID to load roles.
                                </p>
                            </div>
                        </div>
                        <div class="form-row">
                            <small class="description">
                                📝 <strong>Tipp:</strong> Rollen werden als <code>&lt;@&amp;ROLEID&gt;</code> eingefügt. 
                                Sie können auch manuell eingeben: <code>&lt;@&amp;739772583179845653&gt;</code>
                            </small>
                        </div>
                    </div>
                `);
                console.log('Role mentions section added via JavaScript');
            }
        },
        
        /**
         * Load and display roles
         */
        loadAndDisplayRoles: function(botToken, serverId) {
            const container = $('#role-selector-container');
            
            // Validate inputs
            if (!botToken || !serverId) {
                container.html('<p class="description">Enter Bot Token and Server ID to load roles.</p>');
                return;
            }
            
            // Show loading state
            container.html('<p>🔄 Loading roles...</p>');
            
            console.log('Loading roles for server:', serverId);
            
            this.loadServerRoles(botToken, serverId)
                .then(roles => {
                    console.log('Roles loaded successfully:', roles);
                    
                    if (roles && roles.length > 0) {
                        this.createRoleSelector('#role-selector-container', roles);
                        console.log(`✅ ${roles.length} roles loaded for server ${serverId}`);
                    } else {
                        container.html('<p class="description">No roles found. Check permissions.</p>');
                    }
                })
                .catch(error => {
                    console.log('Error loading roles:', error);
                    
                    // Simple error message without technical details
                    container.html('<p class="description">Could not load roles. Check Bot Token and Server ID.</p>');
                });
        }
    };
    
    // Auto-initialize when document is ready
    $(document).ready(function() {
        window.DiscordRoleManager.init();
    });
    
})(jQuery);
