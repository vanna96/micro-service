sap.ui.define([], function () {
    const routes = [
        {
            "pattern": "category",
            "name": "category",
            "target": "category"
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
