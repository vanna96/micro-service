sap.ui.define([
    "my/app/util/Helper",
    "sap/ui/core/mvc/Controller"
], (Helper, Controller) => {
	"use strict";

	return Controller.extend("my.app.controller.Base", {
        onInit: function (){  
            this.oModel = new sap.ui.model.json.JSONModel({});
            this.oModel.setSizeLimit(100000);
            this.getView().setModel(this.oModel, "model");
            this.oRouter = sap.ui.core.UIComponent.getRouterFor(this);
        },
        
		formatDisplayDate: function (oDate) {
            if (oDate) {
                return Helper.dateFormat({ format: 'dd MMM, yyyy', value: oDate })
            }
            return "";
        },	

        displayStatusState: function (status) {
            if (!status) return "None";
            return status.startsWith("A") ? "Success" : "Error";
        },  
        
        handlerNavBack: function(){
            window.history.back();
        }
	});

});
