sap.ui.define([
    "my/app/util/HttpService",
], function (HttpService){
    "use strict";

    return {
        endPoint: "Permissions",
        get: async function (queryParams) {
            function toQueryString(params){
                return Object.keys(params)
                    .map(function (key){
                        return encodeURIComponent(key) + "=" + encodeURIComponent(params[key]);
                    })
                    .join("&");
            }

            return {
                "odata.count":3,
                value:[
                    { key: 'Dashboard' },
                    { key: 'Category' },
                    { key: 'Item' },
                    { key: 'Unit of Messure' },
                ]
            }
            
            const queryString = queryParams ? "?" + toQueryString(queryParams) : "";
            return await HttpService.callApi("GET", `${this.endPoint}${queryString}`); 
        } 
    };
});
