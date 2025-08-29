sap.ui.define([
], function () {
    "use strict";
    return {
        toJson: function (data) {
            var json = {
                id: data['id'],
                name: data['name'],
                foreign_name: data['foreign_name'], 
                attachment: data?.attachments?.[0]?.SourcePath,
                parent_id: data['parent']
            }
            return json
        },

        toModel: function (data) {
            return {
                id: data['id'],
                name: data['name'],
                foreign_name: data['foreign_name']
            };
        }

    };
});
