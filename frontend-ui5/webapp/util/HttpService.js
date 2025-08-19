sap.ui.define([
    "my/app/util/Cookie",
    "my/app/util/Crypto"
], function (Cookie, Crypto) {
    "use strict";

    return {
        callApi: function (sMethod, sEndpoint, oData, header = null) {
            const cookie = Cookie.getCookie('XSRF-TOKEN');
            const userData = Cookie.getCookie("userData");
            let token = userData ?  Crypto.decryptData(Cookie.getCookie("userData"))?.token : null;
            const isFormData = oData instanceof FormData;

            return new Promise(function (resolve, reject) {
                const ajaxConfig = {
                    url: sEndpoint,
                    type: sMethod,
                    contentType: isFormData ? false : "application/json",
                    headers: {
                        Accept: "application/json",
                        // "X-XSRF-TOKEN": cookie
                        Authorization: token ? "Bearer " + token : ""
                    },
                    // xhrFields: { withCredentials: true },
                    processData: !isFormData,
                    data: isFormData ? oData : oData ? JSON.stringify(oData) : null,
                    success: (data) => resolve(data),
                    error: (jqXHR) => {
                        if (jqXHR.status == 422) { 
                            window.location.href = "/index.html";
                        }
                        reject(jqXHR);
                    }
                };

                if (header) {
                    ajaxConfig.headers = header;
                }

                jQuery.ajax(ajaxConfig);
            });
        },

        getUrl: function (sEndpoint) {
            return "http://localhost:8880/v1/admin/" + sEndpoint;
        }
    };
});
