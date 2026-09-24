import { useEffect } from "react";

interface SecurityClientConfig {
  anti_inspection_enabled?: boolean;
  disable_right_click?: boolean;
  disable_devtools_keys?: boolean;
  under_attack_mode?: boolean;
}

export function AntiInspectShield() {
  useEffect(() => {
    let isSubscribed = true;

    async function loadSecurityPolicy() {
      try {
        const res = await fetch("/v1/api/security/client-config", {
          cache: "no-store",
          headers: { Accept: "application/json" },
        });

        if (!res.ok || !isSubscribed) return;

        const config = (await res.json()) as SecurityClientConfig;

        // 1. Right-Click Context Menu Lock
        if (config.disable_right_click) {
          const handleContextMenu = (e: MouseEvent) => {
            e.preventDefault();
            return false;
          };
          window.addEventListener("contextmenu", handleContextMenu);
        }

        // 2. DevTools Keyboard Shortcut Lock
        if (config.disable_devtools_keys) {
          const handleKeyDown = (e: KeyboardEvent) => {
            // F12
            if (e.key === "F12" || e.keyCode === 123) {
              e.preventDefault();
              e.stopPropagation();
              return false;
            }

            // Ctrl+Shift+I / Ctrl+Shift+J / Ctrl+Shift+C (or Cmd+Opt on Mac)
            if (
              (e.ctrlKey || e.metaKey) &&
              e.shiftKey &&
              ["I", "i", "J", "j", "C", "c"].includes(e.key)
            ) {
              e.preventDefault();
              e.stopPropagation();
              return false;
            }

            // Ctrl+U / Cmd+U (View Source)
            if ((e.ctrlKey || e.metaKey) && (e.key === "u" || e.key === "U")) {
              e.preventDefault();
              e.stopPropagation();
              return false;
            }
          };

          window.addEventListener("keydown", handleKeyDown, true);
        }
      } catch (err) {
        // Silently continue if config is unavailable
      }
    }

    loadSecurityPolicy();

    return () => {
      isSubscribed = false;
    };
  }, []);

  return null;
}
