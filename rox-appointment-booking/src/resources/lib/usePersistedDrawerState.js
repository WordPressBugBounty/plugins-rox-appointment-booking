/**
 * Drop-in replacement for `useState(null)` that mirrors a drawer's open-state to
 * `localStorage`, so the open drawer stack survives a full page reload.
 *
 * Each drawer-owner (a page, or a form drawer that hosts a nested child) passes a
 * stable unique `key`. The value it stores is the same descriptor the component
 * already kept in state (e.g. `{ kind, record }`, `{ mode, id }`, an id, …); it
 * is serialised with `JSON.stringify`, so non-serialisable parts such as an
 * `onCreated` callback simply drop out of the persisted copy (they stay in the
 * live in-memory state for the current session, and are absent after a reload —
 * the consuming code already guards them with optional chaining).
 *
 * The drawer chain is always linear (one drawer per owner, page → nested child),
 * so restoring each owner's value independently rebuilds the exact open order,
 * and clearing a value (set to `null` on close) removes its key — preserving the
 * open→close flow. The initial value is read lazily from `localStorage`, which is
 * only repopulated on a real reload (a live close clears it first), so a freshly
 * opened drawer never inherits a stale child.
 */

import { useState, useCallback } from "react";

const STORAGE_PREFIX = "rox_appointment_booking.drawer.";

function read(key) {
  try {
    const raw = localStorage.getItem(STORAGE_PREFIX + key);
    return raw ? JSON.parse(raw) : null;
  } catch {
    return null;
  }
}

function write(key, value) {
  try {
    if (value == null) {
      localStorage.removeItem(STORAGE_PREFIX + key);
    } else {
      localStorage.setItem(STORAGE_PREFIX + key, JSON.stringify(value));
    }
  } catch {
    /* storage unavailable (private mode / quota) — fall back to in-memory only */
  }
}

/**
 * @param {string} key Stable unique identifier for this drawer-owner.
 * @return {[any, Function]} `[state, setState]`, where `setState` accepts a value
 *   or an updater function, mirroring `useState`.
 */
export default function usePersistedDrawerState(key) {
  const [state, setState] = useState(() => read(key));

  const set = useCallback(
    (value) => {
      setState((prev) => {
        const next = typeof value === "function" ? value(prev) : value;
        write(key, next);
        return next;
      });
    },
    [key],
  );

  return [state, set];
}
