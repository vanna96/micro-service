sap.ui.define([
], function () {
    "use strict";
    return {
        toJson: function (data) {
            var json = {
                id: data['id'],
                db_name: data['db_name'],
                db_host: data['db_host'],
                db_connection: data['db_connection'],
                db_username: data['db_username'],
                db_password: data['db_password'],
                db_port: data['db_port'],
                status: data['status'],
            }
            return json
        },

        toModel: function (data) {
            return {
                id: data['id'],
                db_name: data['db_name'],
                db_host: data['db_host'],
                db_connection: data['db_connection'],
                db_username: data['db_username'],
                db_password: data['db_password'],
                db_port: data['db_port'],
                status: data['status'],
                DocumentDate: data['created_at']
            };
        }

    };
});
