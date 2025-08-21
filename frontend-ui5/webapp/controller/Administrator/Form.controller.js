sap.ui.define([ 
    "my/app/controller/Base.controller",
    "my/app/repository/AdministratorRepository",
    "my/app/util/FileHelper",
    "my/app/util/Funtion",
    "my/app/models/Administrator",
    "sap/ui/core/BusyIndicator",
    "my/app/util/HttpService",
], function (
    BaseController,
    AdministratorRepository, 
    FileHelper,
    Funtion,
    AdministratorModel,
    BusyIndicator,
    HttpService
) {
    "use strict";

    return BaseController.extend("my.app.controller.Administrator.Form", Object.assign({
    
        onInit: function () {
            BaseController.prototype.onInit.call(this);
            document.title = "Administrator Form";  
            this.oRouter.getRoute("administrator_create").attachPatternMatched(this.hanlderCreateForm, this); 
            this.oRouter.getRoute("administrator_edit").attachPatternMatched(this.hanlderEditForm, this); 
        },

        hanlderCreateForm: function(){  
            console.log('create')
            this.oModel.setData({
                titleForm: 'Form Create',
                buttonSubmit: 'Save',
                DocumentDate: this.formatDisplayDate(new Date()),
                status: 'Active',
                requiredFields: [
                    { key: "username", msg: "Username is required!"},
                    { key: "password", msg: "Password is required!"},
                    { key: "password_confirmation", msg: "Confirm Password is required!"},
                    { key: "name", msg: "Name is required" },
                    { key: "phone", msg: "Phone is required" },
                ]
            });
        },

        hanlderEditForm: async function(oEvent){
            BusyIndicator.show();
            const oParams = oEvent.getParameter("arguments");
            const res = await AdministratorRepository.find(oParams?.id ?? 0) 
            const data = await AdministratorModel.toModel(res.data); 
            console.log(data)
            this.oModel.setData({
                titleForm: 'Form Edit',
                buttonSubmit: 'Update',
                ...data,
                requiredFields: [
                    { key: "username", msg: "Username is required!"}, 
                    { key: "name", msg: "Name is required" },
                    { key: "phone", msg: "Phone is required" },
                ]
            });
            BusyIndicator.hide();
        },

        handlerChangeFile: function (oEvent) {
            return FileHelper.handlerChangeFiles.call(this, oEvent, false);
        }, 

        handlerSave: async function(){  
            var oRouter = sap.ui.core.UIComponent.getRouterFor(this);
            var data = this.oModel.getData();
            var payload = AdministratorModel.toJson(data); 
             
            if (Funtion.validateRequiredFields.call(this, {
                data:payload,
                requiredFields:data.requiredFields
            })) return;

            try {
                let method = "POST";
                let url = HttpService.getUrl('register');
                if(data.id){
                    method = "PATCH";
                    url = HttpService.getUrl(`administrator/edit/${data.id}`);
                }
                
                BusyIndicator.show();
                const res = await HttpService.callApi(method, url, payload);
                BusyIndicator.hide(); 
                sap.m.MessageToast.show(res.message);  
                setTimeout(() => {
                    oRouter.navTo("administrator")
                }, 1000)
            } catch (error) { 
                console.error(error)
                Funtion.errMessageDialog.call(this, error)
                BusyIndicator.hide();
            }
        }

    }, FileHelper, Funtion));
});
