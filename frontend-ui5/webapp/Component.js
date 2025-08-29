sap.ui.define([
    "sap/ui/core/UIComponent",
    "my/app/util/Cookie",
    "my/app/routes/index",
    "my/app/routes/master",
    "my/app/util/HttpService", 
    "my/app/routes/administrator",
    "my/app/routes/tenant",
    "my/app/routes/category",
], (
    UIComponent,
    Cookie,
    index,
    master,
    HttpService, 
    administrator,
    tenant,
    category
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
            const aRouteDefs = [index, category, administrator, tenant];
            // const isSubdomain = window.location.hostname.split('.').length > 2;
            // if (!isSubdomain) aRouteDefs.push(administrator, tenant);

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

            const sLanguage = sessionStorage.getItem("lng") || "en";
            this._setLanguage(sLanguage);

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

        _setLanguage: function (sLang) {
            const i18nModel = new sap.ui.model.resource.ResourceModel({
                bundleName: "my.app.i18n.i18n",
                locale: sLang
            });

            this.setModel(i18nModel, "i18n");
            sap.ui.getCore().setModel(i18nModel, "i18n");
            sap.ui.getCore().getConfiguration().setLanguage(sLang);
        },

        setLanguage: function (sLang) {
            sessionStorage.setItem("lng", sLang);
            this._setLanguage(sLang);
            this.getEventBus().publish("app", "languageChanged", { lang: sLang });
        }
    });
});
