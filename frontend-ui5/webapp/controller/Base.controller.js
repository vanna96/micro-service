sap.ui.define([
    "my/app/util/Helper",
    "sap/ui/core/mvc/Controller"
], (Helper, Controller) => {
	"use strict";

	return Controller.extend("my.app.controller.Base", {
        onInit: function (){  
            this.oModel = new sap.ui.model.json.JSONModel({});
            this.getView().setModel(this.oModel, "model");
            this.oRouter = sap.ui.core.UIComponent.getRouterFor(this);
        },
        
		formatDate: function (oDate) {
            if (oDate) {
                return Helper.dateFormat({ format: 'dd MMM, yyyy', value: oDate })
            }
            return "";
        },	
        
	});

});
