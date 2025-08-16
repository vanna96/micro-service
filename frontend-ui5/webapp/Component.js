sap.ui.define([
    "sap/ui/core/UIComponent",
    "my/app/util/Cookie",
    "my/app/routes/index",
    "my/app/routes/master",
], (
    UIComponent,
    Cookie,
    index,
    master
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
            const aRouteDefs = [index, master];
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

        _onRouteMatched: function (oEvent) {
            const oRouter = this.getRouter();
            const sRouteName = oEvent.getParameter("name");

            if (sRouteName === "login" && this._checkAuthentication()) {
                oRouter.navTo("dashboard");
            } else if (sRouteName !== "login" && !this._checkAuthentication()) {
                oRouter.navTo("login");
            }
        },

        _checkAuthentication: function () {
            console.log(document.cookie)
            const authCookie = document.cookie.split(';').find(cookie => cookie.trim().startsWith("XSRF-TOKEN="));
            return authCookie !== undefined;
        }
    });
});
