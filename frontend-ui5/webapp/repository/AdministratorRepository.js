sap.ui.define([
    "my/app/util/HttpService",
], function (HttpService) {
    "use strict";

    return {
        endPoint: "user",
        get: async function (queryParams) {
            function toQueryString(params) {
                return Object.keys(params)
                    .map(function (key) {
                        return encodeURIComponent(key) + "=" + encodeURIComponent(params[key]);
                    })
                    .join("&");
            }
            const queryString = queryParams ? "?" + toQueryString(queryParams) : "";
            return await HttpService.callApi("GET", HttpService.getUrl(`${this.endPoint}/list${queryString}`));
        },

        find: async function (id) {
            if (!id) return;
            return await HttpService.callApi("GET", HttpService.getUrl(`${this.endPoint}/edit/${id}`));
        },

        post: async function (payload) {
            return await HttpService.callApi("POST", HttpService.getUrl(`${this.endPoint}/store`), payload);
        },

        update: async function (id, payload) {
            if (!id) return;
            return await HttpService.callApi("PATCH", HttpService.getUrl(`${this.endPoint}/update/${id}`), payload);
        },
    };
});
