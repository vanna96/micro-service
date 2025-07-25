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
                "odata.count": 100,
                "value": [
                    {
                        'id' : 1,
                        'name' : 'អាវដៃវែង',
                        'foreign_name': 'Long Shirt',
                        'status': 'Active',
                        'created_at': '2025-02-17'
                    },
                    {
                        'id' : 2,
                        'name' : 'អាវដៃខ្លី',
                        'foreign_name': 'Short Shirt',
                        'status': 'Inactive',
                        'created_at': '2025-02-18'
                    },
                    {
                        'id' : 3,
                        'name' : 'បញ្ចុះតម្លៃ១០%',
                        'foreign_name': 'Discount 10%',
                        'status': 'Active',
                        'created_at': '2025-02-19'
                    },
                    {
                        'id' : 4,
                        'name' : 'បញ្ចុះតម្លៃ១០%',
                        'foreign_name': 'Discount 10%',
                        'parent': 'បញ្ចុះតម្លៃ',
                        'status': 'Active'
                    },
                    {
                        'id' : 5,
                        'name' : 'បញ្ចុះតម្លៃ',
                        'foreign_name': 'Discount',
                        'status': 'Active',
                        'created_at': '2025-02-20'
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
