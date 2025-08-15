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
                headers: {
                    Accept: "application/json"
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
        // callApi: function (sMethod, sEndpoint, oData, header = null) {
        //     const isFormData = oData instanceof FormData;

        //     const ajaxConfig = {
        //         url: sEndpoint,
        //         type: sMethod,
        //         contentType: isFormData ? false : "application/json",
        //         headers: {
        //             Accept: "application/json"
        //         },
        //         xhrFields: { withCredentials: true },
        //         processData: !isFormData,
        //         data: isFormData ? oData : oData ? JSON.stringify(oData) : null
        //     };

        //     if (header) {
        //         ajaxConfig.headers = header;
        //     }

        //     function performAjax() {
        //         return new Promise(function (resolve, reject) {
        //             ajaxConfig.success = resolve;
        //             ajaxConfig.error = reject;
        //             jQuery.ajax(ajaxConfig);
        //         });
        //     }

        //     // Check if laravel_session cookie exists
        //     if (!document.cookie.includes('laravel_session')) {
        //         // First call CSRF cookie endpoint
        //         return new Promise(function (resolve, reject) {
        //             jQuery.ajax({
        //                 url: "http://localhost:8880/sanctum/csrf-cookie",
        //                 type: "GET",
        //                 xhrFields: { withCredentials: true },
        //                 success: function () {
        //                     performAjax().then(resolve).catch(reject);
        //                 },
        //                 error: function (err) {
        //                     reject(err);
        //                 }
        //             });
        //         });
        //     } else {
        //         // If session exists, just call API
        //         return performAjax();
        //     }
        // },

        getUrl: function (sEndpoint) {
            return "http://localhost:8880/v1/admin/" + sEndpoint;
        }
    };
});
