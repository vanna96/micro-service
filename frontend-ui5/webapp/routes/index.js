sap.ui.define([], function () {
    return {
        "routes": [
            {
                "pattern": "",
                "name": "dashboard",
                "target": "dashboard"
            },
            {
                "pattern": "login",
                "name": "login",
                "target": "login"
            }
        ],
        "targets": {
            "main": {
                "viewName": "Main",
                "viewType": "XML",
                "controlAggregation": "pages"
            },
            "notFound": {
                "viewName": "NotFound",
                "transition": "show"
            },
            "dashboard": {
                "viewName": "Dashboard",
                "controlId": "appContainer",
                "parent": "main",
                "viewLevel": 1
            },
            "login": {
                "viewName": "Authentication.Login"
            }
        }
    };
});
