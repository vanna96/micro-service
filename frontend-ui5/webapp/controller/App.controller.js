sap.ui.define([
	"sap/ui/Device",
	"sap/ui/core/mvc/Controller",
	"sap/ui/model/Filter",
	"sap/ui/model/FilterOperator",
	"sap/ui/model/json/JSONModel",
	"sap/base/strings/formatMessage"
], (Device, Controller) => {
	"use strict";

	return Controller.extend("my.app.controller.App", {
		onInit: function () {  
			document.title = "App";
        }		
	});

});
