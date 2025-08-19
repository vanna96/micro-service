sap.ui.define([
    "my/app/util/HttpService",
], function (HttpService){
    "use strict";

    return {
        endPoint: "administrator",
        get: async function (queryParams) {
            function toQueryString(params){
                return Object.keys(params)
                    .map(function (key){
                        return encodeURIComponent(key) + "=" + encodeURIComponent(params[key]);
                    })
                    .join("&");
            }
            const queryString = queryParams ? "?" + toQueryString(queryParams) : "";
            return await HttpService.callApi("GET", HttpService.getUrl(`${this.endPoint}${queryString}`)); 
        },
        count: async function (queryParams) {
            const res =  await HttpService.callApi("GET", `https://jsonplaceholder.typicode.com/posts`);  
            return res.length ?? 0;
        },
    };
});
