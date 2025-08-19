sap.ui.define([
    "sap/ui/core/UIComponent",
    "my/app/util/Cookie",
    "my/app/routes/index",
    "my/app/routes/master",
    "my/app/util/HttpService",
    "my/app/util/Crypto",
     "my/app/routes/administrator",
], (
    UIComponent,
    Cookie,
    index,
    master,
    HttpService,
    Crypto,
    administrator
) => {
    "use strict";
    return UIComponent.extend("my.app.Component", {
        metadata: {
            manifest: "json",
            interfaces: ["sap.ui.core.IAsyncContentCreation"],
        },

        init: function () {
            this._oSplitApp = this.byId("splitAppControl");
            UIComponent.prototype.init.apply(this, arguments);

            const oRouter = this.getRouter();
            const aRouteDefs = [index, master, administrator];
            aRouteDefs.forEach(oDef => {
                oDef.routes.forEach(route => {
                    oRouter.addRoute(route);
                });

                const oTargets = oRouter.getTargets();
                Object.entries(oDef.targets).forEach(([name, config]) => {
                    oTargets.addTarget(name, config);
                });
            });

            oRouter.initialize();
            oRouter.attachRouteMatched(this._onRouteMatched, this);
        },

        _onRouteMatched: async function (oEvent) {
            const oRouter = this.getRouter();
            const sRouteName = oEvent.getParameter("name"); 
            if (sRouteName === "login" && Cookie.getCookie("userData")/*await this._checkAuthentication()*/) {
                oRouter.navTo("dashboard");
            } else if (sRouteName !== "login" && !Cookie.getCookie("userData")/*await this._checkAuthentication()*/) {
                oRouter.navTo("login");
            }
        },

        _checkAuthentication: async function () {
            try {
                await HttpService.callApi("GET", HttpService.getUrl('auth'));
                return true;
            } catch (error) {
                console.log(error)
                return false;
            }
        },
    });
});
