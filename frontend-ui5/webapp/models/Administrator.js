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
                profile: data?.attachments?.[0]?.SourcePath
            }
            return json
        },

        toModel: async function (data) {
            const attachments = await Promise.all(
                data['galleries']?.map(async (item) => {
                    const fileName = item.name.split('/').pop(); 
                    const fileExtension = fileName.split('.').pop().toLowerCase();
                    const isImage = ["jpg", "jpeg", "png", "gif", "bmp", "webp"].includes(fileExtension);
                    let base64Data = null;

                    if (isImage) {
                        try {
                            const response = await fetch(item.image_url);
                            const blob = await response.blob();
                            base64Data = await new Promise((resolve, reject) => {
                                const reader = new FileReader();
                                reader.onloadend = () => resolve(reader.result);
                                reader.onerror = reject;
                                reader.readAsDataURL(blob);
                            });
                        } catch (err) {
                            console.error("Failed to convert image to base64:", err);
                        }
                    }

                    return {
                        documentId: item.id,
                        FileExtension: fileExtension,
                        FileName: fileName,
                        SourcePath: base64Data,
                        mimeType: isImage ? `image/${fileExtension}` : null,
                        thumbnailUrl: item.image_url,
                        AttachmentDate: item.created_at
                    };
                }) || []
            );

            return {
                id: data['id'], 
                name: data['name'], 
                username: data['username'],
                email: data['email'],
                password: data['password'],
                password_confirmation: data['cpassword'],
                country_code: "855",
                phone: data['phone'],
                firstName: data['first_name'],
                lastName: data['last_name'],
                gender: data['gender'],
                dob: data['dob'],
                status: data['status'],
                DocumentDate: data['created_at'],
                attachments: attachments
            };
        }

    };
});