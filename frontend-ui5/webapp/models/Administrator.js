sap.ui.define([ 
], function ()
{
    "use strict";
    return {
        toJson: function (data)
        {
            var json = {
                name: data['name'], 
                username: data['username'],
                email: data['email'],
                password: data['password'],
                password_confirmation: data['cpassword'],
                country_code: "855",
                // lng: "km",
                phone: data['phone'],
                first_name: data['firstName'],
                last_name: data['lastName'],
                gender: data['gender'],
                dob: data['dob'],
                status: data['status'],
            }
            return json
        },

        toModel: function (data)
        {
            var json = {
                name: data['name'], 
                username: data['username'],
                email: data['email'],
                password: data['password'],
                password_confirmation: data['cpassword'],
                country_code: "855",
                // lng: "km",
                phone: data['phone'],
                firstName: data['first_name'],
                lastName: data['last_name'],
                gender: data['gender'],
                dob: data['dob'],
                status: data['status'],
            }
            return json
        }
    };
});