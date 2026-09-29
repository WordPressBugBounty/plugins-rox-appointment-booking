/**
 * The block's font picker, shared by the controls and by every style's preview.
 *
 * The list itself is printed by BookingPanelBlock::registerEditorAssets(), so
 * PHP stays the only place it is defined and the picker here can never offer a
 * font the published panel cannot render.
 */

import { useEffect } from "@wordpress/element";

import { ensureWebFont, googleFontHref } from "../../../../lib/webFont.js";

/**
 * Fonts the panel offers, as `{ value, label, stack }` rows.
 *
 * @type {Array}
 */
export const fontOptions = window?.rox_appointment_booking?.fontFamilies || [];

/**
 * The row a saved key resolves to.
 *
 * An unknown key (a font dropped from the list) resolves to nothing, which
 * leaves the preview on the stylesheet's default exactly as the frontend.
 *
 * @param {string} fontFamily Saved font key.
 * @return {Object|null} Font row, or null.
 */
export const selectedFontFor = (fontFamily) =>
	fontOptions.find((option) => option.value === fontFamily) || null;

/**
 * Loads a chosen Google font into the document a preview is rendered in.
 *
 * The editor canvas is an iframe of its own, so the face has to land in *that*
 * document's head rather than the editor's — which is why this takes the node
 * rather than reaching for `document`.
 *
 * @param {Object} previewRef  Ref on any node inside the preview.
 * @param {Object} selectedFont Font row, or null.
 * @return {void}
 */
export const useWebFont = (previewRef, selectedFont) => {
	useEffect(() => {
		if (!selectedFont) {
			return;
		}

		ensureWebFont(
			previewRef.current?.ownerDocument,
			googleFontHref(selectedFont),
		);
	}, [selectedFont]);
};
