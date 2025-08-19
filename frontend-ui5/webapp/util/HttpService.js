sap.ui.define([
    "my/app/util/Cookie"
], function (Cookie) {
    "use strict";

    return {
        callApi: function (sMethod, sEndpoint, oData, header = null) {
            const isFormData = oData instanceof FormData;
            const ajaxConfig = {
                url: sEndpoint,
                type: sMethod,
                contentType: isFormData ? false : "application/json",
                headers: {
                    Accept: "application/json",
                    "X-XSRF-TOKEN": Cookie.getCookie('XSRF-TOKEN')
                },
                xhrFields: { withCredentials: true },
                processData: !isFormData,
                data: isFormData ? oData : oData ? JSON.stringify(oData) : null,
                success: (data) => resolve(data),
                error: (error) => reject(error)
            };

            if (header) {
                ajaxConfig.headers = header;
            }

            return new Promise(function (resolve, reject) {
                ajaxConfig.success = resolve;
                ajaxConfig.error = reject;
                jQuery.ajax(ajaxConfig);
            });
        },

        getUrl: function (sEndpoint) {
            return "http://localhost:8880/v1/admin/" + sEndpoint;
        }
    };
});
