/**
 * Discord Embed Plugin UI Fix v3.0 - Complete Rewrite
 * Fixes Preview Renderi            // Fields - ERWEITERTE Selektoren da die Standard-Klassen nicht gefunden werden!
            const fields = [];
            console.log('=== FIELDS DEBUGGING ===');
            console.log('Looking for fields in:', $('#fields-container').length, 'containers');
            
            // ATTEMPT 1: Original selectors (.embed-field)
            const $embedFields = $('#fields-container .embed-field');
            console.log('Found .embed-field elements:', $embedFields.length);
            
            if ($embedFields.length > 0) {
                $embedFields.each(function(index) {
                    const $field = $(this);
                    const name = $field.find('.field-name').val();
                    const value = $field.find('.field-value').val();
                    const inline = $field.find('.inline-checkbox').is(':checked');
                    
                    console.log(`Field ${index + 1} (embed-field):`, { name, value, inline });
                    
                    if (name && value) {
                        fields.push({ name, value, inline });
                    }
                });
            } else {
                // ATTEMPT 2: Alternative selectors - search directly for input/textarea
                console.log('No .embed-field found, trying alternative selectors...');
                
                const $fieldInputs = $('#fields-container input, #fields-container textarea');
                console.log('Found field inputs/textareas:', $fieldInputs.length);
                
                // Sammle alle Inputs mit deren Details
                const fieldInputs = [];
                $fieldInputs.each(function() {
                    const $input = $(this);
                    const className = $input.attr('class') || '';
                    const placeholder = $input.attr('placeholder') || '';
                    const value = $input.val() || '';
                    
                    console.log('Processing input:', {
                        value: value,
                        class: className,
                        placeholder: placeholder,
                        type: this.tagName,
                        isNameField: className.includes('field-name') || placeholder.includes('Field Name'),
                        isValueField: className.includes('field-value') || placeholder.includes('Field Value')
                    });
                    
                    fieldInputs.push({
                        element: $input,
                        value: value,
                        className: className,
                        placeholder: placeholder,
                        isNameField: className.includes('field-name') || placeholder.includes('Field Name'),
                        isValueField: className.includes('field-value') || placeholder.includes('Field Value')
                    });
                });
                
                // ATTEMPT 2A: Search for specific classes
                const nameFields = fieldInputs.filter(f => f.isNameField);
                const valueFields = fieldInputs.filter(f => f.isValueField);
                
                console.log('Found name fields:', nameFields.length);
                console.log('Found value fields:', valueFields.length);
                
                for (let i = 0; i < Math.min(nameFields.length, valueFields.length); i++) {
                    const nameField = nameFields[i];
                    const valueField = valueFields[i];
                    
                    if (nameField.value && valueField.value) {
                        fields.push({
                            name: nameField.value,
                            value: valueField.value,
                            inline: false // Default
                        });
                        console.log(`✓ Created field ${i + 1} from specific classes:`, {
                            name: nameField.value,
                            value: valueField.value
                        });
                    }
                }
                
                // ATTEMPT 2B: If that doesn't work, try pairwise pairing
                if (fields.length === 0) {
                    console.log('No fields from specific classes, trying pairwise pairing...');
                    
                    for (let i = 0; i < fieldInputs.length; i += 2) {
                        if (i + 1 < fieldInputs.length) {
                            const first = fieldInputs[i];
                            const second = fieldInputs[i + 1];
                            
                            if (first.value && second.value) {
                                fields.push({
                                    name: first.value,
                                    value: second.value,
                                    inline: false
                                });
                                console.log(`✓ Created field from pair ${Math.floor(i/2) + 1}:`, {
                                    name: first.value,
                                    value: second.value
                                });
                            }
                        }
                    }
                }
            }
            
            console.log('Final fields array:', fields);
            console.log('Will embedData.fields be set?', fields.length > 0);
            
            if (fields.length > 0) {
                embedData.fields = fields;
                console.log('✅ embedData.fields SET with', fields.length, 'fields');
            } else {
                console.log('❌ NO FIELDS - embedData.fields will NOT be set');
            }e Loading Issues
 */

