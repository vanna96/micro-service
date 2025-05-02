sap.ui.define(function () {
	"use strict";
	return {
		name: "QUnit test suite for Todo App",
		defaults: {
			page: "ui5://test-resources/my/app/Test.qunit.html?testsuite={suite}&test={name}",
			qunit: {
				version: 2
			},
			sinon: {
				version: 4
			},
			ui5: {
				language: "EN",
				theme: "sap_horizon"
			},
			coverage: {
				only: "my/app/",
				never: "test-resources/my/app/"
			},
			loader: {
				paths: {
					"my/app": "../"
				}
			}
		},
		tests: {
			"unit/unitTests": {
				title: "Unit tests for Todo App"
			},
			"integration/opaTests": {
				title: "Integration tests for Todo App"
			}
		}
	};
});
