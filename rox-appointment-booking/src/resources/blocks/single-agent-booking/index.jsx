import { registerBlockType } from "@wordpress/blocks";
import metadata from "./block.json";
import Edit from "./edit.jsx";
import "./editor.scss";

// Colored block icon (a person with a booking accent). Set here on
// registerBlockType because block.json `icon` only supports dashicon slugs.
const icon = (
	<svg
		width="24"
		height="24"
		viewBox="0 0 24 24"
		fill="none"
		xmlns="http://www.w3.org/2000/svg"
	>
		<rect x="2" y="3.5" width="20" height="17" rx="2.5" fill="#EEF2FF" />
		<circle cx="9" cy="10" r="3" fill="#3560FB" />
		<path
			d="M4 17.5c0-2.4 2.2-4 5-4s5 1.6 5 4"
			stroke="#3560FB"
			strokeWidth="1.6"
			strokeLinecap="round"
		/>
		<rect x="15.5" y="8" width="4.5" height="2" rx="1" fill="#9AA0AC" />
		<rect x="15.5" y="11.5" width="4.5" height="2" rx="1" fill="#C7D2FE" />
		<circle cx="18.5" cy="16.5" r="2.6" fill="#22C55E" />
		<path
			d="M17.4 16.6l.7.7 1.4-1.5"
			stroke="#fff"
			strokeWidth="1"
			strokeLinecap="round"
			strokeLinejoin="round"
		/>
	</svg>
);

registerBlockType(metadata.name, {
	icon,
	edit: Edit,
	// Dynamic block: markup is produced by the Pro PHP render_callback, which
	// prints the same mount node the shortcode uses; the shared view bundle then
	// mounts the reused BookingService panel in single-agent mode.
	save: () => null,
});