(function($) {
    'use strict';
    
    console.log('%c=== DISCORD EMBED PLUGIN UI FIX V3.0 LOADING ===', 'color: #0073aa; font-size: 16px; font-weight: bold;');
    
    // Wait for DOM ready
    $(document).ready(function() {
        
        // Get localized string helper function
        function __l(key, fallback) {
            return (typeof discordEmbedL10n !== 'undefined' && discordEmbedL10n[key]) ? discordEmbedL10n[key] : fallback;
        }
        
        // Helper functions
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function parseMarkdown(text) {
            if (!text) return '';
            
            try {
                let html = escapeHtml(text);
                
                // Step 1: Discord-specific emoji replacements BEFORE other processing
                const discordEmojiMap = {
                    ':date:': '📅',
                    ':clock:': '🕐',
                    ':heart:': '❤️',
                    ':star:': '⭐',
                    ':fire:': '🔥',
                    ':sparkles:': '✨',
                    ':tada:': '🎉',
                    ':musical_note:': '🎵',
                    ':notes:': '🎶',
                    ':microphone:': '🎤',
                    ':guitar:': '🎸',
                    ':trumpet:': '🎺',
                    ':drum:': '🥁',
                    ':calendar:': '📅',
                    ':time:': '⏰',
                    ':alarm_clock:': '⏰',
                    ':hourglass:': '⏳',
                    ':stopwatch:': '⏱️'
                };
                
                // Replace Discord emoji codes with Unicode emojis
                for (const [code, emoji] of Object.entries(discordEmojiMap)) {
                    const escapedCode = code.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                    html = html.replace(new RegExp(escapedCode, 'g'), emoji);
                }
                
                // NOTE: User mentions (@Bot, @everyone) and channel mentions (#channel) 
                // are intentionally NOT processed here because:
                // 1. Webhooks cannot create real Discord mentions
                // 2. They appear as plain text in Discord when sent via webhook
                // 3. Converting them to HTML breaks the preview
                
                // Role mentions: <@&ROLEID> - These work with bot tokens!
                html = html.replace(/<@&(\d+)>/g, '<span style="background: #5865f2; color: #ffffff; padding: 1px 4px; border-radius: 3px; font-weight: 500;">@role</span>');
                
                // User mentions: <@USERID> or <@!USERID> (for reference, but won't work with webhooks)
                html = html.replace(/<@!?(\d+)>/g, '<span style="background: #5865f2; color: #ffffff; padding: 1px 4px; border-radius: 3px; font-weight: 500; opacity: 0.7;">@user</span>');
                
                // Channel mentions: <#CHANNELID> (for reference, but won't work with webhooks)
                html = html.replace(/<#(\d+)>/g, '<span style="background: #5865f2; color: #ffffff; padding: 1px 4px; border-radius: 3px; font-weight: 500; opacity: 0.7;">#channel</span>');
                
                // Step 2: Discord Timestamps - BEFORE other processing
                // Convert Discord timestamp format <t:timestamp:format> to readable German format
                html = html.replace(/<t:(\d+):([tTdDfFR])>/g, function(match, timestamp, format) {
                    try {
                        const date = new Date(parseInt(timestamp) * 1000);
                        const now = new Date();
                        
                        // German month names
                        const months = [
                            'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni',
                            'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'
                        ];
                        
                        // German day names
                        const days = [
                            'Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 
                            'Donnerstag', 'Freitag', 'Samstag'
                        ];
                        
                        let formattedTime = '';
                        const day = date.getDate();
                        const month = months[date.getMonth()];
                        const year = date.getFullYear();
                        const dayName = days[date.getDay()];
                        const hours = date.getHours().toString().padStart(2, '0');
                        const minutes = date.getMinutes().toString().padStart(2, '0');
                        
                        switch (format.toLowerCase()) {
                            case 't': // Short time (16:20)
                                formattedTime = `${hours}:${minutes}`;
                                break;
                            case 'T': // Long time (16:20:30)
                                const seconds = date.getSeconds().toString().padStart(2, '0');
                                formattedTime = `${hours}:${minutes}:${seconds}`;
                                break;
                            case 'd': // Short date (20/04/2021)
                                formattedTime = `${day.toString().padStart(2, '0')}.${(date.getMonth() + 1).toString().padStart(2, '0')}.${year}`;
                                break;
                            case 'D': // Long date (20 April 2021)
                                formattedTime = `${day}. ${month} ${year}`;
                                break;
                            case 'f': // Short date/time (20 April 2021 16:20)
                                formattedTime = `${day}. ${month} ${year} um ${hours}:${minutes}`;
                                break;
                            case 'F': // Long date/time (Tuesday, 20 April 2021 16:20)
                                formattedTime = `${dayName}, ${day}. ${month} ${year} um ${hours}:${minutes}`;
                                break;
                            case 'R': // Relative time (2 months ago)
                                const diffMs = now.getTime() - date.getTime();
                                const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));
                                const diffHours = Math.floor(diffMs / (1000 * 60 * 60));
                                const diffMinutes = Math.floor(diffMs / (1000 * 60));
                                
                                if (Math.abs(diffDays) >= 1) {
                                    if (diffDays > 0) {
                                        formattedTime = diffDays === 1 ? 'vor 1 Tag' : `vor ${diffDays} Tagen`;
                                    } else {
                                        formattedTime = Math.abs(diffDays) === 1 ? 'in 1 Tag' : `in ${Math.abs(diffDays)} Tagen`;
                                    }
                                } else if (Math.abs(diffHours) >= 1) {
                                    if (diffHours > 0) {
                                        formattedTime = diffHours === 1 ? 'vor 1 Stunde' : `vor ${diffHours} Stunden`;
                                    } else {
                                        formattedTime = Math.abs(diffHours) === 1 ? 'in 1 Stunde' : `in ${Math.abs(diffHours)} Stunden`;
                                    }
                                } else if (Math.abs(diffMinutes) >= 1) {
                                    if (diffMinutes > 0) {
                                        formattedTime = diffMinutes === 1 ? 'vor 1 Minute' : `vor ${diffMinutes} Minuten`;
                                    } else {
                                        formattedTime = Math.abs(diffMinutes) === 1 ? 'in 1 Minute' : `in ${Math.abs(diffMinutes)} Minuten`;
                                    }
                                } else {
                                    formattedTime = 'gerade eben';
                                }
                                break;
                            default:
                                formattedTime = `${day}. ${month} ${year} um ${hours}:${minutes}`;
                        }
                        
                        // Return with Discord timestamp styling
                        return `<span style="background: rgba(88, 101, 242, 0.15); color: #ffffff; padding: 1px 3px; border-radius: 3px; font-family: monospace; cursor: default;" title="Discord Timestamp: ${match}">${formattedTime}</span>`;
                        
                    } catch (error) {
                        console.warn('Discord timestamp parsing error:', error);
                        return match; // Return original if parsing fails
                    }
                });

                // Step 3: Enhanced Discord markdown processing
                // Bold: **text**
                html = html.replace(/\*\*(.*?)\*\*/g, '<strong style="color: #ffffff;">$1</strong>');
                
                // Underline: __text__ (Discord uses __ for underline, not bold)
                html = html.replace(/__(.*?)__/g, '<u style="color: #ffffff;">$1</u>');
                
                // Italic: *text* or _text_ (but not __ which is underline)
                html = html.replace(/(?<!\*)\*([^*]+?)\*(?!\*)/g, '<em>$1</em>');
                html = html.replace(/(?<!_)_([^_]+?)_(?!_)/g, '<em>$1</em>');
                
                // Strikethrough: ~~text~~
                html = html.replace(/~~(.*?)~~/g, '<del>$1</del>');
                
                // Code blocks: ```text``` (process before single backticks)
                html = html.replace(/```([\s\S]*?)```/g, '<pre style="background: rgba(79, 84, 92, 0.32); padding: 8px; border-radius: 4px; font-family: monospace; white-space: pre-wrap; margin: 4px 0;"><code>$1</code></pre>');
                
                // Inline code: `text`
                html = html.replace(/`([^`]+?)`/g, '<code style="background: #202225; padding: 2px 4px; border-radius: 3px; font-family: monospace;">$1</code>');
                
                // Spoilers: ||text||
                html = html.replace(/\|\|(.*?)\|\|/g, '<span style="background: #202225; color: #202225; border-radius: 3px; padding: 0 2px; cursor: pointer;" title="Spoiler - Click to reveal" onclick="this.style.color=\'#dcddde\'">$1</span>');
                
                // Line breaks
                html = html.replace(/\n/g, '<br>');
                
                return html;
                
            } catch (error) {
                console.warn('Discord formatting error:', error);
                // Return safely escaped text if processing fails
                return escapeHtml(text).replace(/\n/g, '<br>');
            }
        }
        
        // Main updatePreview function
        function updatePreview() {
            console.log('=== UPDATE PREVIEW CALLED ===');
            
            // Collect form data
            const embedData = {
                title: $('#embed-title').val() || '',
                description: $('#embed-description').val() || '',
                url: $('#embed-url').val() || '',
                color: $('#embed-color').val() || '#5865f2'
            };
            
            console.log('Color from input:', $('#embed-color').val(), 'Final color:', embedData.color);
            
            // ZUSÄTZLICHER Fields-Test für Debugging - ERWEITERT
            console.log('=== MANUAL FIELDS CHECK ===');
            console.log('Fields container exists:', $('#fields-container').length);
            console.log('All .embed-field elements on page:', $('.embed-field').length);
            console.log('All .field-name elements on page:', $('.field-name').length);
            console.log('All .field-value elements on page:', $('.field-value').length);
            
            // NEUE TESTS - Verschiedene mögliche Selektoren
            console.log('=== ALTERNATIVE FIELD SELECTORS ===');
            console.log('Input elements with "field" in name:', $('input[class*="field"]').length);
            console.log('Textarea elements with "field" in name:', $('textarea[class*="field"]').length);
            console.log('All input elements in fields-container:', $('#fields-container input').length);
            console.log('All textarea elements in fields-container:', $('#fields-container textarea').length);
            
            // ZEIGE ALLE EINGABEFELDER IN FIELDS-CONTAINER - DETAILLIERT
            console.log('=== ALL ELEMENTS IN FIELDS-CONTAINER ===');
            $('#fields-container').find('*').each(function(i) {
                const $el = $(this);
                if ($el.is('input, textarea, select')) {
                    console.log(`  Field input ${i}:`, {
                        tag: this.tagName,
                        type: $el.attr('type'),
                        id: $el.attr('id'),
                        name: $el.attr('name'),
                        class: $el.attr('class'),
                        placeholder: $el.attr('placeholder'),
                        value: $el.val()
                    });
                }
            });
            
            // Author data
            const authorName = $('#author-name').val();
            const authorUrl = $('#author-url').val();
            const authorIcon = $('#author-icon').val();
            if (authorName || authorUrl || authorIcon) {
                embedData.author = {
                    name: authorName,
                    url: authorUrl,
                    icon_url: authorIcon
                };
            }
            
            // Footer data
            const footerText = $('#footer-text').val();
            const footerIcon = $('#footer-icon').val();
            if (footerText || footerIcon) {
                embedData.footer = {
                    text: footerText,
                    icon_url: footerIcon
                };
            }
            
            // Images
            const thumbnailUrl = $('#thumbnail-url').val();
            const imageUrl = $('#image-url').val();
            if (thumbnailUrl) {
                embedData.thumbnail = { url: thumbnailUrl };
            }
            if (imageUrl) {
                embedData.image = { url: imageUrl };
            }
            
            // Fields - ENHANCED DETECTION SYSTEM
            console.log('=== ENHANCED FIELDS DETECTION ===');
            
            // DEBUGGING: Show complete fields container structure
            console.log('🔍 Fields container HTML structure:');
            console.log($('#fields-container').html());
            
            // Find ALL possible input/textarea elements in the fields container
            const fieldsContainer = $('#fields-container');
            const allFieldInputs = fieldsContainer.find('input, textarea')
                .not('[type="button"], [type="submit"], [type="checkbox"], [type="hidden"]');
            
            console.log(`🔍 Total input/textarea elements in fields-container: ${allFieldInputs.length}`);
            
            // Log each element with detailed information
            allFieldInputs.each(function(index) {
                const $elem = $(this);
                const tagName = $elem.prop('tagName');
                const elemType = $elem.attr('type') || 'N/A';
                const elemId = $elem.attr('id') || 'N/A';
                const elemClass = $elem.attr('class') || 'N/A';
                const placeholder = $elem.attr('placeholder') || 'N/A';
                const value = $elem.val() || '';
                const parent = $elem.parent().attr('class') || 'N/A';
                const parentTag = $elem.parent().prop('tagName') || 'N/A';
                
                console.log(`📋 Field Input ${index}: ${tagName}[${elemType}] | ID: ${elemId} | Class: ${elemClass} | Placeholder: ${placeholder} | Value: "${value}" | Parent: ${parentTag}.${parent}`);
            });

            // Try original method first
            const fields = [];
            $('#fields-container .field-group').each(function() {
                const name = $(this).find('.field-name').val();
                const value = $(this).find('.field-value').val();
                const inline = $(this).find('.field-inline').is(':checked');
                if (name && value) {
                    fields.push({ name, value, inline });
                }
            });
            
            console.log(`🎯 Original method found ${fields.length} fields`);

            // If original method found nothing, try enhanced detection
            if (fields.length === 0 && allFieldInputs.length > 0) {
                console.log('🚀 Using enhanced field detection...');
                
                // Strategy 1: Look for pairs based on visual order in the container
                console.log('📊 Strategy 1 - Sequential pairing of all inputs in fields-container');
                
                for (let i = 0; i < allFieldInputs.length; i += 2) {
                    if (i + 1 < allFieldInputs.length) {
                        const nameInput = $(allFieldInputs[i]);
                        const valueInput = $(allFieldInputs[i + 1]);
                        const nameVal = nameInput.val() || '';
                        const valueVal = valueInput.val() || '';
                        
                        console.log(`� Strategy 1 - Pair ${(i/2)+1}:`);
                        console.log(`  Name input: ${nameInput.attr('placeholder')} = "${nameVal}"`);
                        console.log(`  Value input: ${valueInput.attr('placeholder')} = "${valueVal}"`);
                        
                        // Accept any pair that has at least one value
                        if (nameVal.trim() || valueVal.trim()) {
                            fields.push({
                                name: nameVal.trim() || `Field ${(i/2)+1}`,
                                value: valueVal.trim() || `Value ${(i/2)+1}`,
                                inline: false
                            });
                            console.log(`✅ Strategy 1 - Added Field ${(i/2)+1}: "${nameVal || `Field ${(i/2)+1}`}" / "${valueVal || `Value ${(i/2)+1}`}"`);
                        }
                    }
                }
                
                // Strategy 2: If we have odd number of inputs, check for specific patterns
                if (fields.length === 0 && allFieldInputs.length > 0) {
                    console.log('� Strategy 2 - Pattern-based detection');
                    
                    // Look for inputs that might be field names vs values based on size/type
                    const textInputs = allFieldInputs.filter(function() {
                        return $(this).is('input[type="text"], input:not([type])');
                    });
                    const textareas = allFieldInputs.filter(function() {
                        return $(this).is('textarea');
                    });
                    
                    console.log(`Found ${textInputs.length} text inputs and ${textareas.length} textareas`);
                    
                    // If we have equal numbers, pair them
                    if (textInputs.length > 0 && textareas.length > 0 && textInputs.length === textareas.length) {
                        for (let i = 0; i < textInputs.length; i++) {
                            const nameVal = $(textInputs[i]).val() || '';
                            const valueVal = $(textareas[i]).val() || '';
                            
                            if (nameVal.trim() || valueVal.trim()) {
                                fields.push({
                                    name: nameVal.trim() || `Field ${i+1}`,
                                    value: valueVal.trim() || `Value ${i+1}`,
                                    inline: false
                                });
                                console.log(`✅ Strategy 2 - Added Field ${i+1}: "${nameVal || `Field ${i+1}`}" / "${valueVal || `Value ${i+1}`}"`);
                            }
                        }
                    }
                }
            }
            
            // Final assignment
            if (fields.length > 0) {
                embedData.fields = fields;
                console.log(`🎉 SUCCESS: embedData.fields SET with ${fields.length} fields:`, embedData.fields);
            } else {
                console.log('❌ NO FIELDS COLLECTED');
                console.log('🔍 Debug: Consider checking field HTML structure');
            }
            
            console.log('Collected embed data:', embedData);
            
            // Render the preview
            renderPreview(embedData);
        }
        
        // Enhanced renderPreview function
        function renderPreview(embedData) {
            console.log('=== RENDER PREVIEW START ===');
            console.log('Input embedData:', embedData);
            
            // Try to find the existing embed-preview container first
            let $container = $('#embed-preview');
            console.log('Found embed-preview container:', $container.length > 0);
            
            // If not found, try preview-container
            if ($container.length === 0) {
                $container = $('#preview-container');
                console.log('Found preview-container:', $container.length > 0);
            }
            
            // Create preview container if it doesn't exist
            if ($container.length === 0) {
                console.log('Creating new preview container...');
                
                // Try multiple selectors to find where to place the preview
                let $targetElement = $('.embed-form');
                if ($targetElement.length === 0) {
                    $targetElement = $('.wrap').first();
                }
                if ($targetElement.length === 0) {
                    $targetElement = $('body');
                }
                
                $targetElement.after(`
                    <div class="preview-section" style="margin-top: 20px;">
                        <h2>Embed Vorschau</h2>
                        <div class="preview-container" id="preview-container" style="border: 1px solid #ddd; border-radius: 4px; padding: 10px; background: #f9f9f9;"></div>
                    </div>
                `);
                
                // Re-select the container
                $container = $('#preview-container');
                console.log('Preview container created, checking again...');
            }
            
            console.log('Final container found:', $container.length > 0);
            console.log('Container ID:', $container.attr('id'));
            console.log('Preview container element:', $container[0]);
            
            // Check if we have any data
            const hasTitle = embedData.title && embedData.title.trim() !== '';
            const hasDescription = embedData.description && embedData.description.trim() !== '';
            const hasColor = embedData.color && embedData.color !== '';
            const hasAnyData = hasTitle || hasDescription || hasColor || embedData.url || embedData.author || embedData.footer || (embedData.fields && embedData.fields.length > 0);
            
            console.log('Data check:', {hasTitle, hasDescription, hasColor, hasAnyData});
            
            // Determine if this is the main embed-preview container or our custom one
            const isMainPreview = $container.attr('id') === 'embed-preview';
            console.log('Is main preview container:', isMainPreview);
            
            if (!hasAnyData) {
                const placeholderHtml = isMainPreview ? 
                    `<div style="padding: 20px; text-align: center; color: #72767d; font-size: 14px;">
                        Keine Daten für Vorschau verfügbar
                     </div>` :
                    `<div style="
                        text-align: center;
                        padding: 40px;
                        color: #666;
                        border: 2px dashed #ddd;
                        border-radius: 4px;
                        background: #f9f9f9;
                    ">
                        <h3 style="margin: 0 0 10px 0; color: #333;">Keine Vorschau verfügbar</h3>
                        <p style="margin: 0; color: #666;">Füllen Sie die Felder aus, um eine Vorschau zu sehen.</p>
                    </div>`;
                
                $container.html(placeholderHtml);
                console.log('No data - showing placeholder');
                return;
            }
            
            // Get color
            const color = embedData.color || '#5865f2';
            console.log('Using color:', color);
            
            let embedHtml;
            
            if (isMainPreview) {
                // For the main embed-preview, create a simpler structure that fits the existing Discord mockup
                embedHtml = '';
                
                // Author
                if (embedData.author && (embedData.author.name || embedData.author.icon_url)) {
                    embedHtml += `
                        <div style="
                            display: flex;
                            align-items: center;
                            margin-bottom: 8px;
                            gap: 8px;
                        ">
                    `;
                    
                    if (embedData.author.icon_url) {
                        embedHtml += `
                            <img src="${escapeHtml(embedData.author.icon_url)}" style="
                                width: 20px;
                                height: 20px;
                                border-radius: 50%;
                            " alt="Author Icon">
                        `;
                    }
                    
                    if (embedData.author.name) {
                        const authorContent = embedData.author.url ? 
                            `<a href="${escapeHtml(embedData.author.url)}" target="_blank" style="color: #ffffff; text-decoration: none;">${escapeHtml(embedData.author.name)}</a>` :
                            escapeHtml(embedData.author.name);
                        
                        embedHtml += `
                            <span style="
                                color: #ffffff;
                                font-size: 14px;
                                font-weight: 600;
                            ">${authorContent}</span>
                        `;
                    }
                    
                    embedHtml += '</div>';
                }
                
                // Title
                if (hasTitle) {
                    const titleContent = embedData.url ? 
                        `<a href="${escapeHtml(embedData.url)}" target="_blank" style="color: #00b0f4; text-decoration: none;">${escapeHtml(embedData.title)}</a>` :
                        escapeHtml(embedData.title);
                    
                    embedHtml += `
                        <div style="
                            color: #ffffff;
                            font-size: 16px;
                            font-weight: 600;
                            margin-bottom: ${hasDescription ? '8px' : '0'};
                            line-height: 1.375;
                        ">${titleContent}</div>
                    `;
                }
                
                // Description
                if (hasDescription) {
                    embedHtml += `
                        <div style="
                            color: #dcddde;
                            font-size: 14px;
                            line-height: 1.375;
                            margin-bottom: 8px;
                            white-space: pre-wrap;
                            word-wrap: break-word;
                        ">${parseMarkdown(embedData.description)}</div>
                    `;
                }
                
                // Fields - ENHANCED DEBUGGING
                if (embedData.fields && embedData.fields.length > 0) {
                    console.log(`=== RENDERING ${embedData.fields.length} FIELDS ===`);
                    embedData.fields.forEach((field, index) => {
                        console.log(`Rendering field ${index + 1}:`, field);
                        embedHtml += `
                            <div style="margin-bottom: 8px;">
                                <div style="
                                    color: #ffffff;
                                    font-size: 14px;
                                    font-weight: 600;
                                    margin-bottom: 2px;
                                ">${parseMarkdown(field.name)}</div>
                                <div style="
                                    color: #dcddde;
                                    font-size: 14px;
                                    line-height: 1.375;
                                ">${parseMarkdown(field.value)}</div>
                            </div>
                        `;
                    });
                } else {
                    console.log('=== NO FIELDS TO RENDER ===');
                    if (embedData.fields) {
                        console.log('Fields array exists but empty:', embedData.fields);
                    } else {
                        console.log('No fields property in embedData');
                    }
                }
                
                // Image (full width) - WICHTIG für Discord-Genauigkeit
                if (embedData.image && embedData.image.url) {
                    embedHtml += `
                        <div style="margin-top: 16px;">
                            <img src="${escapeHtml(embedData.image.url)}" style="
                                max-width: 100%;
                                width: 100%;
                                border-radius: 4px;
                                height: auto;
                                display: block;
                            " alt="Embed Image" onerror="console.error('Image failed to load:', '${escapeHtml(embedData.image.url)}')">
                        </div>
                    `;
                }
                
                // Thumbnail (right side) - WICHTIG für Discord-Genauigkeit  
                if (embedData.thumbnail && embedData.thumbnail.url) {
                    embedHtml += `
                        <div style="
                            float: right;
                            margin-left: 16px;
                            margin-top: -80px;
                            max-width: 80px;
                        ">
                            <img src="${escapeHtml(embedData.thumbnail.url)}" style="
                                max-width: 80px;
                                max-height: 80px;
                                border-radius: 4px;
                                object-fit: cover;
                                display: block;
                            " alt="Thumbnail" onerror="console.error('Thumbnail failed to load:', '${escapeHtml(embedData.thumbnail.url)}')">
                        </div>
                    `;
                }
                
                // Footer
                if (embedData.footer && (embedData.footer.text || embedData.footer.icon_url)) {
                    embedHtml += `
                        <div style="
                            display: flex;
                            align-items: center;
                            margin-top: 8px;
                            gap: 8px;
                            clear: both;
                        ">
                    `;
                    
                    if (embedData.footer.icon_url) {
                        embedHtml += `
                            <img src="${escapeHtml(embedData.footer.icon_url)}" style="
                                width: 20px;
                                height: 20px;
                                border-radius: 50%;
                            " alt="Footer Icon">
                        `;
                    }
                    
                    if (embedData.footer.text) {
                        embedHtml += `
                            <span style="
                                color: #72767d;
                                font-size: 12px;
                            ">${escapeHtml(embedData.footer.text)}</span>
                        `;
                    }
                    
                    embedHtml += '</div>';
                }
                
                // Update the color of the parent discord-embed container - EXTENDED DIAGNOSTICS!
                setTimeout(function() {
                    console.log('=== COLOR UPDATE DEBUGGING ===');
                    console.log('Target color:', color);
                    console.log('Container element:', $container[0]);
                    console.log('Container HTML:', $container.html().substring(0, 100) + '...');
                    
                    // Try multiple approaches to find and update the color
                    const $discordEmbed = $container.closest('.discord-embed');
                    const $parentEmbed = $container.parent('.discord-embed');
                    const $anyEmbed = $('.discord-embed');
                    
                    console.log('Searching for discord-embed containers...');
                    console.log('Found closest:', $discordEmbed.length, $discordEmbed[0]);
                    console.log('Found parent:', $parentEmbed.length, $parentEmbed[0]);
                    console.log('Found any:', $anyEmbed.length);
                    
                    // List all discord-embed elements for debugging
                    $('.discord-embed').each(function(i) {
                        console.log(`Discord embed ${i}:`, this, 'Current border-left-color:', $(this).css('border-left-color'));
                    });
                    
                    if ($discordEmbed.length > 0) {
                        // Versuche mehrere Methoden zur Farbänderung
                        $discordEmbed.css({
                            'border-left-color': color + ' !important',
                            'border-left-width': '4px',
                            'border-left-style': 'solid'
                        });
                        
                        // Zusätzlich: Direkte Stil-Attribute setzen (überschreibt CSS)
                        $discordEmbed[0].style.setProperty('border-left-color', color, 'important');
                        $discordEmbed[0].style.borderLeftWidth = '4px';
                        $discordEmbed[0].style.borderLeftStyle = 'solid';
                        
                        console.log('✓ Applied color via CSS and direct style');
                        console.log('  - CSS method result:', $discordEmbed.css('border-left-color'));
                        console.log('  - Direct style result:', $discordEmbed[0].style.borderLeftColor);
                    } else if ($parentEmbed.length > 0) {
                        $parentEmbed.css({
                            'border-left-color': color + ' !important',
                            'border-left-width': '4px',
                            'border-left-style': 'solid'
                        });
                        console.log('✓ Updated embed border color via parent, new color:', $parentEmbed.css('border-left-color'));
                    } else if ($anyEmbed.length > 0) {
                        $anyEmbed.css({
                            'border-left-color': color + ' !important',
                            'border-left-width': '4px',
                            'border-left-style': 'solid'
                        });
                        console.log('✓ Updated embed border color via any, new color:', $anyEmbed.css('border-left-color'));
                    } else {
                        console.warn('✗ No discord-embed container found for color update');
                    }
                }, 100);
                
            } else {
                // For custom containers, create the full Discord mockup
                embedHtml = `
                    <div style="
                        background: #36393f;
                        padding: 20px;
                        border-radius: 8px;
                        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                        max-width: 100%;
                        margin: 0;
                    ">
                        <div style="
                            background: #2f3136;
                            border-left: 4px solid ${color};
                            border-radius: 4px;
                            padding: 16px;
                            position: relative;
                            max-width: 100%;
                        ">
                `;
                
                // Title
                if (hasTitle) {
                    const titleContent = embedData.url ? 
                        `<a href="${escapeHtml(embedData.url)}" target="_blank" style="color: #00b0f4; text-decoration: none;">${escapeHtml(embedData.title)}</a>` :
                        escapeHtml(embedData.title);
                    
                    embedHtml += `
                        <div style="
                            color: #ffffff;
                            font-size: 16px;
                            font-weight: 600;
                            margin-bottom: ${hasDescription ? '8px' : '0'};
                            line-height: 1.375;
                        ">${titleContent}</div>
                    `;
                }
                
                // Description
                if (hasDescription) {
                    embedHtml += `
                        <div style="
                            color: #dcddde;
                            font-size: 14px;
                            line-height: 1.375;
                            margin-bottom: 8px;
                            white-space: pre-wrap;
                            word-wrap: break-word;
                        ">${parseMarkdown(embedData.description)}</div>
                    `;
                }
                
                // Close the embed
                embedHtml += `
                        </div>
                    </div>
                `;
            }
            
            console.log('Generated HTML length:', embedHtml.length);
            console.log('Setting HTML to container...');
            console.log('Container jQuery object:', $container);
            console.log('Is main preview:', isMainPreview);
            
            if ($container.length === 0) {
                console.error('❌ CRITICAL: Container still not found after creation attempt!');
                // Create a fallback container
                $('body').append('<div id="preview-container-fallback" style="border: 2px solid red; padding: 20px; margin: 20px; background: white;"><h3>FALLBACK PREVIEW CONTAINER</h3></div>');
                $('#preview-container-fallback').html(embedHtml);
                console.log('Used fallback container');
                return;
            }
            
            // Set the HTML
            $container.html(embedHtml);
            
            // Verify the content was set
            setTimeout(function() {
                const contentLength = $container.html().length;
                console.log('Container content length after setting:', contentLength);
                if (contentLength === 0) {
                    console.error('❌ Content was not set properly!');
                } else {
                    console.log('✅ Preview content set successfully!');
                    console.log('Preview is visible:', $container.is(':visible'));
                    console.log('Container parent:', $container.parent().attr('class'));
                }
            }, 100);
            
            console.log('=== RENDER PREVIEW END ===');
        }
        
        // Message History Functions
        window.messageHistoryData = {}; // Global storage for message data
        
        function renderMessageHistory(messages) {
            console.log('Rendering message history:', messages);
            
            // Clear previous data
            window.messageHistoryData = {};
            
            let $historyContainer = $('.history-content');
            if ($historyContainer.length === 0) {
                console.log('Creating history section...');
                $('.preview-section').after(`
                    <div class="history-section">
                        <h2>Gesendete Nachrichten</h2>
                        <div class="history-content"></div>
                    </div>
                `);
                $historyContainer = $('.history-content');
            }
            
            if (!messages || messages.length === 0) {
                $historyContainer.html(`
                    <div style="text-align: center; padding: 40px; color: #666;">
                        <h3>Keine gesendeten Nachrichten gefunden</h3>
                        <p>Senden Sie eine Nachricht, um sie hier zu sehen.</p>
                        <button type="button" id="manual-refresh-history" class="button">🔄 Erneut laden</button>
                    </div>
                `);
                
                // Manual refresh button
                $('#manual-refresh-history').off('click').on('click', function() {
                    loadMessageHistory();
                });
                return;
            }
            
            let html = `
                <div class="message-history-header" style="
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 20px;
                    padding: 15px;
                    background: #f8f9fa;
                    border-radius: 4px;
                ">
                    <h3 style="margin: 0;">${messages.length} Gesendete Nachrichten</h3>
                    <button type="button" id="refresh-history-btn" class="button">🔄 Aktualisieren</button>
                </div>
                <div class="message-history-list">
            `;
            
            messages.forEach(function(message, index) {
                let embedData = {};
                try {
                    embedData = JSON.parse(message.embed_data || '{}');
                } catch (e) {
                    embedData = { title: 'Parsing Error' };
                }
                
                // Store data globally with unique index
                const messageIndex = 'msg_' + index + '_' + Date.now();
                window.messageHistoryData[messageIndex] = embedData;
                
                const color = embedData.color ? '#' + embedData.color.toString(16).padStart(6, '0') : '#5865f2';
                const title = embedData.title || 'Untitled';
                const description = embedData.description || '';
                const statusClass = message.status === 'sent' ? 'success' : 'error';
                const statusIcon = message.status === 'sent' ? '✅' : '❌';
                
                // Format date
                const sentDate = new Date(message.sent_at);
                const formattedDate = sentDate.toLocaleString('de-DE');
                
                html += `
                    <div class="message-history-item" style="
                        border-left: 4px solid ${color};
                        background: #fff;
                        border: 1px solid #ddd;
                        border-radius: 4px;
                        padding: 15px;
                        margin-bottom: 15px;
                        position: relative;
                    ">
                        <div class="message-header" style="
                            display: flex;
                            justify-content: space-between;
                            align-items: flex-start;
                            margin-bottom: 10px;
                        ">
                            <div style="flex: 1;">
                                <h4 style="margin: 0 0 5px 0; color: #333;">${escapeHtml(title)}</h4>
                                ${description ? `<p style="margin: 0; color: #666; font-size: 14px; line-height: 1.4;">${escapeHtml(description.substring(0, 150))}${description.length > 150 ? '...' : ''}</p>` : ''}
                            </div>
                            <div style="
                                display: flex;
                                gap: 8px;
                                flex-shrink: 0;
                                margin-left: 15px;
                            ">
                                <button type="button" class="button load-message-btn" data-message-index="${messageIndex}" title="In Editor laden">
                                    📥 Laden
                                </button>
                                <span class="message-status ${statusClass}" style="
                                    padding: 4px 8px;
                                    border-radius: 3px;
                                    font-size: 11px;
                                    font-weight: 600;
                                    text-transform: uppercase;
                                    background: ${message.status === 'sent' ? '#d4edda' : '#f8d7da'};
                                    color: ${message.status === 'sent' ? '#155724' : '#721c24'};
                                    display: flex;
                                    align-items: center;
                                    gap: 4px;
                                ">${statusIcon} ${message.status}</span>
                            </div>
                        </div>
                        <div class="message-meta" style="
                            font-size: 12px;
                            color: #888;
                            line-height: 1.4;
                            border-top: 1px solid #eee;
                            padding-top: 10px;
                            margin-top: 10px;
                        ">
                            <div><strong>📅 Gesendet:</strong> ${formattedDate}</div>
                            <div><strong>🔗 Webhook:</strong> ${escapeHtml((message.webhook_url || 'N/A').substring(0, 50))}${message.webhook_url && message.webhook_url.length > 50 ? '...' : ''}</div>
                            ${message.channel_id ? `<div><strong>📺 Channel ID:</strong> ${escapeHtml(message.channel_id)}</div>` : ''}
                            ${message.webhook_type ? `<div><strong>⚙️ Type:</strong> ${escapeHtml(message.webhook_type)}</div>` : ''}
                            ${message.error_message ? `<div style="color: #dc3545;"><strong>⚠️ Fehler:</strong> ${escapeHtml(message.error_message.substring(0, 100))}${message.error_message.length > 100 ? '...' : ''}</div>` : ''}
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            $historyContainer.html(html);
            
            // Bind refresh button
            $('#refresh-history-btn').off('click').on('click', function() {
                loadMessageHistory();
            });
            
            // Bind load message buttons with NEW approach
            $('.load-message-btn').off('click').on('click', function(e) {
                e.preventDefault();
                console.log('Load message button clicked');
                
                try {
                    const messageIndex = $(this).attr('data-message-index');
                    console.log('Message index:', messageIndex);
                    
                    if (!messageIndex || !window.messageHistoryData[messageIndex]) {
                        console.error('No message data found for index:', messageIndex);
                        alert(__l('noDataToLoad', 'No data to load found'));
                        return;
                    }
                    
                    const messageData = window.messageHistoryData[messageIndex];
                    console.log('Parsed message data:', messageData);
                    
                    loadMessageIntoEditor(messageData);
                } catch (error) {
                    console.error('Error loading message:', error);
                    alert(__l('errorLoadingMessage', 'Error loading message'));
                }
            });
        }
        
        // Load message into editor function
        function loadMessageIntoEditor(embedData) {
            console.log('Loading message into editor:', embedData);
            
            if (!embedData) {
                console.error('No embed data provided');
                return;
            }
            
            try {
                // Clear all fields first
                $('#embed-title, #embed-description, #embed-url, #thumbnail-url, #image-url, #author-name, #author-url, #author-icon, #footer-text, #footer-icon').val('');
                $('#fields-container').empty();
                
                // Fill basic fields
                if (embedData.title) $('#embed-title').val(embedData.title);
                if (embedData.description) $('#embed-description').val(embedData.description);
                if (embedData.url) $('#embed-url').val(embedData.url);
                
                // Thumbnail and image
                if (embedData.thumbnail && embedData.thumbnail.url) {
                    $('#thumbnail-url').val(embedData.thumbnail.url);
                }
                if (embedData.image && embedData.image.url) {
                    $('#image-url').val(embedData.image.url);
                }
                
                // Color
                if (embedData.color) {
                    let colorHex;
                    if (typeof embedData.color === 'string') {
                        colorHex = embedData.color.startsWith('#') ? embedData.color : '#' + embedData.color;
                    } else {
                        colorHex = '#' + embedData.color.toString(16).padStart(6, '0');
                    }
                    $('#embed-color').val(colorHex);
                    console.log('Setting color to:', colorHex);
                }
                
                // Author fields
                if (embedData.author) {
                    if (embedData.author.name) $('#author-name').val(embedData.author.name);
                    if (embedData.author.url) $('#author-url').val(embedData.author.url);
                    if (embedData.author.icon_url) $('#author-icon').val(embedData.author.icon_url);
                }
                
                // Footer fields
                if (embedData.footer) {
                    if (embedData.footer.text) $('#footer-text').val(embedData.footer.text);
                    if (embedData.footer.icon_url) $('#footer-icon').val(embedData.footer.icon_url);
                }
                
                // Clear template selection
                $('#template-select').val('');
                $('#template-name').val('');
                
                // Trigger change events to update preview
                setTimeout(function() {
                    $('#embed-title, #embed-description, #embed-color').trigger('input');
                    updatePreview();
                    
                    // Scroll to top
                    $('html, body').animate({ scrollTop: 0 }, 500);
                    
                    // Show success message
                    alert(__l('messageLoadedSuccessfully', 'Message was successfully loaded into the editor!'));
                }, 200);
                
            } catch (error) {
                console.error('Error loading message into editor:', error);
                alert(__l('errorLoadingMessage', 'Error loading message'));
            }
        }
        
        // Load message history function
        function loadMessageHistory() {
            console.log('Loading message history...');
            
            $.ajax({
                url: discordEmbed.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'load_message_history',
                    nonce: discordEmbed.nonce
                },
                success: function(response) {
                    console.log('Message history loaded:', response);
                    if (response.success && response.data) {
                        // Handle new pagination format
                        const messages = response.data.messages || response.data;
                        renderMessageHistory(messages);
                    } else {
                        console.log('No message history found or error:', response);
                        renderMessageHistory([]);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading message history:', {xhr: xhr.responseText, status, error});
                    renderMessageHistory([]);
                }
            });
        }
        
        // Initialize everything
        function initializeUIFixes() {
            console.log('Initializing UI Fixes...');
            
            // Bind form change events for LIVE preview - ERWEITERT für Fields!
            $('#embed-form').off('input.uifix change.uifix keyup.uifix paste.uifix').on('input.uifix change.uifix keyup.uifix paste.uifix', 'input, textarea, select', function() {
                console.log('Form field changed, updating preview...', $(this).attr('id') || $(this).attr('class'));
                updatePreview();
            });
            
            // SPEZIELLE Field Event-Bindings für dynamische Fields
            $(document).off('input.fieldfix change.fieldfix keyup.fieldfix').on('input.fieldfix change.fieldfix keyup.fieldfix', '.field-name, .field-value, .inline-checkbox', function() {
                console.log('FIELD element changed:', $(this).attr('class'), 'Value:', $(this).val());
                updatePreview();
            });
            
            // Also bind to specific important fields
            $('#embed-title, #embed-description, #embed-url, #embed-color').off('input.livepreview keyup.livepreview').on('input.livepreview keyup.livepreview', function() {
                console.log('Key field changed:', $(this).attr('id'), '=', $(this).val());
                updatePreview();
            });
            
            // Color picker needs special handling
            $('#embed-color').off('input.colorchange change.colorchange').on('input.colorchange change.colorchange', function() {
                console.log('Color changed to:', $(this).val());
                updatePreview();
            });
            
            // Override existing updatePreview if it exists (CRITICAL!)
            if (typeof window.updatePreview === 'function') {
                console.log('Overriding existing updatePreview function from admin.js');
            }
            window.updatePreview = updatePreview;
            
            // Also override any global updatePreview function
            if (typeof updatePreview !== 'undefined' && updatePreview !== window.updatePreview) {
                console.log('Multiple updatePreview functions detected - using our enhanced version');
            }
            
            // Force our function to be the global one
            this.updatePreview = updatePreview;
            
            // Ensure template loading triggers our updatePreview
            $(document).off('template-loaded.uifix').on('template-loaded.uifix', function() {
                console.log('Template loaded event detected, updating preview...');
                setTimeout(updatePreview, 100);
            });
            
            // Initial preview update
            setTimeout(function() {
                console.log('Initial preview update...');
                updatePreview();
            }, 500);
            
            // Load message history
            setTimeout(loadMessageHistory, 1000);
            
            console.log('UI Fixes initialized successfully!');
        }
        
        // Start initialization
        setTimeout(initializeUIFixes, 100);
        
        // Auto-refresh message history every 30 seconds
        setInterval(function() {
            if ($('.history-section:visible').length > 0) {
                loadMessageHistory();
            }
        }, 30000);
        
        console.log('%c=== DISCORD EMBED PLUGIN UI FIX V3.0 LOADED ===', 'color: #0073aa; font-size: 16px; font-weight: bold;');
        
    });
    
})(jQuery);
