sap.ui.define([ 
    "my/app/controller/Base.controller",
    "my/app/repository/CategoryRepository",
    "my/app/util/Pagination",
    "my/app/util/FileHelper",
    "my/app/util/Funtion",
], function (
    BaseController,
    CategoryRepository,
    Pagination,
    FileHelper,
    Funtion
) {
    "use strict";

    return BaseController.extend("my.app.controller.UoM.Form", Object.assign({
    
        onInit: function () {
            BaseController.prototype.onInit.call(this);
            document.title = "UoM Form";  
            this.oRouter.getRoute("uom_create").attachPatternMatched(this.hanlderCreateForm, this); 
            this.oRouter.getRoute("uom_edit").attachPatternMatched(this.hanlderEditForm, this); 
        },

        hanlderCreateForm: function(){  
            console.log('create')
            this.oModel.setData({
                titleForm: 'Form Create',
                buttonSubmit: 'Save',
                DocumentDate: this.formatDisplayDate(new Date()),
                status: 'Active'
            });
        },

        hanlderEditForm: function(oEvent){
            const oParams = oEvent.getParameter("arguments");
            console.log(`edit: ${oParams.id}`)
            this.oModel.setData({
                titleForm: 'Form Edit',
                buttonSubmit: 'Update'
            });
        },

        handlerChangeFile: function (oEvent) {
            return FileHelper.handlerChangeFiles.call(this, oEvent, false);
        }, 
    }, FileHelper, Funtion));
});
