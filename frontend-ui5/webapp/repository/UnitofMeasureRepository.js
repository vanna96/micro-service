sap.ui.define([
    "my/app/util/HttpService",
], function (HttpService){
    "use strict";

    return {
        endPoint: "UoM",
        get: async function (queryParams) {
            function toQueryString(params){
                return Object.keys(params)
                    .map(function (key){
                        return encodeURIComponent(key) + "=" + encodeURIComponent(params[key]);
                    })
                    .join("&");
            }
            return {
                "odata.count": 100,
                "value": [
                    {
                        'id' : 1,
                        'name' : 'លីត្រ',
                        'foreign_name': 'Litter',
                        'status': 'Active',
                        'created_at': '2025-02-17'
                    },
                    {
                        'id' : 2,
                        'name' : 'តោន',
                        'foreign_name': 'Tone',
                        'status': 'Inactive',
                        'created_at': '2025-02-18'
                    }
                ]
            };
            const queryString = queryParams ? "?" + toQueryString(queryParams) : "";
            return await HttpService.callApi("GET", HttpService.getUrl(`${this.endPoint}${queryString}`)); 
        },
        count: async function (queryParams) {
            const res =  await HttpService.callApi("GET", `https://jsonplaceholder.typicode.com/posts`);  
            return res.length ?? 0;
        },
    };
});
