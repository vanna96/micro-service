sap.ui.define([], function () {
    "use strict";

    const SECRET_KEY = "MyVery$ecretKey123!";

    function encryptData(data) {
        const jsonString = JSON.stringify(data);
        return window.CryptoJS.AES.encrypt(jsonString, SECRET_KEY).toString();
    }

    function decryptData(ciphertext) {
        const bytes = window.CryptoJS.AES.decrypt(ciphertext, SECRET_KEY);
        const decryptedString = bytes.toString(window.CryptoJS.enc.Utf8);
        return JSON.parse(decryptedString);
    }

    return {
        encryptData,
        decryptData
    };
});