sap.ui.define([
	"sap/ui/test/Opa5"
], (Opa5) => {
	"use strict";

	return Opa5.extend("my.app.test.integration.arrangements.Startup", {

		iStartMyApp() {
			this.iStartMyUIComponent({
				componentConfig: {
					name: "my.app",
					async: true,
					manifest: true
				}
			});
		}

	});
});
