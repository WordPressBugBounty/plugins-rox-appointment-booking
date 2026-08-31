// Adds a web-font stylesheet to a document once.
//
// Block editor previews live inside an iframe with their own document, so the
// link has to go into whichever document the target node actually belongs to
// rather than the one this script happens to run in.
//
// Deduped on the href, not an id of our own: on a published page PHP has
// normally already enqueued the very same stylesheet under a WordPress handle,
// and matching on the URL is what stops a second copy landing next to it.
export const ensureWebFont = (doc, href) => {
	if (!doc || !href || doc.querySelector(`link[href="${href}"]`)) {
		return;
	}

	const link = doc.createElement("link");
	link.rel = "stylesheet";
	link.href = href;
	doc.head.appendChild(link);
};

// Builds the Google Fonts URL for one entry of the shared font list. Entries
// for locally available faces carry no `google` value and need no request.
export const googleFontHref = (font) =>
	font?.google
		? `https://fonts.googleapis.com/css2?family=${font.google}&display=swap`
		: "";
