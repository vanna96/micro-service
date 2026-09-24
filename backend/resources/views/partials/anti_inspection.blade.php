@php
    $antiInspectActive = \App\Models\SecuritySetting::getBool('anti_inspection_enabled', false);
    $disableRightClick = \App\Models\SecuritySetting::getBool('disable_right_click', false);
    $disableDevToolsKeys = \App\Models\SecuritySetting::getBool('disable_devtools_keys', false);
@endphp

@if ($antiInspectActive)
<script>
    (function () {
        'use strict';

        @if ($disableRightClick)
        // Disable Right-Click Context Menu
        document.addEventListener('contextmenu', function (e) {
            e.preventDefault();
            return false;
        }, false);
        @endif

        @if ($disableDevToolsKeys)
        // Disable DevTools Keyboard Shortcuts
        document.addEventListener('keydown', function (e) {
            // F12
            if (e.key === 'F12' || e.keyCode === 123) {
                e.preventDefault();
                e.stopPropagation();
                return false;
            }

            // Ctrl+Shift+I, Ctrl+Shift+J, Ctrl+Shift+C (or Cmd+Opt+I on Mac)
            if ((e.ctrlKey || e.metaKey) && e.shiftKey && ['I', 'i', 'J', 'j', 'C', 'c'].includes(e.key)) {
                e.preventDefault();
                e.stopPropagation();
                return false;
            }

            // Ctrl+U (View Source)
            if ((e.ctrlKey || e.metaKey) && (e.key === 'u' || e.key === 'U')) {
                e.preventDefault();
                e.stopPropagation();
                return false;
            }
        }, false);
        @endif
    })();
</script>
@endif
