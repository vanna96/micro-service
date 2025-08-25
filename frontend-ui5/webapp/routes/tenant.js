sap.ui.define([], function () {
    const routes = [
        {
            "pattern": "tenant",
            "name": "tenant",
            "target": "tenant"
        },
        {
            "pattern": "tenant/create",
            "name": "tenant_create",
            "target": "tenant_form"
        },
        {
            "pattern": "tenant/edit/:id:",
            "name": "tenant_edit",
            "target": "tenant_form"
        },
    ];

    const targets = {
        "tenant": {
            "viewName": "tenant.list",
            "controlId": "appContainer",
            "parent": "main",
            "viewLevel": 1
        },
        "tenant_form": {
            "viewName": "tenant.form",
            "controlId": "appContainer",
            "parent": "main",
            "viewLevel": 1
        }
    }

    return {
        routes,
        targets
    };
});
