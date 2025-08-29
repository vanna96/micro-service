sap.ui.define([], function () {
    const routes = [
        {
            "pattern": "category",/*/:?tenant:*/
            "name": "category",
            "target": "category"
        },
        {
            "pattern": "category/create",
            "name": "category_create",
            "target": "category_form"
        },
        {
            "pattern": "category/edit/:id:",
            "name": "category_edit",
            "target": "category_form"
        },
    ];

    const targets = {
        "category": {
            "viewName": "master_data.category.list",
            "controlId": "appContainer",
            "parent": "main",
            "viewLevel": 1
        },
        "category_form": {
            "viewName": "master_data.category.form",
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
