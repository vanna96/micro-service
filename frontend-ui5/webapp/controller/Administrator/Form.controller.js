sap.ui.define([
    "my/app/controller/Base.controller",
    "my/app/repository/AdministratorRepository",
    "my/app/util/FileHelper",
    "my/app/util/Funtion",
    "my/app/models/Administrator",
    "sap/ui/core/BusyIndicator",
    "my/app/util/HttpService",
    "my/app/repository/TenantRepository",
    "my/app/models/Tenant",
], function (
    BaseController,
    AdministratorRepository,
    FileHelper,
    Funtion,
    AdministratorModel,
    BusyIndicator,
    HttpService,
    TenantRepository,
    TenantModel
) {
    "use strict";

    return BaseController.extend("my.app.controller.Administrator.Form", Object.assign({

        onInit: function () {
            BaseController.prototype.onInit.call(this);
            this.oRouter.getRoute("administrator_create").attachPatternMatched(this.hanlderCreateForm, this);
            this.oRouter.getRoute("administrator_edit").attachPatternMatched(this.hanlderEditForm, this);
        },

        hanlderCreateForm: function () {
            document.title = "";
            this.oModel.setData({
                titleForm: 'Administrator Form Create',
                buttonSubmit: 'Save',
                DocumentDate: this.formatDisplayDate(new Date()),
                status: 'Active',
                requiredFields: [
                    { key: "username", msg: "Username is required!" },
                    { key: "password", msg: "Password is required!" },
                    { key: "password_confirmation", msg: "Confirm Password is required!" },
                    { key: "name", msg: "Name is required" },
                    { key: "phone", msg: "Phone is required" },
                ]
            });
        },

        hanlderEditForm: async function (oEvent) {
            document.title = "Administrator Form Edit";
            BusyIndicator.show();
            const oParams = oEvent.getParameter("arguments");
            const res = await AdministratorRepository.find(oParams?.id ?? 0)
            const data = await AdministratorModel.toModel(res.data);
            const tenants = await this.handlerLoadTenants();

            this.oModel.setData({
                titleForm: 'Form Edit',
                buttonSubmit: 'Update',
                ...data,
                requiredFields: [
                    { key: "username", msg: "Username is required!" },
                    { key: "name", msg: "Name is required" },
                    { key: "phone", msg: "Phone is required" },
                ],
                tenants
            });
            BusyIndicator.hide();
        },

        handlerChangeFile: function (oEvent) {
            return FileHelper.handlerChangeFiles.call(this, oEvent, false);
        },

        handlerSave: async function () {
            var oRouter = sap.ui.core.UIComponent.getRouterFor(this);
            var data = this.oModel.getData();
            var payload = AdministratorModel.toJson(data);

            if (Funtion.validateRequiredFields.call(this, {
                data: payload,
                requiredFields: data.requiredFields
            })) return;
            
            try {
                BusyIndicator.show();
                let res;
                if (data.buttonSubmit === 'Update') res = await AdministratorRepository.update(data.id, payload);
                else res = await AdministratorRepository.post(payload);

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
        },

        handlerAddTenantRow: function () {
            let doc_tenants = this.oModel.getProperty('/doc_tenants') || [];
            doc_tenants.push({});
            this.oModel.setProperty('/doc_tenants', doc_tenants);
        },

        handlerLoadTenants: async function (oEvent) {
            var oComboBox = oEvent?.getSource();
            var oContext = oComboBox?.getBindingContext("model");
            
            oContext?.setProperty("isBusy", true);

            let _res = await TenantRepository.get({
                per_page: 1000000
            });
            _res = (_res.data || []).map((result) => new TenantModel.toModel(result))
            
            oContext?.setProperty("isBusy", false);
            this.oModel.setProperty('/tenants', _res);
            return _res;
        },

        handlerChangeTenant: function (oEvent) {
            var oSource = oEvent.getSource();
            var oBindingContext = oSource.getBindingContext("model");
            var sPath = oBindingContext.getPath();
            var aParts = sPath.split("/");
            var iIndex = aParts[aParts.length - 1];

            var oSelectedItem = oSource.getSelectedItem();
            if (oSelectedItem)
            {
                var oContext = oSelectedItem.getBindingContext("model");
                var oSelectedObject = oContext.getObject(); 
                
                this.oModel.setProperty(`/doc_tenants/${ iIndex }/tenant`, oSelectedObject)
                this.oModel.setProperty(`/doc_tenants/${iIndex}/tenant_id`, oSource.getSelectedKey());
            } 
        },

        handlerRemoveTenants: function (oEvent) {
            var oTable = this.byId("tenants_table");
            var oModel = this.getView().getModel("model");
            var aSelectedIndices = oTable.getSelectedIndices();

            if (aSelectedIndices.length === 0) {
                sap.m.MessageToast.show("Please select rows to delete.");
                return;
            }

            var aDocTenants = oModel.getProperty("/doc_tenants");

            aSelectedIndices.sort(function (a, b) { return b - a; });
            aSelectedIndices.forEach(function (iIndex) {
                aDocTenants.splice(iIndex, 1);
            });

            oModel.setProperty("/doc_tenants", aDocTenants);
            oTable.clearSelection();
        }
    }, FileHelper, Funtion));
});
