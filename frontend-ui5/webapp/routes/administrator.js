sap.ui.define([], function () {
    const routes = [
        {
            "pattern": "administrator",
            "name": "administrator",
            "target": "administrator"
        },
        {
            "pattern": "administrator/create",
            "name": "administrator_create",
            "target": "administrator_form"
        },
        {
            "pattern": "administrator/edit/:id:",
            "name": "administrator_edit",
            "target": "administrator_form"
        },
    ];

    const targets = {
        "administrator": {
            "viewName": "administrator.list",
            "controlId": "appContainer",
            "parent": "main",
            "viewLevel": 1
        },
        "administrator_form": {
            "viewName": "administrator.form",
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
