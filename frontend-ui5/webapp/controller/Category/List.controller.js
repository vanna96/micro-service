sap.ui.define([
    "my/app/controller/Base.controller",
    "my/app/repository/CategoryRepository",
    "my/app/util/Pagination",
    "my/app/util/ToolbarList",
    "my/app/util/Helper",
    "sap/m/MessageBox",
], function (
    BaseController,
    CategoryRepository,
    Pagination,
    ToolbarList,
    Helper,
    MessageBox
) {
    "use strict";

    return BaseController.extend("my.app.controller.Category.List", Object.assign(Pagination, ToolbarList, {
        formatter: Helper,
        onInit: function () {
            BaseController.prototype.onInit.call(this);
            this.oRouter.getRoute("category").attachPatternMatched(this.onListing, this);
        },

        onListing: async function () {
            document.title = "Category";
            this.oModel.setData({
                pageSize: 20,
                isLoading: false,
                search: ''
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
                search: search,
                tenant: sessionStorage.getItem('tenant_id')
            };

            try {
                const res = await CategoryRepository.get(oParams);
                const totalItems = res["total"] ?? 0;
                const paginationInfo = this.getPaginationInfo(totalItems, pageSize, pageNumber);
                this.oModel.setProperty("/data", res['data'] ?? []);
                this.oModel.setProperty("/pagination", paginationInfo);

            } catch (error) {
                console.error("Error loading data:", error);
                this.oModel.setProperty("/data", []);
                this.oModel.setProperty("/pagination", null);
                let errorMessage = "Unknown error occurred.";
                if (error && error.responseJSON) {
                    if (error.responseJSON && error.responseJSON.message) {
                        errorMessage = error.responseJSON.message;
                    }
                }

                MessageBox.error(errorMessage);
            } finally {
                this.oModel.setProperty("/isLoading", false);
            }
        },

        handlerEdit: function (oEvent) {
            var oItem = oEvent.getSource();
            var sId = oItem.getBindingContext("model").getProperty("id");
            this.oRouter.navTo("category_edit", { id: sId });
        },

        handlerCreate: function () {
            this.oRouter.navTo("category_create");
        }
    }));
});
