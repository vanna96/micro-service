sap.ui.define([
    "my/app/controller/Base.controller",
    "my/app/repository/TenantRepository",
    "my/app/util/FileHelper",
    "my/app/util/Funtion",
    "my/app/models/Tenant",
    "sap/ui/core/BusyIndicator",
], function (
    BaseController,
    TenantRepository,
    FileHelper,
    Funtion,
    TenantModel,
    BusyIndicator
) {
    "use strict";

    return BaseController.extend("my.app.controller.Tenant.Form", Object.assign({

        onInit: function () {
            BaseController.prototype.onInit.call(this);
            this.oRouter.getRoute("tenant_create").attachPatternMatched(this.hanlderCreateForm, this);
            this.oRouter.getRoute("tenant_edit").attachPatternMatched(this.hanlderEditForm, this);
        },

        hanlderCreateForm: function () {
            document.title = "Tenant Form Create";
            this.oModel.setData({
                titleForm: 'Form Create',
                buttonSubmit: 'Save',
                DocumentDate: this.formatDisplayDate(new Date()),
                status: 'Active',
                requiredFields: [
                    { key: "id", msg: "Tenant ID is required!" },
                    { key: "db_name", msg: "DB Name is required!" },
                    { key: "db_connection", msg: "DB Connection is required!" },
                    { key: "db_port", msg: "DB Port is required!" },
                    { key: "db_username", msg: "DB Username is required!" },
                    { key: "db_host", msg: "DB Host is required!" },
                    { key: "db_password", msg: "DB Password is required!" },
                ]
            });
        },

        hanlderEditForm: async function (oEvent) {
            document.title = "Tenant Form Edit";
            BusyIndicator.show();
            const oParams = oEvent.getParameter("arguments");
            const res = await TenantRepository.find(oParams?.id ?? 0)
            const data = await TenantModel.toModel(res.data);
            this.oModel.setData({
                titleForm: 'Form Edit',
                buttonSubmit: 'Update',
                ...data,
                requiredFields: [
                    { key: "id", msg: "Tenant ID is required!" },
                    { key: "db_name", msg: "DB Name is required!" },
                    { key: "db_connection", msg: "DB Connection is required!" },
                    { key: "db_port", msg: "DB Port is required!" },
                    { key: "db_username", msg: "DB Username is required!" },
                    { key: "db_host", msg: "DB Host is required!" },
                    { key: "db_password", msg: "DB Password is required!" },
                ]
            });
            BusyIndicator.hide();
        },

        handlerChangeFile: function (oEvent) {
            return FileHelper.handlerChangeFiles.call(this, oEvent, false);
        },

        handlerSave: async function () {
            var oRouter = sap.ui.core.UIComponent.getRouterFor(this);
            var data = this.oModel.getData();
            var payload = TenantModel.toJson(data);

            if (Funtion.validateRequiredFields.call(this, {
                data: payload,
                requiredFields: data.requiredFields
            })) return;

            try {
                let res;
                if (data.buttonSubmit === 'Update') res = await TenantRepository.update(data.id, payload);
                else res = await TenantRepository.post(payload);

                BusyIndicator.hide();
                sap.m.MessageToast.show(res.message);
                setTimeout(() => {
                    oRouter.navTo("tenant")
                }, 1000)
            } catch (error) {
                console.error(error)
                Funtion.errMessageDialog.call(this, error)
                BusyIndicator.hide();
            }
        }

    }, FileHelper, Funtion));
});
