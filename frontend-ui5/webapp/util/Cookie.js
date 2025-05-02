sap.ui.define([], function () {
    "use strict";
    return {
        setCookie: function (name, value, days) {
            let expires = "";
            if (days) {
                const date = new Date();
                date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
                expires = "; expires=" + date.toUTCString();
            }
            document.cookie = name + "=" + encodeURIComponent(value) + expires + "; path=/";
        },

        getCookie: function (name) {
            const nameEQ = name + "=";
            const cookies = document.cookie.split(';');
            for (let i = 0; i < cookies.length; i++) {
                const cookie = cookies[i].trim();
                if (cookie.indexOf(nameEQ) === 0) {
                    return decodeURIComponent(cookie.substring(nameEQ.length, cookie.length));
                }
            }
            return null;
        },

        deleteCookie: function (cookieName) {
            const cookies = document.cookie.split(";");
 
            for (let i = 0; i < cookies.length; i++) {
                const cookie = cookies[i].trim();
                 
                if (cookie.indexOf(cookieName + "=") === 0) {
                    // Delete the cookie by setting its expiration date to a past date
                    document.cookie = cookieName + "=; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT";
                }
            }
        }
    };
});
