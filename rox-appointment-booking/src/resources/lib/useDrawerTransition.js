import { useEffect, useState } from "react";

// Matches antd Drawer's default close motion duration (unmodified in the
// app's theme — see App.jsx `components.Drawer`), so the drawer stays mounted
// exactly long enough for its own exit transition to finish playing.
const CLOSE_ANIMATION_MS = 300;

/**
 * Keeps returning the last truthy value of a nullable drawer-state variable
 * for CLOSE_ANIMATION_MS after it turns null, so the owning component can stay
 * mounted (and keep rendering its content) while antd's Drawer plays its
 * closing transition, instead of being unmounted the instant state clears.
 *
 * @param {*} value The raw drawer state (e.g. `drawer`, `childDrawer`, an id).
 * @return {*} `value` while truthy; the last truthy value for a beat after it
 *   goes null; then null.
 */
export default function useDrawerTransition(value) {
  const [display, setDisplay] = useState(value);

  useEffect(() => {
    if (value != null) {
      setDisplay(value);
      return;
    }
    const timer = setTimeout(() => setDisplay(null), CLOSE_ANIMATION_MS);
    return () => clearTimeout(timer);
  }, [value]);

  return display;
}
