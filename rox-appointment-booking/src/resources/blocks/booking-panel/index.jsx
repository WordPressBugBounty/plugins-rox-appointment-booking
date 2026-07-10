import { registerBlockType } from "@wordpress/blocks";
import metadata from "./block.json";
import Edit from "./edit.jsx";
import "./editor.scss";

// Colored block icon (booking panel with a calendar accent). Set here on
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
		<rect x="2" y="3.5" width="20" height="4.5" rx="2.5" fill="#3560FB" />
		<rect x="6" y="2" width="2" height="4" rx="1" fill="#1E3A8A" />
		<rect x="16" y="2" width="2" height="4" rx="1" fill="#1E3A8A" />
		<rect x="5" y="11" width="5" height="5" rx="1" fill="#C7D2FE" />
		<rect x="12" y="11" width="7" height="2" rx="1" fill="#9AA0AC" />
		<rect x="12" y="14.5" width="7" height="2" rx="1" fill="#C7D2FE" />
		<circle cx="17.5" cy="15.5" r="3" fill="#22C55E" />
		<path
			d="M16.3 15.6l.8.8 1.6-1.7"
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
	// Dynamic block: markup is produced by the PHP render_callback, which mounts
	// the shortcode's frontend booking panel bundle.
	save: () => null,
});
