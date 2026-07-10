import { registerBlockType } from "@wordpress/blocks";
import metadata from "./block.json";
import Edit from "./edit.jsx";
import "./editor.scss";

// Colored block icon (service-list cards with a booking accent). Set here on
// registerBlockType because block.json `icon` only supports dashicon slugs.
const icon = (
	<svg
		width="24"
		height="24"
		viewBox="0 0 24 24"
		fill="none"
		xmlns="http://www.w3.org/2000/svg"
	>
		<rect x="2" y="4" width="20" height="16" rx="2.5" fill="#EEF2FF" />
		<rect x="5" y="7.5" width="7" height="2.5" rx="1.25" fill="#3560FB" />
		<rect x="5" y="11.5" width="11" height="2" rx="1" fill="#9AA0AC" />
		<rect x="5" y="15" width="9" height="2" rx="1" fill="#C7D2FE" />
		<circle cx="18" cy="9" r="2.5" fill="#3560FB" />
		<path
			d="M16.9 9l.8.8 1.5-1.6"
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
	// Dynamic block: markup is produced by the PHP render_callback.
	save: () => null,
});
