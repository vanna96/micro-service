sap.ui.define([], function () {
    "use strict";

    return {
        callApi: function (sMethod, sEndpoint, oData, header = null) {
            const isFormData = oData instanceof FormData;
            // const sUrl = this.getUrl(sEndpoint);
        
            const ajaxConfig = {
                url: sEndpoint,
                type: sMethod,
                contentType: isFormData ? false : "application/json",
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
            return "https://192.168.1.199:50000/b1s/v1/" + sEndpoint;
        }
    };
});
