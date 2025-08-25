sap.ui.define([
    "my/app/controller/Base.controller",
    "my/app/repository/TenantRepository",
    "my/app/util/Pagination",
    "my/app/util/ToolbarList",
    "my/app/util/Funtion",
    "sap/ui/core/BusyIndicator",
], function (
    BaseController,
    TenantRepository,
    Pagination,
    ToolbarList,
    Funtion,
    BusyIndicator
) {
    "use strict";

    return BaseController.extend("my.app.controller.Tenant.List", Object.assign(Pagination, ToolbarList, {

        onInit: function () {
            BaseController.prototype.onInit.call(this);
            this.oRouter.getRoute("tenant").attachPatternMatched(this.onListing, this);
        },

        onListing: async function () {
            document.title = "Tenant";
            this.oModel.setData({
                pageSize: 20,
                isLoading: false
            });

            await this.loadData(1);
        },

        loadData: async function (pageNumber) {
            this.oModel.setProperty("/isLoading", true);
            const pageSize = this.oModel.getProperty("/pageSize");
            const search = this.oModel.getProperty("/search")

            const oParams = {
                per_page: pageSize,
                page: pageNumber,
                search: search
            };

            try {
                const res = await TenantRepository.get(oParams);
                const totalItems = res["total"] ?? 0;
                const paginationInfo = this.getPaginationInfo(totalItems, pageSize, pageNumber);
                this.oModel.setProperty("/data", res['data'] ?? []);
                this.oModel.setProperty("/pagination", paginationInfo);

            } catch (error) {
                console.error("Error loading data:", error);
            } finally {
                this.oModel.setProperty("/isLoading", false);
            }
        },

        handlerEdit: function (oEvent) {
            var oItem = oEvent.getSource();
            var sId = oItem.getBindingContext("model").getProperty("id");
            this.oRouter.navTo("tenant_edit", { id: sId });
        },

        handlerCreate: function () {
            this.oRouter.navTo("tenant_create");
        },

        handlerDelete: function (oEvent) {
            var that = this;
            var oItem = oEvent.getSource();
            var sId = oItem.getBindingContext("model").getProperty("id");
            new Funtion.smgDialog({
                type: 'Error',
                title: 'Confirm deletion',
                smg: 'Are you sure you want to delete this item?',
                onOk: async function () {
                    try {
                        BusyIndicator.show();
                        const res = await TenantRepository.delete(sId);
                        sap.m.MessageToast.show(res.message);
                        BusyIndicator.hide();
                        that.handlerResetList();
                    } catch (error) {
                        console.error(error)
                        Funtion.errMessageDialog.call(that, error)
                        BusyIndicator.hide();
                    }
                }
            });
        }
    }));
});
