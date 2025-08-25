sap.ui.define([
    "my/app/util/Helper",
    "sap/ui/core/mvc/Controller"
], (Helper, Controller) => {
    "use strict";

    return Controller.extend("my.app.controller.Base", {
        onInit: function () {
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

        handlerNavBack: function () {
            if (this.navBackDialog) {
                this.navBackDialog.destroy();
            }

            this.navBackDialog = new sap.m.Dialog({
                type: sap.m.DialogType.Message,
                title: "Warning",
                state: sap.ui.core.ValueState.Warning,
                content: new sap.m.Text({ text: "Are you sure you want to leave this page?" }),
                beginButton: new sap.m.Button({
                    type: sap.m.ButtonType.Emphasized,
                    text: "OK",
                    press: function () {
                        this.navBackDialog.close();
                        this.navBackDialog.destroy();
                        delete this.navBackDialog;
                        window.history.back();
                    }.bind(this)
                }),
                endButton: new sap.m.Button({
                    text: "Cancel",
                    press: function () {
                        this.navBackDialog.close();
                        this.navBackDialog.destroy();
                        delete this.navBackDialog;
                    }.bind(this)
                })
            });

            this.navBackDialog.open();
        },

        formatVisibleRowCount: function (perPage) {
            return perPage || 20; // default to 20 if undefined
        }
    });

});
