/**
 * Discord Embed Plugin UI Fix v3.0 - Complete Rewrite
 * Fixes Preview Rendering Issues
 */

(function($) {
    'use strict';

    // Lightweight UI enhancer; do not compete with admin.js when present
    const hasAdmin = () => typeof window.discordEmbedAdminInitialized !== 'undefined' && window.discordEmbedAdminInitialized === true;

    // Debounce helper
    function debounce(fn, wait) {
        let t;
        return function() {
            const ctx = this, args = arguments;
            clearTimeout(t);
            t = setTimeout(function(){ fn.apply(ctx, args); }, wait);
        };
    }

    // Safe markdown escape used only for preview addons if needed
    function escapeHtml(text) { const d = document.createElement('div'); d.textContent = text || ''; return d.innerHTML; }

    $(function() {
        // If admin.js is loaded, do not override critical functions
        if (hasAdmin()) {
            // Bind only minimal cosmetic listeners, let admin.js handle preview and history
            const debouncedTrigger = debounce(function(){ if (typeof window.updatePreview === 'function') window.updatePreview(); }, 200);
            $('#embed-form').off('.uifix').on('input.uifix change.uifix', 'input, textarea, select', debouncedTrigger);

            // Avoid auto-creating containers or loading history here; admin.js manages it
            return;
        }

        // Fallback mode: provide a minimal updatePreview when admin.js is absent
        function collectFallbackData() {
            const data = {
                title: $('#embed-title').val() || '',
                description: $('#embed-description').val() || '',
                url: $('#embed-url').val() || '',
                color: $('#embed-color').val() || '#5865f2'
            };
            const fields = [];
            $('#fields-container .embed-field').each(function(){
                const name = $(this).find('.field-name').val();
                const value = $(this).find('.field-value').val();
                const inline = $(this).find('.inline-checkbox').is(':checked');
                if (name || value) fields.push({ name: name||'', value: value||'', inline: !!inline });
            });
            if (fields.length) data.fields = fields;
            return data;
        }

        function renderFallbackPreview(embedData) {
            const $container = $('#embed-preview');
            if ($container.length === 0) return;
            const color = embedData.color || '#5865f2';
            let html = '';
            if (embedData.title) html += `<div style="color:#fff;font-weight:600;margin-bottom:6px;">${escapeHtml(embedData.title)}</div>`;
            if (embedData.description) html += `<div style="color:#dcddde;">${escapeHtml(embedData.description)}</div>`;
            if (embedData.fields && embedData.fields.length) {
                embedData.fields.forEach(f=>{
                    html += `<div style="margin-top:8px;"><div style="color:#fff;font-weight:600;">${escapeHtml(f.name)}</div><div style="color:#dcddde;">${escapeHtml(f.value)}</div></div>`;
                });
            }
            $container.html(`<div style="border-left:4px solid ${color};padding-left:12px;">${html}</div>`);
        }

        const debouncedFallback = debounce(function(){ renderFallbackPreview(collectFallbackData()); }, 200);
        $('#embed-form').on('input.uifix change.uifix', 'input, textarea, select', debouncedFallback);
        debouncedFallback();
    });
})(jQuery);
