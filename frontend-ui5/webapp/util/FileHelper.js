sap.ui.define([], function () {
    "use strict";

    return {
        handlerChangeFiles: function (oEvent, allowMultiple = true) {  
            const oFile = oEvent.getParameter("files")[0];
            if (oFile) {
                const oModel = this.oModel;
                const oReader = new FileReader();
                oReader.onload = (e) => {
                    const sImagePath = e.target.result;
                    const sFileName = oFile.name;
                    const sFileExtension = sFileName.split(".").pop().toLowerCase();
                    const mimeType = oFile.type;

                    const isImage = ["jpg", "jpeg", "png", "gif", "bmp", "webp"].includes(sFileExtension);

                    const oNewItem = {
                        documentId: Date.now(),
                        FileName: sFileName,
                        FileExtension: sFileExtension,
                        SourcePath: sImagePath,
                        mimeType: mimeType,
                        thumbnailUrl: isImage ? sImagePath : ""
                    };

                    if (allowMultiple) {
                        // Add to existing
                        const aAttachments = oModel.getProperty("/attachments") || [];
                        const aFiles = oModel.getProperty("/files") || [];

                        aAttachments.push(oNewItem);
                        aFiles.push(oFile);

                        oModel.setProperty("/attachments", aAttachments);
                        oModel.setProperty("/files", aFiles);
                    } else {
                        // Replace with only one
                        oModel.setProperty("/attachments", [oNewItem]);
                        oModel.setProperty("/files", [oFile]);
                    }
                };
                oReader.readAsDataURL(oFile);
            }
        },

        hanlderDeletedFile: function (oEvent) {
            const sDocumentId = oEvent.getParameter("documentId");
            const oModel = this.oModel;
            const aAttachments = oModel.getProperty("/attachments") || [];
            const files = oModel.getProperty("/files") || [];

            const iIndex = aAttachments.findIndex(o => String(o.documentId) === String(sDocumentId));
            if (iIndex === -1) return;

            aAttachments.splice(iIndex, 1);
            files.splice(iIndex, 1);

            oModel.setProperty("/attachments", aAttachments);
            oModel.setProperty("/files", files);
        },

        hanlderPressThumbnail: function (oEvent) {
            const oItem = oEvent.getSource();
            const sFileUrl = oItem.getBindingContext("model").getProperty("SourcePath");

            if (!sFileUrl) {
                sap.m.MessageToast.show("File not available");
                return;
            }

            if (sFileUrl.startsWith("data:")) {
                const win = window.open();
                if (!win) {
                    sap.m.MessageToast.show("Popup blocked. Please allow popups.");
                    return;
                }

                win.document.write(`
                    <html>
                        <head><title>Preview</title></head>
                        <body style="margin:0">
                            <iframe src="${sFileUrl}" width="100%" height="100%" frameborder="0" style="border: none;"></iframe>
                        </body>
                    </html>
                `);
            } else {
                window.open(sFileUrl, "_blank");
            }
        }
    };
});
