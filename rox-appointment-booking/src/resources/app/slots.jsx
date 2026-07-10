/**
 * Minimal named-slot (SlotFill) registry for in-page extension.
 *
 * Whole-page extension is handled by the page-route registry (app/routes.jsx);
 * this covers the finer-grained case where an add-on (the Pro plugin) needs to
 * inject UI *inside* a free page — e.g. an extra tab on the Services page, or an
 * extra field on a form. A free page renders `<Slot name="x" />` wherever it
 * allows extension; an add-on calls `registerFill("x", id, <Node/>)`.
 *
 * Fills are read at render time (no live re-render): add-on bundles register
 * their fills during page load, before the app's deferred first render, so they
 * are present when a `<Slot>` renders — the same timing model as the route
 * registry.
 */

import React from "react";

/**
 * slotName -> array of { id, node }.
 *
 * @type {Object<string, Array<{id: string, node: React.ReactNode}>>}
 */
const slotFills = {};

/**
 * Register (or replace) a fill for a named slot.
 *
 * @param {string}          slotName Slot identifier.
 * @param {string}          fillId   Stable id; re-registering with the same id
 *                                   replaces the previous node.
 * @param {React.ReactNode} node     The element to render in the slot.
 * @return {void}
 */
export function registerFill(slotName, fillId, node) {
  if (!slotFills[slotName]) {
    slotFills[slotName] = [];
  }
  slotFills[slotName] = slotFills[slotName].filter((fill) => fill.id !== fillId);
  slotFills[slotName].push({ id: fillId, node });
}

/**
 * Get the registered fill nodes for a slot.
 *
 * @param {string} slotName Slot identifier.
 * @return {React.ReactNode[]}
 */
export function getFills(slotName) {
  return (slotFills[slotName] || []).map((fill) => fill.node);
}

/**
 * Render all fills registered for a named slot.
 *
 * @param {{name: string}} props
 * @return {React.ReactElement}
 */
export function Slot({ name }) {
  return (
    <>
      {getFills(name).map((node, index) => (
        <React.Fragment key={index}>{node}</React.Fragment>
      ))}
    </>
  );
}

/**
 * Publish the fill-registration API on `window` so add-on bundles (loaded
 * separately) can register fills without importing this module.
 *
 * @return {void}
 */
export function exposeSlotApi() {
  if (typeof window === "undefined") {
    return;
  }
  window.rox_appointment_booking = window.rox_appointment_booking || {};
  window.rox_appointment_booking.slots = { registerFill };
}
