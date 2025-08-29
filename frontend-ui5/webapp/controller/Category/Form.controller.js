sap.ui.define([ 
    "my/app/controller/Base.controller",
    "my/app/repository/CategoryRepository",
    "my/app/util/Pagination",
    "my/app/util/FileHelper",
    "my/app/util/Funtion",
    "my/app/models/Category",
    "sap/ui/core/BusyIndicator",
], function (
    BaseController,
    CategoryRepository,
    Pagination,
    FileHelper,
    Funtion,
    CategoryModel,
    BusyIndicator
) {
    "use strict";

    return BaseController.extend("my.app.controller.Category.Form", Object.assign({

        onInit: function () {
            BaseController.prototype.onInit.call(this);
            this.oRouter.getRoute("category_create").attachPatternMatched(this.hanlderCreateForm, this);
            this.oRouter.getRoute("category_edit").attachPatternMatched(this.hanlderEditForm, this);
        },

        hanlderCreateForm: function () {
            document.title = "";
            this.oModel.setData({
                titleForm: 'Category Form Create',
                buttonSubmit: 'Save',
                DocumentDate: this.formatDisplayDate(new Date()),
                status: 'Active',
                requiredFields: [
                    { key: "name", msg: "Name is required!" }
                ]
            });
        },

        hanlderEditForm: async function (oEvent) {
            document.title = "Category Form Edit";
            BusyIndicator.show();
            const oParams = oEvent.getParameter("arguments");
            const res = await CategoryRepository.find(oParams?.id ?? 0)
            const data = await CategoryModel.toModel(res.data);
            const tenants = await this.handlerLoadTenants();

            this.oModel.setData({
                titleForm: 'Form Edit',
                buttonSubmit: 'Update',
                ...data,
                requiredFields: [
                    { key: "name", msg: "Name is required!" }
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
            var payload = CategoryModel.toJson(data);

            if (Funtion.validateRequiredFields.call(this, {
                data: payload,
                requiredFields: data.requiredFields
            })) return;
            
            try {
                BusyIndicator.show();
                let res;
                if (data.buttonSubmit === 'Update') res = await CategoryRepository.update(data.id, payload);
                else res = await CategoryRepository.post(payload);

                BusyIndicator.hide();
                sap.m.MessageToast.show(res.message);
                setTimeout(() => {
                    oRouter.navTo("category")
                }, 1000)
            } catch (error) {
                console.error(error)
                Funtion.errMessageDialog.call(this, error)
                BusyIndicator.hide();
            }
        }, 

        handlerLoadCategories: async function (oEvent) { 
            this.oModel.setProperty('/isBusyParent', true);
            let _res = await CategoryRepository.get({
                per_page: 1000000,
                tenant: sessionStorage.getItem('tenant_id')
            });
            _res = (_res.data || []).map((result) => new CategoryModel.toModel(result))
            
            this.oModel.setProperty('/isBusyParent', false);
            this.oModel.setProperty('/parents', _res);
            return _res;
        }

    }, FileHelper, Funtion));
});
