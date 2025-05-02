sap.ui.define([ 
	"sap/ui/core/mvc/Controller",
    "my/app/util/HttpService",
    "sap/m/MessageBox",
    "my/app/util/Cookie"
], (
    Controller,
    HttpService,
    MessageBox,
    Cookie
) => {
	"use strict";

	return Controller.extend("my.app.controller.Authentication.Login", {
		onInit: function () {
            document.title = "Login";
			this.oModel = new sap.ui.model.json.JSONModel({
				loginButton: "Login"
			});  
			this.getView().setModel(this.oModel, "login");
        },

		onLoginPress: async function (){
            const that = this;
            this.oModel.setProperty('/loginButton', 'Loading...');
            const username = this.byId("usernameInput").getValue();
            const password = this.byId("passwordInput").getValue();
            const companyDB = this.byId("companyDBInput").getValue();

            const payload = {
                "CompanyDB": companyDB,
                "UserName": username,
                "Password": password
            }; 

            try {
                const data = await HttpService.callApi("POST", "Login", payload);  
                Cookie.setCookie("B1SESSION", data.SessionId, 30); 
                const oRouter = sap.ui.core.UIComponent.getRouterFor(that);
                oRouter.navTo("dashboard");
        
            } catch (error) { 
                let errorMessage = "Unknown error occurred.";
                if (error && error.responseText) {
                    const response = JSON.parse(error.responseText);
                    if (response.error && response.error.message) {
                        errorMessage = response.error.message.value || response.error.message;
                    }
                }

                MessageBox.error(errorMessage);
            } finally {
                this.oModel.setProperty('/loginButton', 'Login');
            }
        },
	});

});
