sap.ui.define([], function () {
    return {
        "routes": [
            // category
            {
                "pattern": "category",
                "name": "category",
                "target": "category"
            },
            // item
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
        ],
        "targets": {
            // category
            "category": {
                "viewName": "master_data.category.list",
                "controlId": "appContainer",
                "parent": "main",
                "viewLevel": 1
            },
            // item
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
    };
});
