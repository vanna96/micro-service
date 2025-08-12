sap.ui.define([], function () {
    const routes = [
        {
            "pattern": "category",
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

        {
            "pattern": "uom",
            "name": "uom",
            "target": "uom"
        },
        {
            "pattern": "uom/create",
            "name": "uom_create",
            "target": "uom_form"
        },
        {
            "pattern": "uom/edit/:id:",
            "name": "uom_edit",
            "target": "uom_form"
        },

        {
            "pattern": "item",
            "name": "item",
            "target": "item"
        },
        {
            "pattern": "item/create",
            "name": "item_create",
            "target": "item_form"
        }
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
        },

        "uom": {
            "viewName": "master_data.uom.list",
            "controlId": "appContainer",
            "parent": "main",
            "viewLevel": 1
        },
        "uom_form": {
            "viewName": "master_data.uom.form",
            "controlId": "appContainer",
            "parent": "main",
            "viewLevel": 1
        },

        "item": {
            "viewName": "master_data.item.list",
            "controlId": "appContainer",
            "parent": "main",
            "viewLevel": 1
        },
        "item_form": {
            "viewName": "master_data.item.form",
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
