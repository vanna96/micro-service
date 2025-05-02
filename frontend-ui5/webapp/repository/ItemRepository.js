sap.ui.define([
    "my/app/util/HttpService",
], function (HttpService){
    "use strict";

    return {
        endPoint: "Items",
        get: async function (queryParams) {
            function toQueryString(params){
                return Object.keys(params)
                    .map(function (key){
                        return encodeURIComponent(key) + "=" + encodeURIComponent(params[key]);
                    })
                    .join("&");
            }
            
            const queryString = queryParams ? "?" + toQueryString(queryParams) : "";
            return await HttpService.callApi("GET", `${this.endPoint}${queryString}`); 
        } 
    };
});
