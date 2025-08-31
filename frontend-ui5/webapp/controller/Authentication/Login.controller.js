sap.ui.define([
    "sap/ui/core/mvc/Controller",
    "my/app/util/HttpService",
    "sap/m/MessageBox",
    "sap/m/MessageToast",
    "my/app/util/Cookie",
    "my/app/util/Crypto",
    "my/app/util/Funtion",
], (
    Controller,
    HttpService,
    MessageBox,
    MessageToast,
    Cookie,
    Crypto,
    Funtion
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

        onLoginPress: async function () {
            const that = this;
            this.oModel.setProperty('/loginButton', 'Loading...');
            const username = this.byId("usernameInput").getValue();
            const password = this.byId("passwordInput").getValue();

            const payload = {
                "username": username,
                "password": password
            };

            try {
                // await HttpService.callApi("GET", HttpService.getUrl('sanctum/csrf-cookie'));
                const res = await HttpService.callApi("POST", HttpService.getUrl('user/login'), payload);
                Cookie.setCookie("userData", Crypto.encryptData(res.data), res.data.timeout)
                const oRouter = sap.ui.core.UIComponent.getRouterFor(that);
                MessageToast.show("login was successful!")

                const tenants = [{
                    id: null,
                    db_name: 'Select Tenant'
                }];
                const userTenants = res.data?.user?.tenants || [];

                if (tenants.length > 0) {
                    tenants.push(...userTenants)
                    new Funtion.smgDialog({
                        title: 'Tanents',
                        content: new sap.m.Select({
                            width: "100%",
                            items: tenants.map(tenant => new sap.ui.core.Item({
                                key: tenant.id,
                                text: tenant.db_name
                            })),
                            change: function (oEvent) {
                                that.selectedTenantId = oEvent.getSource().getSelectedKey();
                                that.oModel.setProperty('/tenant_id', that.selectedTenantId);
                            }
                        }),
                        onOk: () => {
                            sessionStorage.setItem('tenant_id', that.selectedTenantId)
                            oRouter.navTo("dashboard")
                        },
                        onCancel: () => oRouter.navTo("dashboard"),
                    })
                } else setTimeout(() => oRouter.navTo("dashboard"), 1000)
            } catch (error) {
                console.error(error)
                let errorMessage = "Unknown error occurred.";
                if (error && error.responseJSON) {
                    if (error.responseJSON && error.responseJSON.message) {
                        errorMessage = error.responseJSON.message;
                    }
                }

                MessageBox.error(errorMessage);
            } finally {
                this.oModel.setProperty('/loginButton', 'Login');
            }
        },
    });

});
