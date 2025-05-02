sap.ui.define([
    "my/app/util/HttpService",
], function (HttpService){
    "use strict";

    return {
        endPoint: "Category",
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
                    {
                        'id': "13",
                        'name': "13",
                        'foreign_name': "13",
                        'parent': "13",
                    },
                    {
                        'id': "13",
                        'name': "13",
                        'foreign_name': "13",
                        'parent': "13",
                    },
                    {
                        'id': "13",
                        'name': "13",
                        'foreign_name': "13",
                        'parent': "13",
                    }
                ]
            }
            
            const queryString = queryParams ? "?" + toQueryString(queryParams) : "";
            return await HttpService.callApi("GET", `${this.endPoint}${queryString}`); 
        } 
    };
});
