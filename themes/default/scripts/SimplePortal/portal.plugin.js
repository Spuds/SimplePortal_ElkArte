/**
 * @package SimplePortal ElkArte
 *
 * @author SimplePortal Team
 * @copyright 2015-2026 SimplePortal Team
 * @license BSD 3-clause
 * @version 2.0.0
 */

/** global: change_type, start_state */

(function(sceditor) {
	'use strict';

	let editor;

	sceditor.plugins.portal = function(initial_state, new_state) {
		let base = this;

		if (typeof new_state !== "undefined")
		{
			// Changing the editors type, attempt to convert the language
			sp_to_new(initial_state, new_state);
		}

		/**
		 * Called before signalReady as part of the editor startup process
		 */
		base.init = function() {
			editor = this;

			sp_editor_change_type(change_type);
		};

		/**
		 * Initialize, called when sceditor starts and initializes plugins
		 */
		base.signalReady = function() {
			if (start_state !== "bbc")
			{
				editor.sourceMode(true);
				document.getElementById("editor_toolbar_container").style.display = "none";
			}
		};
	};
}(sceditor));
