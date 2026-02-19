/**
 * Leichtgewichtige Format-Toolbar für contenteditable Bereiche.
 * B, I, U, Schriftgröße (14–24 px). Keine externen Libraries.
 */
(function () {
    'use strict';

    var FONT_SIZES = [14, 16, 18, 20, 22, 24];

    function createToolbar() {
        var toolbar = document.createElement('div');
        toolbar.className = 'format-toolbar';

        var btnB = document.createElement('button');
        btnB.type = 'button';
        btnB.className = 'format-btn format-btn--bold';
        btnB.title = 'Fett';
        btnB.textContent = 'B';
        btnB.dataset.cmd = 'bold';

        var btnI = document.createElement('button');
        btnI.type = 'button';
        btnI.className = 'format-btn format-btn--italic';
        btnI.title = 'Kursiv';
        btnI.textContent = 'I';
        btnI.dataset.cmd = 'italic';

        var btnU = document.createElement('button');
        btnU.type = 'button';
        btnU.className = 'format-btn format-btn--underline';
        btnU.title = 'Unterstrichen';
        btnU.textContent = 'U';
        btnU.dataset.cmd = 'underline';

        var fontWrap = document.createElement('div');
        fontWrap.className = 'format-font-wrap';
        var fontSel = document.createElement('select');
        fontSel.className = 'format-font-size';
        fontSel.title = 'Schriftgröße';
        fontSel.dataset.cmd = 'fontSize';
        var defOpt = document.createElement('option');
        defOpt.value = '';
        defOpt.textContent = 'Schriftgröße';
        fontSel.appendChild(defOpt);
        FONT_SIZES.forEach(function (px) {
            var opt = document.createElement('option');
            opt.value = px;
            opt.textContent = px + ' px';
            fontSel.appendChild(opt);
        });
        fontWrap.appendChild(fontSel);

        toolbar.appendChild(btnB);
        toolbar.appendChild(btnI);
        toolbar.appendChild(btnU);
        toolbar.appendChild(fontWrap);

        return { toolbar: toolbar, fontSelect: fontSel };
    }

    function applyFormat(editor, cmd, value) {
        editor.focus();
        if (cmd === 'fontSize' && value) {
            var sel = window.getSelection();
            var range = sel.rangeCount ? sel.getRangeAt(0) : null;
            var txt = sel.toString();
            if (range && (txt || range.collapsed)) {
                document.execCommand('insertHTML', false,
                    '<span style="font-size: ' + parseInt(value, 10) + 'px">' + (txt || '\u200b') + '</span>');
                if (!txt) {
                    var r = sel.getRangeAt(0);
                    r.setStart(r.startContainer, 0);
                    r.collapse(true);
                    sel.removeAllRanges();
                    sel.addRange(r);
                }
            }
        } else {
            document.execCommand(cmd, false, value || null);
        }
    }

    function initEditor(wrapper) {
        var editor = wrapper.querySelector('.format-editor');
        var hidden = wrapper.querySelector('input[type="hidden"].format-value');
        if (!editor || !hidden) return;

        var tb = wrapper.querySelector('.format-toolbar');
        if (!tb) {
            var parts = createToolbar();
            tb = parts.toolbar;
            wrapper.insertBefore(tb, wrapper.firstChild);
        }

        var fontSel = tb.querySelector('.format-font-size');
        tb.querySelectorAll('.format-btn, .format-font-size').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                if (btn.tagName === 'SELECT') return;
                applyFormat(editor, btn.dataset.cmd);
            });
        });
        if (fontSel) {
            fontSel.addEventListener('change', function () {
                if (this.value) {
                    applyFormat(editor, 'fontSize', this.value);
                    this.value = '';
                }
            });
        }

        function syncToHidden() {
            var html = editor.innerHTML;
            if (html === '<br>' || html === '<div><br></div>' || html === '') html = '';
            hidden.value = html;
        }

        editor.addEventListener('input', syncToHidden);
        editor.addEventListener('blur', syncToHidden);

        wrapper.closest('form').addEventListener('submit', function () {
            syncToHidden();
        });
    }

    function init() {
        document.querySelectorAll('.format-toolbar-wrapper').forEach(function (el) {
            if (!el.dataset.init) {
                el.dataset.init = '1';
                initEditor(el);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    window.FormatToolbar = { init: init };
})();
